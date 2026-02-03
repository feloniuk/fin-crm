<div class="space-y-4">
    @if($items->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Товар</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Кількість</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Ціна</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Знижка</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Сума</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                {{ number_format($item->quantity, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                {{ number_format($item->unit_price, 2, ',', ' ') }} грн
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                @if($item->discount_type === 'percent')
                                    {{ number_format($item->discount_value, 0) }}%
                                @elseif($item->discount_type === 'fixed')
                                    {{ number_format($item->discount_value, 2, ',', ' ') }} грн
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ number_format($item->total, 2, ',', ' ') }} грн
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800 font-medium">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">Всього:</td>
                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($items->sum('total'), 2, ',', ' ') }} грн
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            Товари не знайдено
        </div>
    @endif
</div>
