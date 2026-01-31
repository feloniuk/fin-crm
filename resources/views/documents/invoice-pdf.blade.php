<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            margin: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
            text-transform: uppercase;
        }
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 12px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 12px;
        }
        .section-content {
            font-size: 12px;
            line-height: 1.6;
            margin-left: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        thead {
            background-color: #f5f5f5;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-left: auto;
            width: 300px;
            font-size: 12px;
            margin-bottom: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        .total-row.final {
            font-weight: bold;
            border-bottom: 2px solid #000;
            border-top: 2px solid #000;
            padding: 8px 0;
            font-size: 13px;
        }
        .total-label {
            flex: 1;
        }
        .total-value {
            text-align: right;
            min-width: 80px;
        }
        .sum-in-words {
            font-size: 12px;
            margin-bottom: 20px;
            font-style: italic;
        }
        .footer {
            font-size: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            font-size: 11px;
        }
        .signature-block {
            width: 40%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
        }
        .comment {
            background-color: #f9f9f9;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 11px;
            border-left: 3px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Рахунок</h1>
        </div>

        <div class="invoice-meta">
            <div>
                <div><strong>Номер:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>Дата:</strong> {{ $invoice->invoice_date->format('d.m.Y') }}</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Продавець:</div>
            <div class="section-content">
                {{ $invoice->ourCompany->name }}<br>
                ЄДРПОУ/IПН: {{ $invoice->ourCompany->edrpou_ipn }}<br>
                @if($invoice->ourCompany->address)
                    Адреса: {{ $invoice->ourCompany->address }}<br>
                @endif
                @if($invoice->ourCompany->phone)
                    Телефон: {{ $invoice->ourCompany->phone }}<br>
                @endif
                @if($invoice->ourCompany->email)
                    Email: {{ $invoice->ourCompany->email }}
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Покупець:</div>
            <div class="section-content">
                {{ $invoice->counterparty->name }}<br>
                @if($invoice->counterparty->edrpou_ipn)
                    ЄДРПОУ/IПН: {{ $invoice->counterparty->edrpou_ipn }}<br>
                @endif
                @if($invoice->counterparty->address)
                    Адреса: {{ $invoice->counterparty->address }}<br>
                @endif
                @if($invoice->counterparty->phone)
                    Телефон: {{ $invoice->counterparty->phone }}<br>
                @endif
                @if($invoice->counterparty->email)
                    Email: {{ $invoice->counterparty->email }}
                @endif
            </div>
        </div>

        @if($invoice->order && ($invoice->order->delivery_name || $invoice->order->delivery_address))
            <div class="section">
                <div class="section-title">Доставка:</div>
                <div class="section-content">
                    @if($invoice->order->delivery_name)
                        Одержувач: {{ $invoice->order->delivery_name }}<br>
                    @endif
                    @if($invoice->order->delivery_city)
                        Місто: {{ $invoice->order->delivery_city }}<br>
                    @endif
                    @if($invoice->order->delivery_address)
                        Адреса: {{ $invoice->order->delivery_address }}<br>
                    @endif
                    @if($invoice->order->delivery_type)
                        Тип доставки: {{ $invoice->order->delivery_type }}
                    @endif
                </div>
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Назва товару/послуги</th>
                    <th style="width: 12%;">Кількість</th>
                    <th style="width: 8%;">Од.изм.</th>
                    <th style="width: 13%;" class="text-right">Ціна</th>
                    <th style="width: 12%;" class="text-right">Підсумок</th>
                    <th style="width: 12%;" class="text-right">Знижка</th>
                    <th style="width: 13%;" class="text-right">Сума</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="text-right">{{ number_format($item->quantity, 3, '.', '') }}</td>
                        <td class="text-center">{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2, ',', ' ') }} грн</td>
                        <td class="text-right">{{ number_format($item->subtotal, 2, ',', ' ') }} грн</td>
                        <td class="text-right">
                            @if($item->discount_amount > 0)
                                {{ number_format($item->discount_amount, 2, ',', ' ') }} грн
                                @if($item->discount_type === 'percent')
                                    <br><span style="font-size: 9px;">({{ number_format($item->discount_value, 2) }}%)</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($item->total, 2, ',', ' ') }} грн</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span class="total-label">Сума без ПДВ:</span>
                <span class="total-value">{{ number_format($invoice->subtotal, 2, ',', ' ') }} грн</span>
            </div>
            @if($invoice->with_vat)
                <div class="total-row">
                    <span class="total-label">ПДВ (20%):</span>
                    <span class="total-value">{{ number_format($invoice->vat_amount, 2, ',', ' ') }} грн</span>
                </div>
            @endif
            <div class="total-row final">
                <span class="total-label">РАЗОМ:</span>
                <span class="total-value">{{ number_format($invoice->total, 2, ',', ' ') }} грн</span>
            </div>
        </div>

        <div class="sum-in-words">
            Сумою: <strong>{{ $sumInWords }}</strong> гривень
        </div>

        @if($invoice->comment)
            <div class="comment">
                <strong>Примітка:</strong> {{ $invoice->comment }}
            </div>
        @endif

        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>{{ $invoice->ourCompany->signatory_name ?? 'Підписант' }}</div>
                <div style="font-size: 10px;">{{ $invoice->ourCompany->signatory_position ?? 'Посада' }}</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>М.П. (печатка)</div>
            </div>
        </div>

        <div class="footer">
            <div>Документ згенеровано автоматично. Не потребує подпису.</div>
            <div>Дата генерації: {{ now()->format('d.m.Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
