@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title', __('deliverer-agrip::settings.meta_title')  . ' // ' . config('app.url'))
@section('meta_description', __('deliverer-agrip::settings.meta_description'))

@push('head')
    @include('deliverer-agrip::integration.favicons')
    @include('deliverer-agrip::integration.ga')
@endpush

@section('create_button')

@endsection

@section('content')
    <div class="filter">
        <div class="grid grid-align-center grid-justify-between grid-justify-center--mlg">
            <div class="cell-inline cell-1-1--mlg">

            </div>
            <div class="cell-inline">
                @include('deliverer-agrip::sections.partials.menu-manage')
            </div>
        </div>
    </div>
    @jobstatuses([
    'queues' => ['auto_deliverer_store_add_products',
    'auto_deliverer_store_add_and_update_my_prices_stocks',
    'auto_deliverer_store_update_products',
    'auto_deliverer_store_add_and_update_shop_products']
    ])

    <div class="section">

        <form-builder method="PATCH" url="{{ route('deliverer-agrip.settings.index') }}" store-data="editSetting"
                      send-text="{{ __('wide-store::general.save') }}"
                      cancel-text="{{ __('wide-store::general.cancel') }}" disabled-dialog>

           <div class="section">
               <h3>{{__('deliverer-agrip::settings.add_and_update_products') }}</h3>
{{--               <fb-input name="url_1" label="{{ __('deliverer-agrip::settings.url_1') }}" autocomplete="new-password"></fb-input>--}}
{{--               <fb-input name="url_2" label="{{ __('deliverer-agrip::settings.url_2') }}" autocomplete="new-password"></fb-input>--}}
               <fb-input name="login" label="{{ __('deliverer-agrip::settings.login') }}" autocomplete="new-password"></fb-input>
               <fb-input type="password" name="pass" label="{{ __('deliverer-agrip::settings.password') }}" autocomplete="new-password"></fb-input>
{{--               <fb-input name="login2" label="{{ __('deliverer-agrip::settings.login') }}" autocomplete="new-password"></fb-input>--}}
{{--               <fb-input type="password" name="pass2" label="{{ __('deliverer-agrip::settings.password2') }}" autocomplete="new-password"></fb-input>--}}
{{--                              <fb-input name="token" label="{{ __('deliverer-agrip::settings.token') }}" autocomplete="new-password"></fb-input>--}}
               <fb-switcher name="debug" label="{{ __('deliverer-agrip::settings.debug') }}"></fb-switcher>
               <fb-input type="number" value="800" name="max_width_images_disk" label="{{ __('deliverer-agrip::settings.max_width_images_disk') }}"></fb-input>
               <fb-input name="from_add_product" label="{{ __('deliverer-agrip::settings.from_add_product') }}" autocomplete="new-password"></fb-input>
                <small class="cl-caption">{{ __('deliverer-agrip::settings.hint_from_add_product', ['format' => \NetLinker\DelivererAgrip\Sections\Sources\Repositories\ProductRepository::FROM_ADD_PRODUCT_FORMAT]) }}</small>
           </div>

            <div class="section">
                <h3>{{__('deliverer-agrip::settings.update_products') }}</h3>
                <fb-checkbox name="update_exist_images_disk" label="{{__('deliverer-agrip::settings.update_exist_images_disk')}}"></fb-checkbox>
            </div>

            <div class="section">
                <h3>{{__('deliverer-agrip::settings.add_products_cron') }}</h3>
                <fb-input name="add_products_cron" label="{{ __('deliverer-agrip::settings.add_products_cron') }}"></fb-input>
            </div>

            <div class="section">
                <h3>{{__('deliverer-agrip::settings.owner_supervisor_uuid') }}</h3>

                <fb-select name="owner_supervisor_uuid" label="{{ __('deliverer-agrip::settings.owner_supervisor_uuid') }}"
                           :select-options="AWES._store.state.editSettingOwners"
                           options-value="uuid" options-name="name" :multiple="false" placeholder-text=" "></fb-select>
            </div>

            <div class="section">
                <h3>{{__('deliverer-agrip::settings.testing') }}</h3>
                <fb-input type="number" name="limit_products" label="{{__('deliverer-agrip::settings.limit_products')}}"></fb-input>
            </div>

        </form-builder>
    </div>
    <script type="application/javascript">
        document.addEventListener('DOMContentLoaded', (event) => {
            var value = @JSON($value);
            var owners = @JSON($owners);
            AWES._store.commit('setData', {param: 'editSetting', data: value});
            AWES._store.commit('setData', {param: 'editSettingOwners', data: owners});
        })
    </script>
@endsection

@section('modals')

@endsection
