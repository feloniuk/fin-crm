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
                $quantity = (float) $item->quantity;
                $unitPrice = (float) $item->unit_price;
                $discountType = $item->discount_type ?? '';
                $discountValue = (float) ($item->discount_value ?? 0);

                // Calculate total consistently
                $subtotal = $quantity * $unitPrice;
                $discountAmount = 0;
                if ($discountType === 'percent' && $discountValue > 0) {
                    $discountAmount = $subtotal * ($discountValue / 100);
                } elseif ($discountType === 'fixed' && $discountValue > 0) {
                    $discountAmount = min($discountValue, $subtotal);
                }
                $total = max(0, $subtotal - $discountAmount);

                return [
                    'name' => $item->name,
                    'quantity' => $quantity,
                    'unit' => $item->unit ?? 'шт.',
                    'unit_price' => $unitPrice,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'total' => $total,
                ];
            })->toArray();
        } else {
            // Fallback to raw_data for legacy orders
            $items = collect($this->order->getItemsFromRawData())->map(function ($item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                return [
                    'name' => $item['name'],
                    'quantity' => $quantity,
                    'unit' => 'шт.',
                    'unit_price' => $unitPrice,
                    'discount_type' => '',
                    'discount_value' => 0,
                    'total' => $quantity * $unitPrice,
                ];
            })->toArray();
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

        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // Get all form data
            $formData = $this->data ?? $data;

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

    protected function getRedirectUrl(): string
    {
        // Default: redirect to view the created invoice
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function createAndNext(): void
    {
        // Get form data before save
        $formData = $this->form->getState();
        $currentOrderId = $formData['order_id'] ?? null;

        // Create the invoice
        $this->create(another: false);

        // Find next order
        if ($currentOrderId) {
            $nextOrder = Order::where('status', OrderStatus::NEW->value)
                ->doesntHave('invoice')
                ->where(function ($query) {
                    $query->whereNotNull('our_company_id')
                          ->whereNotNull('with_vat');
                })
                ->where('id', '>', $currentOrderId)
                ->orderBy('id', 'asc')
                ->first();

            if (!$nextOrder) {
                $nextOrder = Order::where('status', OrderStatus::NEW->value)
                    ->doesntHave('invoice')
                    ->where('id', '>', $currentOrderId)
                    ->orderBy('id', 'asc')
                    ->first();
            }

            if ($nextOrder) {
                $this->redirect(InvoiceResource::getUrl('create', ['order_id' => $nextOrder->id]));
                return;
            }
        }

        Notification::make()
            ->info()
            ->title('Інформація')
            ->body('Немає більше заказів для створення рахунку')
            ->send();

        $this->redirect(InvoiceResource::getUrl('index'));
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            Action::make('createAndNext')
                ->label('Створити та відкрити наступне')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action('createAndNext'),
            $this->getCancelFormAction(),
        ];
    }
}
