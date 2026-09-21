@extends('storefront.account.layout')

@section('title', 'Address Book — Tisilo')

@section('account-content')
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5 sm:p-6">
                <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">Manage My Account</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Address Book</h1>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                @forelse($addresses as $address)
                    <article class="relative rounded-2xl border p-4 {{ $address->is_default ? 'border-violet-400 bg-violet-50/50' : 'border-slate-200' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="font-black text-slate-950">{{ $address->label }}</p>@if($address->is_default)<span class="mt-1 inline-flex rounded-full bg-violet-600 px-2 py-0.5 text-[10px] font-black text-white">DEFAULT</span>@endif</div>
                            @svg('heroicon-o-map-pin', 'size-5 text-violet-500')
                        </div>
                        <p class="mt-4 text-sm font-bold text-slate-800">{{ $address->recipient_name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $address->phone }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ $address->address_line }}, {{ $address->thana ? $address->thana.', ' : '' }}{{ $address->district }}{{ $address->postal_code ? ' - '.$address->postal_code : '' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @unless($address->is_default)
                                <form method="POST" action="{{ route('store.account.addresses.default', $address) }}">@csrf @method('PATCH')<button class="rounded-lg bg-violet-100 px-3 py-2 text-xs font-black text-violet-700">Set default</button></form>
                            @endunless
                            <form method="POST" action="{{ route('store.account.addresses.destroy', $address) }}" onsubmit="return confirm('ঠিকানাটি মুছে দিতে চান?')">@csrf @method('DELETE')<button class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-black text-rose-600">Delete</button></form>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full py-14 text-center">@svg('heroicon-o-map-pin', 'mx-auto size-12 text-slate-300')<p class="mt-4 font-black text-slate-800">কোনো ঠিকানা সংরক্ষিত নেই</p></div>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-black text-slate-950">Add new address</h2>
            <form method="POST" action="{{ route('store.account.addresses.store') }}" class="mt-5 space-y-4">
                @csrf
                @foreach([['label', 'Label', 'Home / Office'], ['recipient_name', 'Recipient name', 'Full name'], ['phone', 'Mobile number', '01XXXXXXXXX'], ['district', 'District', 'জেলার নাম'], ['thana', 'Thana / Upazila', 'থানা বা উপজেলার নাম'], ['postal_code', 'Postal code', 'Optional']] as [$name, $label, $placeholder])
                    <label class="block text-sm font-bold text-slate-700">{{ $label }}
                        <input name="{{ $name }}" value="{{ old($name) }}" placeholder="{{ $placeholder }}" {{ in_array($name, ['label', 'recipient_name', 'phone', 'district']) ? 'required' : '' }} class="mt-1.5 w-full rounded-xl border-slate-200 text-sm focus:border-violet-500 focus:ring-violet-500">
                        @error($name)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <label class="block text-sm font-bold text-slate-700">Full address
                    <textarea name="address_line" rows="3" required placeholder="বাড়ি, রোড, এলাকা" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm focus:border-violet-500 focus:ring-violet-500">{{ old('address_line') }}</textarea>
                    @error('address_line')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </label>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500"> Make default address</label>
                <button class="w-full rounded-xl bg-violet-600 px-5 py-3 text-sm font-black text-white">Save address</button>
            </form>
        </div>
    </div>
@endsection
