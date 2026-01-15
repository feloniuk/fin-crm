<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Models\OurCompany;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

class TransactionRegistry extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.transaction-registry';

    protected static ?string $navigationGroup = 'Звіти';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Реєстр рахунків';

    public function getCompanyStats()
    {
        return OurCompany::active()
            ->where('annual_limit', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'invoiced' => $company->getYearlyInvoicedAmount(),
                    'paid' => $company->getYearlyPaidAmount(),
                    'limit' => $company->annual_limit,
                    'remaining' => $company->getRemainingLimit(),
                    'percent' => round($company->getLimitUsagePercent() ?? 0, 2),
                ];
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Invoice::query())
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Номер рахунку')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ourCompany.name')
                    ->label('Компанія')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('counterparty.name')
                    ->label('Контрагент')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Сума')
                    ->formatStateUsing(fn ($state) =>
                        number_format($state, 2, ',', ' ') . ' грн'
                    )
                    ->sortable()
                    ->alignment('end'),

                Tables\Columns\IconColumn::make('with_vat')
                    ->label('ПДВ')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle'),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Оплачено')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Дата оплати')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('our_company')
                    ->label('Компанія')
                    ->relationship('ourCompany', 'name'),

                Tables\Filters\Filter::make('invoice_date')
                    ->label('Період')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Від'),
                        Forms\Components\DatePicker::make('to')
                            ->label('До'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($q, $date) => $q->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn ($q, $date) => $q->whereDate('invoice_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('with_vat')
                    ->label('З ПДВ'),

                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Оплачено'),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Invoice $record) => $record->excel_path ? route('invoice.download-excel', $record) : null)
                    ->visible(fn (Invoice $record) => $record->excel_path && file_exists($record->excel_path)),

                Tables\Actions\Action::make('downloadPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->url(fn (Invoice $record) => $record->pdf_path ? route('invoice.download-pdf', $record) : null)
                    ->visible(fn (Invoice $record) => $record->pdf_path && file_exists($record->pdf_path)),

                Tables\Actions\ViewAction::make()
                    ->url(fn (Invoice $record) => route('filament.admin.resources.invoices.view', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAsPaid')
                        ->label('Позначити оплачено')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_paid' => true,
                                    'paid_at' => now(),
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Рахунки позначено як оплачено'),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
