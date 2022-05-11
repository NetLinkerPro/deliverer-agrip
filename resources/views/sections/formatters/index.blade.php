@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title', __('deliverer-agrip::formatters.meta_title')  . ' // ' . config('app.url'))
@section('meta_description', __('deliverer-agrip::formatters.meta_description'))

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
            'name' => 'formatters_table',
            'row_url'=> '',
            'scope_api_url' => route('deliverer-agrip.formatters.scope'),
            'scope_api_params' => []
        ])
        <template slot="header">
            <h3>{{__('deliverer-agrip::formatters.formatter_list') }}</h3>
        </template>
        <tb-column name="name" label="{{__('deliverer-agrip::general.name') }}">
            <template slot-scope="col">
                @{{ col.data.name }}
            </template>
        </tb-column>
        <tb-column name="no_field_other" label="{{__('deliverer-agrip::formatters.configuration_source') }}">
            <template slot-scope="col">
                <div class="grid">
                    <div class="cell-inline mb-0">
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.identifier_type') }}: @{{ col.data.identifier_type }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.name_lang') }}: @{{ col.data.name_lang }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.name_type') }}: @{{ col.data.name_type }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.url_type') }}: @{{ col.data.url_type }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.price_currency') }}: @{{ col.data.price_currency }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.price_type') }}: @{{ col.data.price_type }}</small></div>
                    </div>
                    <div class="cell-inline mb-0">
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.tax_country') }}: @{{ col.data.tax_country }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.stock_type') }}: @{{ col.data.stock_type }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.category_lang') }}: @{{ col.data.category_lang }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.category_type') }}: @{{ col.data.category_type }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.description_lang') }}: @{{ col.data.description_lang }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.description_type') }}: @{{ col.data.description_type }}</small></div>
                    </div>
                    <div class="cell-inline mb-0">
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.image_lang') }}: @{{ col.data.image_lang }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.image_type') }}: @{{ col.data.image_type }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.attribute_lang') }}: @{{ col.data.attribute_lang }}</small></div>
                        <div><small class="cl-caption">{{__('deliverer-agrip::formatters.attribute_type') }}: @{{ col.data.attribute_type }}</small></div>
                    </div>
                </div>
            </template>
        </tb-column>
        <tb-column name="description" label="{{__('deliverer-agrip::general.description') }}">
            <template slot-scope="col">
                @{{ col.data.description }}
            </template>
        </tb-column>
           <tb-column name="no_field_options" label="{{ __('deliverer-agrip::general.options') }}">
                <template slot-scope="d">
                    <context-menu right boundary="table">
                        <button type="submit" slot="toggler" class="btn">
                            {{ __('deliverer-agrip::general.options') }}
                        </button>
                        <cm-button @click="AWES._store.commit('setData', {param: 'editFormatter', data: d.data}); AWES.emit('modal::edit-formatter:open')">
                            {{ __('deliverer-agrip::general.edit') }}
                        </cm-button>
                        <cm-button @click="AWES._store.commit('setData', {param: 'deleteFormatter', data: d.data}); AWES.emit('modal::delete-formatter:open');">
                            {{ __('deliverer-agrip::general.delete') }}
                        </cm-button>
                    </context-menu>
                </template>
            </tb-column>
        @endtable
    </div>
@endsection

@section('modals')

    {{--Add formatter--}}
    <modal-window name="form" class="modal_formbuilder" title="{{ __('deliverer-agrip::formatters.addition_formatter') }}">
        <form-builder name="add-formatter" url="{{ route('deliverer-agrip.formatters.store') }}" @sended="AWES.emit('content::formatters_table:update')"
                      send-text="{{ __('deliverer-agrip::general.add') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">
            <div class="section" v-if="AWES._store.state.forms['add-formatter']">

                <fb-input name="name" label="{{ __('deliverer-agrip::general.name') }}"></fb-input>
                <fb-textarea name="description" label="{{ __('deliverer-agrip::general.description') }}"></fb-textarea>

<div class="section">
    <h4>{{__('deliverer-agrip::formatters.configuration_source') }}</h4>
    <fb-input name="identifier_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.identifier_type') }}"></fb-input>
    <fb-input name="name_lang" :value="'pl'" label="{{ __('deliverer-agrip::formatters.name_lang') }}"></fb-input>
    <fb-input name="name_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.name_type') }}"></fb-input>
    <fb-input name="url_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.url_type') }}"></fb-input>
    <fb-input name="price_currency" :value="'pln'" label="{{ __('deliverer-agrip::formatters.price_currency') }}"></fb-input>
    <fb-input name="price_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.price_type') }}"></fb-input>
    <fb-input name="tax_country" :value="'pl'" label="{{ __('deliverer-agrip::formatters.tax_country') }}"></fb-input>
    <fb-input name="stock_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.stock_type') }}"></fb-input>
    <fb-input name="category_lang" :value="'pl'" label="{{ __('deliverer-agrip::formatters.category_lang') }}"></fb-input>
    <fb-input name="category_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.category_type') }}"></fb-input>
    <fb-input name="image_lang" :value="'pl'" label="{{ __('deliverer-agrip::formatters.image_lang') }}"></fb-input>
    <fb-input name="image_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.image_type') }}"></fb-input>
    <fb-input name="attribute_lang" :value="'pl'" label="{{ __('deliverer-agrip::formatters.attribute_lang') }}"></fb-input>
    <fb-input name="attribute_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.attribute_type') }}"></fb-input>
    <fb-input name="description_lang" :value="'pl'" label="{{ __('deliverer-agrip::formatters.description_lang') }}"></fb-input>
    <fb-input name="description_type" :value="'default'" label="{{ __('deliverer-agrip::formatters.description_type') }}"></fb-input>
</div>
            </div>
        </form-builder>
    </modal-window>

    {{--Edit formatter--}}
    <modal-window name="edit-formatter" class="modal_formbuilder" title="{{ __('deliverer-agrip::formatters.edition_formatter') }}">
        <form-builder method="PATCH" url="{{ route('deliverer-agrip.formatters.index') }}/{id}" store-data="editFormatter" @sended="AWES.emit('content::formatters_table:update')"
                      send-text="{{ __('deliverer-agrip::general.save') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">

            <fb-input name="name" label="{{ __('deliverer-agrip::general.name') }}"></fb-input>
            <fb-textarea name="description" label="{{ __('deliverer-agrip::general.description') }}"></fb-textarea>

            <div class="section">
                <h4>{{__('deliverer-agrip::formatters.configuration_source') }}</h4>
                <fb-input name="identifier_type" label="{{ __('deliverer-agrip::formatters.identifier_type') }}"></fb-input>
                <fb-input name="name_lang" label="{{ __('deliverer-agrip::formatters.name_lang') }}"></fb-input>
                <fb-input name="name_type" label="{{ __('deliverer-agrip::formatters.name_type') }}"></fb-input>
                <fb-input name="url_type" label="{{ __('deliverer-agrip::formatters.url_type') }}"></fb-input>
                <fb-input name="price_currency" label="{{ __('deliverer-agrip::formatters.price_currency') }}"></fb-input>
                <fb-input name="price_type" label="{{ __('deliverer-agrip::formatters.price_type') }}"></fb-input>
                <fb-input name="tax_country" label="{{ __('deliverer-agrip::formatters.tax_country') }}"></fb-input>
                <fb-input name="stock_type" label="{{ __('deliverer-agrip::formatters.stock_type') }}"></fb-input>
                <fb-input name="category_lang" label="{{ __('deliverer-agrip::formatters.category_lang') }}"></fb-input>
                <fb-input name="category_type" label="{{ __('deliverer-agrip::formatters.category_type') }}"></fb-input>
                <fb-input name="image_lang" label="{{ __('deliverer-agrip::formatters.image_lang') }}"></fb-input>
                <fb-input name="image_type" label="{{ __('deliverer-agrip::formatters.image_type') }}"></fb-input>
                <fb-input name="attribute_lang" label="{{ __('deliverer-agrip::formatters.attribute_lang') }}"></fb-input>
                <fb-input name="attribute_type" label="{{ __('deliverer-agrip::formatters.attribute_type') }}"></fb-input>
                <fb-input name="description_lang" label="{{ __('deliverer-agrip::formatters.description_lang') }}"></fb-input>
                <fb-input name="description_type" label="{{ __('deliverer-agrip::formatters.description_type') }}"></fb-input>
            </div>
        </form-builder>
    </modal-window>

    {{--Delete formatter--}}
    <modal-window name="delete-formatter" class="modal_formbuilder" title="{{ __('deliverer-agrip::formatters.are_you_sure_delete_formatter') }}">
        <form-builder name="delete-formatter" method="DELETE" url="{{ route('deliverer-agrip.formatters.index') }}/{id}" store-data="deleteFormatter" @sended="AWES.emit('content::formatters_table:update')"
                      send-text="{{ __('deliverer-agrip::general.yes') }}"
                      cancel-text="{{ __('deliverer-agrip::general.no') }}"
                      disabled-dialog>
            <template slot-scope="block">

                <!-- Fix enable button yes for delete -->
                <input type="hidden" name="isEdited" :value="AWES._store.state.forms['delete-formatter']['isEdited'] = true"/>
            </template>
        </form-builder>
    </modal-window>

@endsection
