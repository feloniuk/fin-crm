<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Filament\Resources\InvoiceResource;
use App\Models\Counterparty;
use App\Models\Order;
use App\Models\OurCompany;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public ?Order $order = null;

    public bool $shouldCreateAnother = false;

    public function mount(): void
    {
        parent::mount();

        // Check if order_id is passed in URL
        $orderId = request()->query('order_id');
        if ($orderId) {
            $this->order = Order::find($orderId);
            if ($this->order) {
                $this->fillFormFromOrder();
            }
        }
    }

    protected function fillFormFromOrder(): void
    {
        if (!$this->order) {
            return;
        }

        // Use counterparty from order, or find by phone, or create new
        $counterparty = $this->order->counterparty;

        if (!$counterparty && $this->order->customer_phone) {
            $counterparty = Counterparty::where('phone', $this->order->customer_phone)->first();
        }

        if (!$counterparty) {
            $counterparty = Counterparty::create([
                'name' => $this->order->customer_name,
                'phone' => $this->order->customer_phone,
                'address' => $this->order->delivery_address,
                'is_auto_created' => true,
            ]);
        }

        // Get items from order - use order_items if available, fallback to raw_data
        if ($this->order->items()->exists()) {
            // Use order_items with discounts applied
            $items = $this->order->items()->get()->map(function ($item) {
                return [
                    'name' => $item->name,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit ?? 'шт.',
                    'unit_price' => (float) $item->unit_price,
                    'discount_type' => $item->discount_type ?? '',
                    'discount_value' => (float) ($item->discount_value ?? 0),
                    'total' => (float) $item->total,
                ];
            })->toArray();
            \Log::info('Order items found', [
                'order_id' => $this->order->id,
                'items_count' => count($items),
            ]);
        } else {
            // Fallback to raw_data for legacy orders
            $items = collect($this->order->getItemsFromRawData())->map(function ($item) {
                return [
                    'name' => $item['name'],
                    'quantity' => (float) $item['quantity'],
                    'unit' => 'шт.',
                    'unit_price' => (float) $item['unit_price'],
                    'discount_type' => '',
                    'discount_value' => 0,
                    'total' => (float) ($item['quantity'] * $item['unit_price']),
                ];
            })->toArray();
            \Log::info('Using fallback raw_data items', [
                'order_id' => $this->order->id,
                'items_count' => count($items),
            ]);
        }

        $this->form->fill([
            'invoice_date' => now()->toDateString(),
            'order_id' => $this->order->id,
            'our_company_id' => $this->order->our_company_id,
            'with_vat' => (bool) ($this->order->with_vat ?? false),
            'counterparty_id' => $counterparty?->id,
            'counterparty_name' => $counterparty?->name,
            'items' => $items,
            'is_paid' => (bool) ($this->order->payed ?? false),
        ]);
    }


    public function mutateFormDataBeforeCreate(array $data): array
    {
        // Store complete form data before processing
        $this->data = $data;

        \Log::info('Invoice form data before create', [
            'order_id' => $data['order_id'] ?? null,
            'our_company_id' => $data['our_company_id'] ?? null,
            'counterparty_id' => $data['counterparty_id'] ?? null,
            'items_count' => count($data['items'] ?? []),
        ]);

        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // Get all form data
            $formData = $this->data ?? $data;

            \Log::info('Invoice creating with data', ['data_keys' => array_keys($formData)]);

            // Extract values
            $orderId = $formData['order_id'] ?? null;
            $ourCompanyId = $formData['our_company_id'] ?? null;
            $counterpartyId = $formData['counterparty_id'] ?? null;
            $items = $formData['items'] ?? [];
            $withVat = (bool) ($formData['with_vat'] ?? false);
            $comment = $formData['comment'] ?? null;
            $discountType = DiscountType::tryFrom($formData['discount_type'] ?? '') ?? DiscountType::NONE;
            $discountValue = (float) ($formData['discount_value'] ?? 0);
            $isPaid = (bool) ($formData['is_paid'] ?? false);

            \Log::info('Extracted values', [
                'ourCompanyId' => $ourCompanyId,
                'counterpartyId' => $counterpartyId,
                'orderId' => $orderId,
                'items_count' => count($items),
                'items_sample' => array_slice($items, 0, 1),
            ]);

            // Validate required fields
            if (empty($ourCompanyId)) {
                throw new \Exception('Наша компанія не вибрана');
            }
            if (empty($counterpartyId)) {
                throw new \Exception('Контрагент не вибран. counterparty_id=' . ($counterpartyId ?? 'null'));
            }

            $company = OurCompany::findOrFail($ourCompanyId);
            $counterparty = Counterparty::findOrFail($counterpartyId);
            $order = $orderId ? Order::find($orderId) : null;

            $action = app(CreateInvoiceAction::class);

            $invoice = $action->execute(
                company: $company,
                counterparty: $counterparty,
                items: $items,
                withVat: $withVat,
                order: $order,
                comment: $comment,
                discountType: $discountType,
                discountValue: $discountValue,
                isPaid: $isPaid,
            );

            Notification::make()
                ->success()
                ->title('Успіх')
                ->body("Рахунок {$invoice->invoice_number} успішно створено")
                ->send();

            return $invoice;
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Помилка')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    protected function afterSave(): void
    {
        // If "create another" was triggered, find and redirect to next order
        if ($this->shouldCreateAnother) {
            // Get order_id from the CREATED invoice record
            $currentOrderId = $this->getRecord()->order_id;

            // Find next order with priority:
            // 1. Orders with full info (our_company_id AND with_vat)
            // 2. Other NEW orders without invoice

            $nextOrder = Order::where('status', OrderStatus::NEW->value)
                ->doesntHave('invoice')
                ->where(function ($query) {
                    $query->whereNotNull('our_company_id')
                          ->whereNotNull('with_vat');
                })
                ->where('id', '>', $currentOrderId)
                ->orderBy('id', 'asc')
                ->first();

            // If no order with full info, try other NEW orders
            if (!$nextOrder) {
                $nextOrder = Order::where('status', OrderStatus::NEW->value)
                    ->doesntHave('invoice')
                    ->where('id', '>', $currentOrderId)
                    ->orderBy('id', 'asc')
                    ->first();
            }

            if ($nextOrder) {
                redirect(InvoiceResource::getUrl('create', ['order_id' => $nextOrder->id]))->send();
            } else {
                Notification::make()
                    ->info()
                    ->title('Інформація')
                    ->body('Немає більше заказів для створення рахунку')
                    ->send();

                redirect(InvoiceResource::getUrl('index'))->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        // Default: redirect to view the created invoice
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreateAnotherAction(): Action
    {
        return Action::make('createAnother')
            ->label('Створити та відкрити наступне')
            ->icon('heroicon-m-arrow-path')
            ->action(function () {
                // Set flag to trigger special redirect logic
                $this->shouldCreateAnother = true;
                // Save will trigger getRedirectUrl() which handles finding the next order
                $this->save();
            })
            ->color('primary');
    }
}
