@php
    $stats = $this->getCompanyStats();
@endphp

<x-filament-panels::page>
    <!-- Company Stats Section -->
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            💼 Статистика компаній
        </h2>
        <div class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
            @forelse($stats as $company)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                        {{ $company['name'] }}
                    </h3>

                    <!-- Progress bar -->
                    <div class="mb-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-full rounded-full transition-all
                            @if($company['percent'] >= 90) bg-red-500
                            @elseif($company['percent'] >= 70) bg-yellow-500
                            @else bg-green-500
                            @endif"
                            style="width: {{ min($company['percent'], 100) }}%"
                        ></div>
                    </div>

                    <!-- Stats -->
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Виписано:</span>
                            <span class="font-semibold">{{ number_format($company['invoiced'], 2, ',', ' ') }} грн</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Оплачено:</span>
                            <span class="font-semibold">{{ number_format($company['paid'], 2, ',', ' ') }} грн</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                            <span>Ліміт:</span>
                            <span class="font-semibold">{{ number_format($company['limit'], 2, ',', ' ') }} грн</span>
                        </div>
                        <div class="flex justify-between pt-1 border-t border-gray-200 dark:border-gray-700">
                            <span class="font-semibold {{ $company['percent'] >= 90 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                Залишок:
                            </span>
                            <span class="font-semibold {{ $company['percent'] >= 90 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ number_format($company['remaining'], 2, ',', ' ') }} грн
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        ℹ️ Немає ФОП компаній з лімітами
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                📋 Реєстр рахунків
            </h2>
        </div>

        <div class="px-6 py-4">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
