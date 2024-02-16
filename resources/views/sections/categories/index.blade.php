@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title', 'Kategorie // ' . config('app.url'))
@section('meta_description', 'Lista kategorii')

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
        'name' => 'categories_table',
        'row_url'=> '',
        'scope_api_url' => route('deliverer-agrip.categories.scope'),
        'scope_api_params' => []
        ])
        <template slot="header">
            <h3>{{__('deliverer-agrip::categories.category_list') }}</h3>
        </template>
        <tb-column name="name" label="{{__('deliverer-agrip::general.name') }}">
            <template slot-scope="col">
                @{{ col.data.name }}
            </template>
        </tb-column>
        <tb-column name="uri" label="URI">
            <template slot-scope="col">
                @{{ col.data.uri }}
            </template>
        </tb-column>
        <tb-column name="ctx" label="ctx">
            <template slot-scope="col">
                @{{ col.data.ctx }}
            </template>
        </tb-column>
        <tb-column name="ctr" label="ctr">
            <template slot-scope="col">
                @{{ col.data.ctr }}
            </template>
        </tb-column>
        <tb-column name="item_id" label="item_id">
            <template slot-scope="col">
                @{{ col.data.item_id }}
            </template>
        </tb-column>
        <tb-column name="table_number" label="table_number">
            <template slot-scope="col">
                @{{ col.data.table_number }}
            </template>
        </tb-column>
        <tb-column name="t" label="t">
            <template slot-scope="col">
                @{{ col.data.t }}
            </template>
        </tb-column>
        <tb-column name="no_field_other" label="Dane">
            <template slot-scope="col">
                <div class="grid">
                    <div class="cell-inline mb-0">
                        @{{ col.data.data }}
                    </div>
                </div>
            </template>
        </tb-column>
        <tb-column name="active" label="Aktywna">
            <template slot-scope="col">
                @{{ col.data.active }}
            </template>
        </tb-column>
        <tb-column name="no_field_options" label="{{ __('deliverer-agrip::general.options') }}">
            <template slot-scope="d">
                <context-menu right boundary="table">
                    <button type="submit" slot="toggler" class="btn">
                        {{ __('deliverer-agrip::general.options') }}
                    </button>
                    <cm-button
                            @click="AWES._store.commit('setData', {param: 'editCategory', data: d.data}); AWES.emit('modal::edit-category:open')">
                        {{ __('deliverer-agrip::general.edit') }}
                    </cm-button>
                    <cm-button
                            @click="AWES._store.commit('setData', {param: 'deleteCategory', data: d.data}); AWES.emit('modal::delete-category:open');">
                        {{ __('deliverer-agrip::general.delete') }}
                    </cm-button>
                </context-menu>
            </template>
        </tb-column>
        @endtable
    </div>
@endsection

@section('modals')

    {{--Add category--}}
    <modal-window name="form" class="modal_formbuilder"
                  title="{{ __('deliverer-agrip::categories.addition_category') }}">
        <form-builder name="add-category" url="{{ route('deliverer-agrip.categories.store') }}"
                      @sended="AWES.emit('content::categories_table:update')"
                      send-text="{{ __('deliverer-agrip::general.add') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">
            <div class="section" v-if="AWES._store.state.forms['add-category']">

                <fb-input name="name" label="{{ __('deliverer-agrip::general.name') }}"></fb-input>
                <fb-textarea name="description" label="{{ __('deliverer-agrip::general.description') }}"></fb-textarea>
                <fb-switcher name="active" label="Aktywna"></fb-switcher>
                <fb-input name="uri" label="URI"></fb-input>
                <fb-input name="ctx" label="ctx"></fb-input>
                <fb-input name="ctr" label="ctr"></fb-input>
                <fb-input name="item_id" label="item_id"></fb-input>
                <fb-input name="table_number" label="table_number"></fb-input>
                <fb-input name="t" label="t"></fb-input>
                <fb-textarea name="data" label="Dane" rows="50"></fb-textarea>
            </div>
        </form-builder>
    </modal-window>

    {{--Edit category--}}
    <modal-window name="edit-category" class="modal_formbuilder"
                  title="{{ __('deliverer-agrip::categories.edition_category') }}">
        <form-builder method="PATCH" url="{{ route('deliverer-agrip.categories.index') }}/{id}"
                      store-data="editCategory" @sended="AWES.emit('content::categories_table:update')"
                      send-text="{{ __('deliverer-agrip::general.save') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">

            <fb-input name="name" label="{{ __('deliverer-agrip::general.name') }}"></fb-input>
            <fb-textarea name="description" label="{{ __('deliverer-agrip::general.description') }}"></fb-textarea>
            <fb-switcher name="active" label="Aktywna"></fb-switcher>
            <fb-input name="uri" label="URI"></fb-input>
            <fb-input name="ctx" label="ctx"></fb-input>
            <fb-input name="ctr" label="ctr"></fb-input>
            <fb-input name="item_id" label="item_id"></fb-input>
            <fb-input name="table_number" label="table_number"></fb-input>
            <fb-input name="t" label="t"></fb-input>
            <fb-textarea name="data" label="Dane" rows="50"></fb-textarea>
        </form-builder>
    </modal-window>

    {{--Delete category--}}
    <modal-window name="delete-category" class="modal_formbuilder"
                  title="{{ __('deliverer-agrip::categories.are_you_sure_delete_category') }}">
        <form-builder name="delete-category" method="DELETE" url="{{ route('deliverer-agrip.categories.index') }}/{id}"
                      store-data="deleteCategory" @sended="AWES.emit('content::categories_table:update')"
                      send-text="{{ __('deliverer-agrip::general.yes') }}"
                      cancel-text="{{ __('deliverer-agrip::general.no') }}"
                      disabled-dialog>
            <template slot-scope="block">

                <!-- Fix enable button yes for delete -->
                <input type="hidden" name="isEdited"
                       :value="AWES._store.state.forms['delete-category']['isEdited'] = true"/>
            </template>
        </form-builder>
    </modal-window>

@endsection
