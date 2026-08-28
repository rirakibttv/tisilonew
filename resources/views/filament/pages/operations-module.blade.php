@php
    $config = $this->configuration();
    $records = $this->records();
    $metrics = $this->metrics();
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Total records', $metrics['total'], 'heroicon-o-rectangle-stack', 'text-primary-600 bg-primary-50'],
            ['Active / complete', $metrics['active'], 'heroicon-o-check-circle', 'text-success-600 bg-success-50'],
            ['Pending / draft', $metrics['pending'], 'heroicon-o-clock', 'text-warning-600 bg-warning-50'],
            ['Recorded amount', '৳'.number_format($metrics['amount'], 2), 'heroicon-o-banknotes', 'text-info-600 bg-info-50'],
        ] as [$label, $value, $icon, $tone])
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center gap-4">
                    <span @class(['grid size-11 place-items-center rounded-xl', $tone])>@svg($icon, 'size-6')</span>
                    <div>
                        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $value }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(19rem,.75fr)_minmax(0,1.5fr)]">
        <x-filament::section>
            <x-slot name="heading">{{ $editingId ? 'Edit record' : 'Add record' }}</x-slot>
            <x-slot name="description">এই তথ্য production database-এ সংরক্ষিত হবে। ব্যক্তিগত যোগাযোগ, নোট ও লেনদেনের তথ্য Git source snapshot-এ প্রকাশ করা হবে না।</x-slot>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Name / title <span class="text-danger-500">*</span></label>
                    <input wire:model="recordTitle" type="text" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5" placeholder="{{ $config['label'] }}">
                    @error('recordTitle') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Reference / code</label>
                        <input wire:model="reference" type="text" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5" placeholder="Unique reference">
                        @error('reference') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Status</label>
                        <select wire:model="status" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5">
                            @foreach ($config['statuses'] as $option)
                                <option value="{{ $option }}">{{ str($option)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Amount (৳)</label>
                        <input wire:model="amount" type="number" step="0.01" min="0" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5" placeholder="0.00">
                        @error('amount') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Contact</label>
                        <input wire:model="contact" type="text" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5" placeholder="Phone or email">
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Schedule / record date</label>
                    <input wire:model="scheduledAt" type="datetime-local" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5">
                    @error('scheduledAt') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Notes / details</label>
                    <textarea wire:model="notes" rows="4" class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5" placeholder="Operational details"></textarea>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button type="submit" icon="heroicon-o-check">{{ $editingId ? 'Update' : 'Save' }}</x-filament::button>
                    @if ($editingId)
                        <x-filament::button type="button" color="gray" wire:click="resetForm">Cancel</x-filament::button>
                    @endif
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $config['label'] }} records</x-slot>
            <x-slot name="description">সর্বশেষ ১০০টি রেকর্ড; সম্পাদনা ও মুছে ফেলার সুবিধাসহ।</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-white/10">
                        <tr>
                            <th class="px-3 py-3">Title / reference</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Amount</th>
                            <th class="px-3 py-3">Contact / date</th>
                            <th class="px-3 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($records as $record)
                            <tr wire:key="operation-{{ $record->id }}">
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $record->title }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $record->reference ?: 'No reference' }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ str($record->status)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="px-3 py-4 font-semibold text-gray-800 dark:text-gray-200">{{ $record->amount !== null ? '৳'.number_format((float) $record->amount, 2) : '—' }}</td>
                                <td class="px-3 py-4">
                                    <p class="text-gray-700 dark:text-gray-200">{{ $record->contact ?: '—' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $record->scheduled_at?->format('d M Y, h:i A') ?: $record->created_at->format('d M Y') }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-filament::button size="xs" color="gray" wire:click="edit({{ $record->id }})" icon="heroicon-o-pencil-square">Edit</x-filament::button>
                                        <x-filament::button size="xs" color="danger" wire:click="delete({{ $record->id }})" wire:confirm="এই রেকর্ডটি মুছে ফেলবেন?" icon="heroicon-o-trash">Delete</x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-14 text-center text-gray-500">এখনো কোনো রেকর্ড নেই। বাম পাশের ফর্ম থেকে প্রথম রেকর্ড যোগ করুন।</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
