<?php

return [
    // Success messages
    'success' => [
        'created' => ':Model успішно створено.',
        'updated' => ':Model успішно оновлено.',
        'deleted' => ':Model успішно видалено.',
        'saved' => 'Дані успішно збережено.',
        'synced' => 'Замовлення успішно синхронізовано.',
        'test_connection_success' => 'З\'єднання з магазином успішне.',
        'invoice_created' => 'Рахунок успішно створено.',
        'invoice_paid' => 'Рахунок позначено як оплачено.',
        'documents_generated' => 'Документи успішно згенеровано.',
    ],

    // Error messages
    'error' => [
        'not_found' => ':Model не знайдено.',
        'unauthorized' => 'Вам не дозволено виконувати цю дію.',
        'forbidden' => 'Дія заборонена.',
        'validation_failed' => 'Помилка валідації. Перевірте дані.',
        'sync_failed' => 'Помилка синхронізації: :error',
        'test_connection_failed' => 'Помилка з\'єднання: :error',
        'limit_exceeded' => 'Перевищено ліміт для :company.',
        'limit_warning' => 'Увага: Використано :percent% ліміту для :company.',
    ],

    // Validation messages
    'validation' => [
        'required' => 'Поле ":attribute" обов\'язкове.',
        'email' => 'Поле ":attribute" повинне містити правильну email адресу.',
        'unique' => 'Значення ":attribute" вже існує.',
        'numeric' => 'Поле ":attribute" повинне бути числом.',
        'min' => 'Поле ":attribute" повинне мати мінімум :min символів.',
        'max' => 'Поле ":attribute" не повинне перевищувати :max символів.',
        'regex' => 'Формат поля ":attribute" невірний.',
        'confirmed' => 'Підтвердження ":attribute" не збігається.',
        'date' => 'Поле ":attribute" не є правильною датою.',
        'digits' => 'Поле ":attribute" повинне містити :digits цифр.',
    ],

    // Action messages
    'action' => [
        'create' => 'Створити',
        'edit' => 'Редагувати',
        'view' => 'Перегляд',
        'delete' => 'Видалити',
        'save' => 'Зберегти',
        'cancel' => 'Скасувати',
        'back' => 'Назад',
        'close' => 'Закрити',
        'download' => 'Завантажити',
        'export' => 'Експортувати',
        'import' => 'Імпортувати',
        'sync' => 'Синхронізувати',
        'test' => 'Тест',
        'search' => 'Пошук',
        'filter' => 'Фільтр',
        'reset' => 'Скинути',
    ],

    // Filament form messages
    'filament' => [
        'unsaved_changes' => 'У вас є незбережені зміни.',
        'confirm_delete' => 'Ви впевнені, що хочете видалити :model?',
        'bulk_action' => 'Виконувати дію над :count записами?',
        'no_results' => 'Записів не знайдено.',
        'empty_state' => 'Жодних записів.',
        'loading' => 'Завантаження...',
        'creating' => 'Створення...',
        'updating' => 'Оновлення...',
        'deleting' => 'Видалення...',
    ],

    // Invoice specific messages
    'invoice' => [
        'created_success' => 'Рахунок :number успішно створено.',
        'updated_success' => 'Рахунок :number успішно оновлено.',
        'marked_as_paid' => 'Рахунок :number позначено як оплачено.',
        'marked_as_unpaid' => 'Рахунок :number позначено як неоплачено.',
        'documents_ready' => 'Excel та PDF документи готові до завантаження.',
        'no_items' => 'Додайте позиції до рахунку.',
        'invalid_company' => 'Виберіть компанію для рахунку.',
        'invalid_counterparty' => 'Виберіть контрагента для рахунку.',
    ],

    // Order specific messages
    'order' => [
        'synced' => 'Замовлення успішно синхронізовано.',
        'cannot_create_invoice' => 'Для замовлення вже створено рахунок.',
        'no_items' => 'Замовлення не містить позицій.',
    ],

    // Permission messages
    'permission' => [
        'cannot_view' => 'Ви не можете переглядати цей ресурс.',
        'cannot_create' => 'Ви не можете створювати цей ресурс.',
        'cannot_update' => 'Ви не можете оновлювати цей ресурс.',
        'cannot_delete' => 'Ви не можете видаляти цей ресурс.',
        'manager_limited' => 'Менеджери можуть тільки переглядати дані.',
    ],

    // Number formatting
    'currency' => [
        'symbol' => 'грн',
        'decimal_separator' => ',',
        'thousands_separator' => ' ',
    ],

    // Date formatting
    'date' => [
        'format' => 'd.m.Y',
        'format_full' => 'd.m.Y H:i',
    ],
];
