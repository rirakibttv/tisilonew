<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-indigo-600">স্বাগতম, {{ $seller?->name }}</p>
            <h2 class="mt-2 text-2xl font-black text-gray-950">{{ $vendor?->name ?? 'আপনার শপ' }}</h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                আপনার seller registration সম্পন্ন হয়েছে। Tisilo team তথ্য যাচাই করার পর shop অনুমোদন করবে।
            </p>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Application Status</p>
            <div class="mt-3 inline-flex rounded-full bg-amber-100 px-4 py-2 text-sm font-black text-amber-800">
                {{ $vendor?->status?->label() ?? 'Pending Review' }}
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">ইমেইল</dt><dd class="font-semibold text-gray-900">{{ $seller?->email }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">মোবাইল</dt><dd class="font-semibold text-gray-900">{{ $seller?->phone }}</dd></div>
            </dl>
        </section>
    </div>
</x-filament-panels::page>
