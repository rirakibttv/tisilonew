@extends('layouts.storefront')

@php
    $catalogPageTitle = $selectedCategory
        ? $selectedCategory->name.' — '.($generalSettings['site_name'] ?? 'Tisilo')
        : 'Shop — '.($generalSettings['site_name'] ?? 'Tisilo');
    $catalogMetaDescription = \App\Support\SeoMetadata::description(
        $selectedCategory?->description,
        $selectedCategory ? $selectedCategory->name.' ক্যাটাগরির পণ্য দেখুন এবং নিরাপদে অর্ডার করুন।' : null,
        'ক্যাটাগরি, ব্র্যান্ড ও মূল্য অনুযায়ী পণ্য খুঁজুন এবং নিরাপদে অর্ডার করুন।',
    );
@endphp
@section('title', $catalogPageTitle)
@section('meta_description', $catalogMetaDescription)
@section('canonical', $selectedCategory?->permalink ?? url()->current())

@section('content')
    @include('storefront.products._catalog-professional')
@endsection
