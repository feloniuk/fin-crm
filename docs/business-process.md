# Бізнес-процес Finance CRM

## Загальна схема

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐    ┌─────────────┐
│  Магазини       │───▶│  Заказ (NEW)     │───▶│  Рахунок        │───▶│  MEDoc      │
│  Horoshop/Prom  │    │  + Компанія      │    │  (INVOICED)     │    │  (XML)      │
│                 │    │  + ПДВ           │    │  + Оплата       │    │             │
└─────────────────┘    └──────────────────┘    └─────────────────┘    └─────────────┘
      ✅                      ✅                      ✅                    ❌
   Реалізовано            Реалізовано            Реалізовано           В розробці
```

---

## 1. Синхронізація замовлень з магазинів

### Підтримувані платформи

| Платформа | API URL | Авторизація |
|-----------|---------|-------------|
| Horoshop | `{shop_url}/api/` | Login + Password → Token |
| Prom.ua | `my.prom.ua/api/v1/` | Bearer Token |

### Процес синхронізації

```
SyncOrdersAction::execute(Shop $shop)
    │
    ├── ShopApiClientFactory::make($shop)
    │       └── HoroshopApiClient / PromUaApiClient
    │
    ├── $client->authenticate()
    │
    ├── $client->getOrders($since)
    │
    └── foreach ($orders as $orderDTO)
            │
            ├── Order::updateOrCreate(...)
            │
            └── SyncOrderItemsAction::execute($order)
                    └── Парсинг raw_data → OrderItems
```

### Ключові файли

- `app/Services/Shop/HoroshopApiClient.php` - API клієнт Horoshop
- `app/Services/Shop/PromUaApiClient.php` - API клієнт Prom.ua
- `app/Actions/Order/SyncOrdersAction.php` - Синхронізація замовлень
- `app/Actions/Order/SyncOrderItemsAction.php` - Синхронізація товарів

---

## 2. Статуси замовлень

### OrderStatus Enum

| Статус | Значення | Колір | Опис |
|--------|----------|-------|------|
| `NEW` | 'new' | Info | Нове замовлення з магазину |
| `PROCESSING` | 'processing' | Warning | В обробці (резерв) |
| `INVOICED` | 'invoiced' | Primary | Рахунок створено |
| `PAID` | 'paid' | Success | Оплачено |

### Переходи статусів

```
NEW ─────────────────────────────▶ INVOICED ──────────────▶ PAID
     CreateInvoiceAction               Invoice::markAsPaid()
     (створення рахунку)               (позначення оплати)
```

### Умови створення рахунку

```php
// app/Models/Order.php
public function canCreateInvoice(): bool
{
    return $this->status->canCreateInvoice()  // NEW status only
        && !$this->invoice                     // Немає рахунку
        && $this->our_company_id !== null      // Призначена компанія
        && $this->with_vat !== null            // Вказано ПДВ
        && $this->items()->exists();           // Є товари
}
```

---

## 3. Призначення компанії та ПДВ

### Поля замовлення

| Поле | Тип | Опис |
|------|-----|------|
| `our_company_id` | FK → our_companies | Компанія-продавець |
| `with_vat` | boolean | Чи включати ПДВ 20% |

### Способи призначення

#### Одиночне (форма редагування)
- Filament форма в `OrderResource`
- Доступно тільки для статусу `NEW`

#### Масове (bulk action)
- Кнопка "Призначити компанію та ПДВ" в таблиці
- Застосовується до вибраних замовлень без рахунків

### Типи компаній

| Тип | TaxSystem | Ліміт | ПДВ |
|-----|-----------|-------|-----|
| ФОП | SINGLE_TAX | Річний ліміт | Ні |
| ФОП | VAT | Без ліміту | Так (20%) |
| ТОВ | VAT | Без ліміту | Так (20%) |

---

## 4. Створення рахунку

### Процес CreateInvoiceAction

```
CreateInvoiceAction::execute($data)
    │
    ├── InvoiceCalculator::calculate()
    │       ├── subtotal = sum(items)
    │       ├── vat = subtotal × 0.20 (якщо with_vat)
    │       └── total = subtotal + vat - discount
    │
    ├── LimitChecker::checkLimit()
    │       └── Перевірка річного ліміту ФОП
    │
    ├── Invoice::create(...)
    │
    ├── InvoiceItem::create(...) × N
    │
    ├── ExcelInvoiceGenerator::generate()
    │       └── storage/app/invoices/invoice_XXX.xlsx
    │
    ├── PdfInvoiceGenerator::generate()
    │       └── storage/app/invoices/invoice_XXX.pdf
    │
    └── Order::markAsInvoiced()
            └── status = INVOICED
```

### Нумерація рахунків

Формат: `DDMMYYYYNNN`
- DD - день (01-31)
- MM - місяць (01-12)
- YYYY - рік
- NNN - порядковий номер за день

Приклад: `20012026001` = 20 січня 2026, перший рахунок

---

## 5. Оплата рахунку

### Процес

```
InvoiceResource → Action "Оплачено"
    │
    └── Invoice::markAsPaid($date)
            │
            ├── is_paid = true
            ├── paid_at = $date
            │
            └── Order::markAsPaid()
                    └── status = PAID
```

### Поля Invoice

| Поле | Тип | Опис |
|------|-----|------|
| `is_paid` | boolean | Чи оплачено |
| `paid_at` | date | Дата оплати |

---

## 6. MEDoc інтеграція (В РОЗРОБЦІ)

### Формат експорту: XML

Техпідтримка MEDoc рекомендує використовувати XML формат для імпорту документів.

### Планована структура

```
app/Services/MEDoc/
├── MedocXmlGenerator.php       # Генерація XML
├── MedocApiClient.php          # HTTP клієнт (якщо є API)
└── ExportToMedocAction.php     # Action експорту
```

### Точка інтеграції

```
Invoice::markAsPaid()
    │
    └── [TRIGGER] ExportToMedocAction::execute($invoice)
            │
            ├── MedocXmlGenerator::generate($invoice)
            │       └── Формування XML згідно специфікації MEDoc
            │
            └── Збереження / Відправка XML
```

### Дані для експорту

| Поле CRM | Призначення MEDoc |
|----------|-------------------|
| `invoice_number` | Номер документа |
| `invoice_date` | Дата документа |
| `ourCompany.name` | Продавець |
| `ourCompany.edrpou` | ЄДРПОУ продавця |
| `counterparty.name` | Покупець |
| `counterparty.edrpou_ipn` | ЄДРПОУ/ІПН покупця |
| `items[]` | Товари/послуги |
| `subtotal` | Сума без ПДВ |
| `vat_amount` | Сума ПДВ |
| `total` | Загальна сума |

---

## 7. Схема бази даних

### Основні таблиці

```
shops
├── id
├── name
├── type (horoshop, prom_ua)
├── api_credentials (JSON)
├── is_active
└── last_synced_at

orders
├── id
├── shop_id → shops.id
├── external_id
├── order_number
├── customer_name
├── customer_phone
├── customer_comment
├── status (new, processing, invoiced, paid)
├── our_company_id → our_companies.id
├── with_vat
├── subtotal
├── discount_total
├── total_amount
├── raw_data (JSON)
└── synced_at

order_items
├── id
├── order_id → orders.id
├── product_id → products.id
├── name
├── quantity
├── unit_price
├── discount_type
├── discount_value
└── subtotal

invoices
├── id
├── order_id → orders.id
├── our_company_id → our_companies.id
├── counterparty_id → counterparties.id
├── invoice_number
├── invoice_date
├── with_vat
├── subtotal
├── vat_amount
├── discount_type
├── discount_value
├── total
├── comment
├── is_paid
├── paid_at
├── excel_path
└── pdf_path

invoice_items
├── id
├── invoice_id → invoices.id
├── name
├── quantity
├── unit
├── unit_price
├── discount_type
├── discount_value
├── subtotal
├── discount_amount
└── total

our_companies
├── id
├── name
├── type (fop, tov)
├── tax_system (single_tax, vat)
├── edrpou
├── ipn
├── address
├── bank_name
├── bank_account
├── annual_limit
├── external_sales_amount
└── remaining_limit_override

counterparties
├── id
├── name
├── edrpou_ipn
├── phone
├── email
├── address
└── is_auto_created
```

### Зв'язки

```
Shop (1) ──────── (N) Order (1) ──────── (1) Invoice
                        │                      │
                        │                      │
                   (N) OrderItem          (N) InvoiceItem
                        │                      │
                   (N) Product            OurCompany
                                          Counterparty
```

---

## 8. Filament ресурси

### OrderResource

**Таблиця:**
- Фільтри: магазин, статус, компанія, ПДВ, без рахунку
- Bulk action: "Призначити компанію та ПДВ"
- Row action: "Створити рахунок" (якщо canCreateInvoice)

**Форма:**
- Секція "Компанія та ПДВ" (тільки для NEW)

### InvoiceResource

**Таблиця:**
- Фільтри: компанія, контрагент, ПДВ, оплачено
- Row actions: Завантажити Excel/PDF, "Оплачено"

**Форма:**
- Автозаповнення з замовлення (order_id в URL)
- Вибір компанії, контрагента
- Repeater для товарів
- Знижки на рівні рахунку

### ShopResource

**Форма:**
- Динамічні поля залежно від типу:
  - Horoshop: URL, Login, Password
  - Prom.ua: API Token

**Таблиця:**
- Actions: "Тест з'єднання", "Синхронізувати"

---

## 9. TODO: Відсутній функціонал

### Критичне

- [ ] MEDoc XML експорт
- [ ] MEDoc API інтеграція (якщо є)

### Бажане

- [ ] Скасування замовлення / повернення
- [ ] Часткова оплата
- [ ] Історія змін (audit log)
- [ ] Корегуючі рахунки
- [ ] Інтеграція з платіжними системами
- [ ] Відстеження доставки
- [ ] Внутрішні нотатки до замовлень
- [ ] Податкова звітність

---

## 10. Корисні посилання

### Документація API

- [Horoshop API - Авторизація](https://horoshop.atlassian.net/wiki/spaces/DOCS/pages/25296931)
- [Horoshop API - Замовлення](https://horoshop.atlassian.net/wiki/spaces/DOCS/pages/25296943)
- Prom.ua API - https://my.prom.ua/api/

### MEDoc

- Офіційний сайт: https://medoc.ua/
- Формат імпорту: XML (рекомендовано техпідтримкою)
