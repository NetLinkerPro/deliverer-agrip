@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title',  __('deliverer-agrip::dashboard.meta_title') . ' - ' .config('app.name') )
@section('meta_description', __('deliverer-agrip::dashboard.meta_description'))

@push('head')
    @include('deliverer-agrip::integration.favicons')
    @include('deliverer-agrip::integration.ga')
@endpush

@section('content')


@endsection
