<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Enums\DiscountType;
use App\Filament\Resources\InvoiceResource;
use App\Models\Counterparty;
use App\Models\Order;
use App\Models\OurCompany;
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
                return [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => 'шт.',
                    'unit_price' => $item->unit_price,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'total' => $item->total,
                ];
            })->toArray();
        } else {
            // Fallback to raw_data for legacy orders
            $items = collect($this->order->getItemsFromRawData())->map(function ($item) {
                return [
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit' => 'шт.',
                    'unit_price' => $item['unit_price'],
                    'discount_type' => null,
                    'discount_value' => 0,
                    'total' => $item['quantity'] * $item['unit_price'],
                ];
            })->toArray();
        }

        $this->form->fill([
            'invoice_date' => now(),
            'order_id' => $this->order->id,
            'our_company_id' => $this->order->our_company_id,
            'with_vat' => $this->order->with_vat ?? false,
            'counterparty_id' => $counterparty?->id,
            'counterparty_name' => $counterparty?->name,
            'items' => $items,
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

            \Log::info('Extracted values', [
                'ourCompanyId' => $ourCompanyId,
                'counterpartyId' => $counterpartyId,
                'orderId' => $orderId,
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
}
