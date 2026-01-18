<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="submit">
                Зберегти налаштування
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
