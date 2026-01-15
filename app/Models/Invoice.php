<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Traits\HasMoney;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    use HasFactory, HasMoney;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'order_id',
        'our_company_id',
        'counterparty_id',
        'with_vat',
        'comment',
        'discount_type',
        'discount_value',
        'subtotal',
        'vat_amount',
        'total',
        'is_paid',
        'paid_at',
        'excel_path',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'with_vat' => 'boolean',
            'is_paid' => 'boolean',
            'paid_at' => 'date',
        ];
    }

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Scopes

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeWithVat($query)
    {
        return $query->where('with_vat', true);
    }

    public function scopeWithoutVat($query)
    {
        return $query->where('with_vat', false);
    }

    public function scopeForCompany($query, int|OurCompany $company)
    {
        $companyId = $company instanceof OurCompany ? $company->id : $company;

        return $query->where('our_company_id', $companyId);
    }

    public function scopeForYear($query, ?int $year = null)
    {
        $year = $year ?? now()->year;

        return $query->whereYear('invoice_date', $year);
    }

    public function scopeForMonth($query, ?int $month = null, ?int $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        return $query->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month);
    }

    // Accessors

    protected function formattedInvoiceDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->invoice_date?->format('d.m.Y'),
        );
    }

    protected function formattedPaidAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paid_at?->format('d.m.Y'),
        );
    }

    // Helpers

    public function markAsPaid(?string $date = null): void
    {
        $this->update([
            'is_paid' => true,
            'paid_at' => $date ?? now()->toDateString(),
        ]);

        $this->order?->markAsPaid();
    }

    public function markAsUnpaid(): void
    {
        $this->update([
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function hasExcel(): bool
    {
        return $this->excel_path && Storage::exists($this->excel_path);
    }

    public function hasPdf(): bool
    {
        return $this->pdf_path && Storage::exists($this->pdf_path);
    }

    public function getExcelUrl(): ?string
    {
        return $this->hasExcel() ? Storage::url($this->excel_path) : null;
    }

    public function getPdfUrl(): ?string
    {
        return $this->hasPdf() ? Storage::url($this->pdf_path) : null;
    }

    public function recalculateTotals(): void
    {
        $itemsTotal = $this->items()->sum('total');

        // Apply invoice-level discount
        $discount = $this->discount_type->calculate($itemsTotal, $this->discount_value);
        $subtotal = $itemsTotal - $discount;

        // Calculate VAT if applicable
        $vatAmount = $this->with_vat ? round($subtotal * 0.2, 2) : 0;
        $total = $subtotal + $vatAmount;

        $this->update([
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ]);
    }

    public static function getNextNumber(OurCompany $company): string
    {
        $year = now()->year;
        $prefix = "INV-{$company->id}-{$year}-";

        $lastNumber = static::where('invoice_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(invoice_number, -5) AS UNSIGNED) DESC')
            ->value('invoice_number');

        if ($lastNumber) {
            $currentNumber = (int) substr($lastNumber, -5);
            $nextNumber = $currentNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
