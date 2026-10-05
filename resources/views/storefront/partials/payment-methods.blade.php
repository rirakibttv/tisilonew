@php
    $selectedPaymentMethod = old('payment_method', $defaultPaymentMethod);
@endphp

<fieldset class="space-y-3">
    <legend class="mb-3 text-sm font-black text-slate-900">{{ __('Select Payment Method') }} <span class="text-rose-600">*</span></legend>
    @forelse($paymentMethods as $method => $details)
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 bg-white p-4 transition has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
            <input
                type="radio"
                name="payment_method"
                value="{{ $method }}"
                @checked($selectedPaymentMethod === $method)
                required
                class="size-5 shrink-0 accent-orange-500"
            >
            <span>
                <span class="block font-black text-slate-900">{{ $details['label'] }}</span>
                <span class="mt-1 block text-sm font-normal leading-5 text-slate-500">{{ $details['description'] }}</span>
            </span>
        </label>
    @empty
        <p class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">{{ __('No payment method is currently available.') }}</p>
    @endforelse
</fieldset>
