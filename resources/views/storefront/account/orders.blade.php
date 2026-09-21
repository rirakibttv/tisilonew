@extends('storefront.account.layout')

@php
    $titles = ['to-pay' => 'To Pay', 'to-ship' => 'To Ship', 'to-receive' => 'To Receive', 'all' => 'All Order'];
@endphp
@section('title', ($titles[$filter] ?? 'Orders').' — Tisilo')

@section('account-content')
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">Order Info</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $titles[$filter] }}</h1>
            <p class="mt-2 text-sm text-slate-500">আপনার অর্ডারের বর্তমান অবস্থা এবং পেমেন্ট তথ্য দেখুন।</p>
        </div>
        @include('storefront.account.partials.order-list', ['orders' => $orders])
    </div>

    @if($orders->hasPages())
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif
@endsection
