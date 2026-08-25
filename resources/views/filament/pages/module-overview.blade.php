@php($group = $this->getModuleGroup())

<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        <x-filament::section :icon="$group->getIcon()" class="xl:col-span-2">
            <x-slot name="heading">{{ $group->getLabel() }} workspace</x-slot>
            <x-slot name="description">This module is registered in the required admin navigation order and is ready for its operational features.</x-slot>
            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    The module foundation is active. Its permissions, workflows, reports and integrations will be added here without changing the approved dashboard order.
                </p>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        <p class="font-semibold text-gray-950 dark:text-white">Navigation</p>
                        <p class="mt-1 text-xs">Registered and ordered</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        <p class="font-semibold text-gray-950 dark:text-white">Access control</p>
                        <p class="mt-1 text-xs">Admin-panel protected</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        <p class="font-semibold text-gray-950 dark:text-white">Status</p>
                        <p class="mt-1 text-xs">Foundation ready</p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Implementation status</x-slot>

            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-success-500"></span>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Dashboard entry active</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-warning-500"></span>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Feature development in progress</span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
