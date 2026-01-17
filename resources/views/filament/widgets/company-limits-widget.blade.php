@php
    $companies = $this->getCompaniesWithLimits();
@endphp

<div class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
    @forelse($companies as $data)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $data['name'] }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $data['type'] }}
                        @if($data['has_override'])
                            <span class="ml-1">🔒</span>
                        @endif
                    </p>
                </div>
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">
                    {{ $data['percent'] }}%
                </span>
            </div>

            <!-- Progress bar -->
            <div class="mb-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-full rounded-full transition-all duration-300
                    @if($data['colorClass'] === 'success') bg-green-500
                    @elseif($data['colorClass'] === 'warning') bg-yellow-500
                    @elseif($data['colorClass'] === 'info') bg-blue-500
                    @else bg-red-500
                    @endif"
                    style="width: {{ min($data['percent'], 100) }}%"
                >
                </div>
            </div>

            <!-- Stats -->
            <div class="space-y-1 text-xs">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Оплачено:</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($data['paid_in_system'], 2, ',', ' ') }} грн
                    </span>
                </div>
                @if($data['external_sales'] > 0)
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Поза системою:</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($data['external_sales'], 2, ',', ' ') }} грн
                        </span>
                    </div>
                @endif
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Ліміт:</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($data['limit'], 2, ',', ' ') }} грн
                    </span>
                </div>
                <div class="flex justify-between pt-1 border-t border-gray-200 dark:border-gray-700">
                    <span
                        class="font-semibold
                        @if($data['colorClass'] === 'success') text-green-600 dark:text-green-400
                        @elseif($data['colorClass'] === 'warning') text-yellow-600 dark:text-yellow-400
                        @elseif($data['colorClass'] === 'info') text-blue-600 dark:text-blue-400
                        @else text-red-600 dark:text-red-400
                        @endif"
                    >
                        Залишок:
                    </span>
                    <span
                        class="font-semibold
                        @if($data['colorClass'] === 'success') text-green-600 dark:text-green-400
                        @elseif($data['colorClass'] === 'warning') text-yellow-600 dark:text-yellow-400
                        @elseif($data['colorClass'] === 'info') text-blue-600 dark:text-blue-400
                        @else text-red-600 dark:text-red-400
                        @endif"
                    >
                        {{ number_format($data['remaining'], 2, ',', ' ') }} грн
                    </span>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-full rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                ℹ️ Немає компаній з лімітами
            </p>
        </div>
    @endforelse
</div>
