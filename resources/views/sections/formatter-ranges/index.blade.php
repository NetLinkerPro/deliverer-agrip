@extends('deliverer-agrip::vendor.indigo-layout.main')

@section('meta_title', __('deliverer-agrip::formatter-ranges.meta_title')  . ' // ' . config('app.url'))
@section('meta_description', __('deliverer-agrip::formatter-ranges.meta_description'))

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
            'name' => 'formatter_ranges_table',
            'row_url'=> '',
            'scope_api_url' => route('deliverer-agrip.formatter_ranges.scope'),
            'scope_api_params' => []
        ])
        <template slot="header">
            <h3>{{__('deliverer-agrip::formatter-ranges.formatter_range_list') }}</h3>
        </template>
        <tb-column name="no_field_formatter_uuid" label="{{__('deliverer-agrip::formatter-ranges.formatter') }}">
            <template slot-scope="col">
                @{{ col.data.formatter.name }}
            </template>
        </tb-column>
        <tb-column name="no_field_range" label="{{__('deliverer-agrip::formatter-ranges.range') }}">
            <template slot-scope="col">
                @{{ col.data.range_object.name }}
            </template>
        </tb-column>
        <tb-column name="no_field_actions" label="{{__('deliverer-agrip::formatter-ranges.actions') }}">
            <template slot-scope="col">

                    <div v-for="(action,index) in col.data.actions">
                    <small class="cl-caption" :class="{'mt-5': index}" style="display:block;">@{{ action.name }}</small>
                    </div>
            </template>
        </tb-column>
            <tb-column name="no_field_options" label="{{ __('deliverer-agrip::general.options') }}">
                <template slot-scope="d">
                    <context-menu right boundary="table">
                        <button type="submit" slot="toggler" class="btn">
                            {{ __('deliverer-agrip::general.options') }}
                        </button>
                        <cm-button @click="AWES._store.commit('setData', {param: 'editFormatterRange', data: d.data}); AWES.emit('modal::edit-formatter-range:open')">
                            {{ __('deliverer-agrip::general.edit') }}
                        </cm-button>
                        <cm-button @click="AWES._store.commit('setData', {param: 'deleteFormatterRange', data: d.data}); AWES.emit('modal::delete-formatter-range:open');">
                            {{ __('deliverer-agrip::general.delete') }}
                        </cm-button>
                    </context-menu>
                </template>
            </tb-column>
        @endtable
    </div>
@endsection

@section('modals')

    {{--Add formatter range--}}
    <modal-window name="form" class="modal_formbuilder" title="{{ __('deliverer-agrip::formatter-ranges.addition_formatter_range') }}">
        <form-builder name="add-formatter-range" url="{{ route('deliverer-agrip.formatter_ranges.store') }}" @sended="AWES.emit('content::formatter_ranges_table:update')"
                      send-text="{{ __('deliverer-agrip::general.add') }}" disabled-dialog
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">

            <div v-if="AWES._store.state.forms['add-formatter-range']">


                 <fb-select name="formatter_uuid" label="{{ __('deliverer-agrip::formatter-ranges.formatter') }}"
                            url="{{route('deliverer-agrip.formatters.scope')}}?q=%s" auto-fetch=""
                            options-value="uuid" options-name="name" :multiple="false" placeholder-text=" "></fb-select>

                 <fb-select name="range" label="{{ __('deliverer-agrip::formatter-ranges.range') }}"
                            url="{{route('deliverer-agrip.formatter_ranges.ranges')}}" auto-fetch=""
                            :disabled="!!AWES._store.state.forms['add-formatter-range'].fields.range"
                            options-value="value" options-name="name" :multiple="false" placeholder-text=" "></fb-select>


                <div v-if="AWES._store.state.forms['add-formatter-range'].fields.range" class="mt-10">

                    <div class="section">
                        <h3>{{ __('deliverer-agrip::formatter-ranges.range_actions') }}</h3>
                        <hr class="my-30"/>
                        <fb-multi-block name="actions" label="{{ __('deliverer-agrip::formatter-ranges.add_action') }}">
                            <template slot-scope="block">

                               <div class="section">
                                   <fb-select name="action" label="{{ __('deliverer-agrip::formatter-ranges.action') }}" :id="block.id"
                                              :url="'{{route('deliverer-agrip.formatter_ranges.actions')}}?range='
                                    + AWES._store.state.forms['add-formatter-range'].fields.range" auto-fetch="" :multiple="false"
                                              options-value="value" options-name="name" placeholder-text=" "></fb-select>

                                   <fb-checkbox name="active" :id="block.id" label="{{ __('deliverer-agrip::formatter-ranges.action_active') }}" :value="1" ></fb-checkbox>

                                   <fb-textarea name="configuration" :id="block.id" label="{{ __('deliverer-agrip::formatter-ranges.configuration') }}"></fb-textarea>
                               </div>

                                <hr class="my-30"/>
                            </template>
                        </fb-multi-block>
                    </div>

                </div>

            </div>
        </form-builder>
    </modal-window>

    {{--Edit formatter range--}}
    <modal-window name="edit-formatter-range" class="modal_formbuilder" title="{{ __('deliverer-agrip::formatter-ranges.edition_formatter_range') }}">
        <form-builder method="PATCH" url="{{ route('deliverer-agrip.formatter_ranges.index') }}/{id}" store-data="editFormatterRange" @sended="AWES.emit('content::formatter_ranges_table:update')"
                      send-text="{{ __('deliverer-agrip::general.save') }}"
                      cancel-text="{{ __('deliverer-agrip::general.cancel') }}">

            <div v-if="AWES._store.state.editFormatterRange">

                <fb-input type="hidden" name="formatter_uuid"></fb-input>
                <fb-input type="hidden" name="range"></fb-input>


                <div class="section">
                    <h3>{{ __('deliverer-agrip::formatter-ranges.range_actions') }}</h3>
                    <hr class="my-30"/>
                    <fb-multi-block name="actions" label="{{ __('deliverer-agrip::formatter-ranges.add_action') }}">
                        <template slot-scope="block">

                            <div class="section">

                                <h5 class="mb-5 mt-10">@{{ AWES._store.state.editFormatterRange.actions[block.id].name }}</h5>
                                <fb-input type="hidden" name="action" :id="block.id"></fb-input>

                                <fb-checkbox name="active" :id="block.id" label="{{ __('deliverer-agrip::formatter-ranges.action_active') }}" :value="1" ></fb-checkbox>

                                <fb-textarea name="configuration" :id="block.id" label="{{ __('deliverer-agrip::formatter-ranges.configuration') }}"></fb-textarea>
                            </div>

                            <hr class="my-30"/>
                        </template>
                    </fb-multi-block>
                </div>
            </div>
        </form-builder>
    </modal-window>

    {{--Delete formatter range--}}
    <modal-window name="delete-formatter-range" class="modal_formbuilder" title="{{ __('deliverer-agrip::formatter-ranges.are_you_sure_delete_formatter_range') }}">
        <form-builder name="delete-formatter-range" method="DELETE" url="{{ route('deliverer-agrip.formatter_ranges.index') }}/{id}" store-data="deleteFormatterRange" @sended="AWES.emit('content::formatter_ranges_table:update')"
                      send-text="{{ __('deliverer-agrip::general.yes') }}"
                      cancel-text="{{ __('deliverer-agrip::general.no') }}"
                      disabled-dialog>
            <template slot-scope="block">

                <!-- Fix enable button yes for delete -->
                <input type="hidden" name="isEdited" :value="AWES._store.state.forms['delete-formatter-range']['isEdited'] = true"/>
            </template>
        </form-builder>
    </modal-window>

@endsection
