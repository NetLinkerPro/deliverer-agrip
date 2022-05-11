@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title', __('deliverer-agrip::configurations.meta_title')  . ' // ' . config('app.url'))
@section('meta_description', __('deliverer-agrip::configurations.meta_description'))

@push('head')
    @include('deliverer-agrip::integration.favicons')
    @include('deliverer-agrip::integration.ga')
@endpush

@section('create_button')
    <button class="frame__header-add" @click="AWES.emit('modal::form:open')"><i class="icon icon-plus"></i></button>
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
    <div class="section">
        @table([
            'name' => 'configurations_table',
            'row_url'=> '',
            'scope_api_url' => route('deliverer-agrip.configurations.scope'),
            'scope_api_params' => []
        ])
        <template slot="header">
            <h3>{{__('deliverer-agrip::configurations.configuration_list') }}</h3>
        </template>
        <tb-column name="name" label="{{ __('deliverer-agrip::general.name') }}"></tb-column>
{{--        <tb-column name="url_1" label="{{ __('deliverer-agrip::configurations.url_1') }}"></tb-column>--}}
{{--        <tb-column name="url_2" label="{{ __('deliverer-agrip::configurations.url_2') }}"></tb-column>--}}
        <tb-column name="login" label="{{ __('deliverer-agrip::configurations.login') }}"></tb-column>
{{--        <tb-column name="login2" label="{{ __('deliverer-agrip::configurations.login2') }}"></tb-column>--}}
        <tb-column name="debug" label="{{ __('deliverer-agrip::configurations.debug') }}" sort>
            <template slot-scope="col">
                <span v-if="col.data.debug">{{ __('deliverer-agrip::general.yes') }}</span>
                <span v-else>{{ __('deliverer-agrip::general.no') }}</span>
            </template>
        </tb-column>
        <tb-column name="baselinker" label="{{ __('deliverer-agrip::configurations.baselinker') }}">
            <template slot-scope="col">
                <div v-if="col.data.baselinker && col.data.baselinker.id_category_products">
                    <div>
                        <small class="cl-caption">{{ __('deliverer-agrip::configurations.id_category_products_baselinker') }}</small>
                    </div>
                    @{{ col.data.baselinker.id_category_products }}
                </div>
            </template>
        </tb-column>
           <tb-column name="no_field_options" label="{{ __('deliverer-agrip::general.options') }}">
                <template slot-scope="d">
                    <context-menu right boundary="table">
                        <button type="submit" slot="toggler" class="btn">
                            {{ __('deliverer-agrip::general.options') }}
                        </button>
                        <cm-button @click="AWES._store.commit('setData', {param: 'editConfiguration', data: d.data}); AWES.emit('modal::edit-configuration:open')">
                            {{ __('deliverer-agrip::general.edit') }}
                        </cm-button>
                        <cm-button @click="AWES._store.commit('setData', {param: 'deleteConfiguration', data: d.data}); AWES.emit('modal::delete-configuration:open');">
                            {{ __('deliverer-agrip::general.delete') }}
                        </cm-button>
                    </context-menu>
                </template>
            </tb-column>
        @endtable
    </div>
@endsection

@section('modals')

    {{--Add configuration--}}
    <modal-window name="form" class="modal_formbuilder" title="{{ __('deliverer-agrip::configurations.addition_configuration') }}">
        <form-builder name="add-configuration" url="{{ route('deliverer-agrip.configurations.store') }}" @sended="AWES.emit('content::configurations_table:update')"
                      send-text="{{ __('deliverer-agrip::general.add') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">
            <div class="section" v-if="AWES._store.state.forms['add-configuration']">

                <fb-input name="name" label="{{ __('deliverer-agrip::general.name') }}"></fb-input>
{{--                <fb-input name="url_1"  label="{{ __('deliverer-agrip::configurations.url_1') }}" autocomplete="new-password" ></fb-input>--}}
{{--                <fb-input name="url_2"  label="{{ __('deliverer-agrip::configurations.url_2') }}" autocomplete="new-password" ></fb-input>--}}
                <fb-input name="login" label="{{ __('deliverer-agrip::configurations.login') }}" autocomplete="new-password"></fb-input>
                <fb-input type="password" name="pass" label="{{ __('deliverer-agrip::configurations.password') }}" autocomplete="new-password"></fb-input>
{{--                <fb-input name="login2" label="{{ __('deliverer-agrip::configurations.login2') }}" autocomplete="new-password"></fb-input>--}}
{{--                <fb-input type="password" name="pass2" label="{{ __('deliverer-agrip::configurations.password2') }}" autocomplete="new-password"></fb-input>--}}

{{--                                <fb-input name="token" label="{{ __('deliverer-agrip::configurations.token') }}" autocomplete="new-password"></fb-input>--}}
                <fb-switcher name="debug" label="{{ __('deliverer-agrip::configurations.debug') }}"></fb-switcher>
                <fb-input name="baselinker.api_token" label="{{ __('deliverer-agrip::configurations.api_token_baselinker') }}"></fb-input>
                <fb-input name="baselinker.id_category_products" label="{{ __('deliverer-agrip::configurations.id_category_products_baselinker') }}"></fb-input>
            </div>
        </form-builder>
    </modal-window>

    {{--Edit configuration--}}
    <modal-window name="edit-configuration" class="modal_formbuilder" title="{{ __('deliverer-agrip::configurations.edition_configuration') }}">
        <form-builder method="PATCH" url="{{ route('deliverer-agrip.configurations.index') }}/{id}" store-data="editConfiguration" @sended="AWES.emit('content::configurations_table:update')"
                      send-text="{{ __('deliverer-agrip::general.save') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">

            <fb-input name="name" label="{{ __('deliverer-agrip::general.name') }}"></fb-input>
{{--            <fb-input name="url_1" label="{{ __('deliverer-agrip::configurations.url_1') }}" autocomplete="new-password"></fb-input>--}}
{{--            <fb-input name="url_2" label="{{ __('deliverer-agrip::configurations.url_2') }}" autocomplete="new-password"></fb-input>--}}
            <fb-input name="login" label="{{ __('deliverer-agrip::configurations.login') }}" autocomplete="new-password"></fb-input>
            <fb-input type="password" name="pass" label="{{ __('deliverer-agrip::configurations.password') }}" autocomplete="new-password"></fb-input>
{{--            <fb-input name="login2" label="{{ __('deliverer-agrip::configurations.login2') }}" autocomplete="new-password"></fb-input>--}}
{{--            <fb-input type="password" name="pass2" label="{{ __('deliverer-agrip::configurations.password2') }}" autocomplete="new-password"></fb-input>--}}

{{--                        <fb-input name="token" label="{{ __('deliverer-agrip::configurations.token') }}" autocomplete="new-password"></fb-input>--}}
            <fb-switcher name="debug" label="{{ __('deliverer-agrip::configurations.debug') }}"></fb-switcher>
            <fb-input name="baselinker.api_token" label="{{ __('deliverer-agrip::configurations.api_token_baselinker') }}"></fb-input>
            <fb-input name="baselinker.id_category_products" label="{{ __('deliverer-agrip::configurations.id_category_products_baselinker') }}"></fb-input>
        </form-builder>
    </modal-window>

    {{--Delete configuration--}}
    <modal-window name="delete-configuration" class="modal_formbuilder" title="{{ __('deliverer-agrip::configurations.are_you_sure_delete_configuration') }}">
        <form-builder name="delete-configuration" method="DELETE" url="{{ route('deliverer-agrip.configurations.index') }}/{id}" store-data="deleteConfiguration" @sended="AWES.emit('content::configurations_table:update')"
                      send-text="{{ __('deliverer-agrip::general.yes') }}"
                      cancel-text="{{ __('deliverer-agrip::general.no') }}"
                      disabled-dialog>
            <template slot-scope="block">

                <!-- Fix enable button yes for delete -->
                <input type="hidden" name="isEdited" :value="AWES._store.state.forms['delete-configuration']['isEdited'] = true"/>
            </template>
        </form-builder>
    </modal-window>

@endsection
