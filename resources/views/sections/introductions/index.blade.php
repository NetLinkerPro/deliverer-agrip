@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title', __('deliverer-agrip::introductions.meta_title')  . ' // ' . config('app.name'))
@section('meta_description', __('deliverer-agrip::introductions.meta_description'))

@push('head')
    @include('deliverer-agrip::integration.favicons')
    @include('deliverer-agrip::integration.ga')
@endpush

@section('create_button')

@endsection

@section('content')
    <div class="grid">
        <div class="cell">

        </div>
    </div>
@endsection

@section('modals')


@endsection
