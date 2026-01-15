<?php

return [
    // ShopType enum
    'shop_type' => [
        'horoshop' => 'Horoshop',
        'prom_ua' => 'Prom.ua',
    ],

    // CompanyType enum
    'company_type' => [
        'fop' => 'ФОП (Фізична особа-підприємець)',
        'tov' => 'ТОВ (Товариство з обмеженою відповідальністю)',
    ],

    // TaxSystem enum
    'tax_system' => [
        'single_tax' => 'Єдиний податок',
        'vat' => 'ПДВ',
    ],

    // OrderStatus enum
    'order_status' => [
        'new' => 'Нове',
        'processing' => 'На обробці',
        'invoiced' => 'Виписано',
        'paid' => 'Оплачено',
    ],

    // DiscountType enum
    'discount_type' => [
        'none' => 'Без знижки',
        'percent' => 'Відсоток',
        'fixed' => 'Фіксована сума',
    ],

    // UserRole enum
    'user_role' => [
        'admin' => 'Адміністратор',
        'manager' => 'Менеджер',
    ],
];
