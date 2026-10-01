@extends('layouts.storefront')

@php
    $catalogPageTitle = $selectedCategory
        ? $selectedCategory->name.' — '.($generalSettings['site_name'] ?? 'Tisilo')
        : 'Shop — '.($generalSettings['site_name'] ?? 'Tisilo');
    $catalogMetaDescription = \App\Support\SeoMetadata::description(
        $selectedCategory?->description,
        $selectedCategory ? __('Browse :category products and order securely.', ['category' => $selectedCategory->name]) : null,
        __('Find products by category, brand and price, then order securely.'),
    );
@endphp
@section('title', $catalogPageTitle)
@section('meta_description', $catalogMetaDescription)
@section('canonical', $selectedCategory?->permalink ?? url()->current())

@section('content')
    @include('storefront.products._catalog-professional')
@endsection
