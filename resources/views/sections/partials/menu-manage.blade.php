<div class="filter__rlink">
    <context-menu button-class="filter__slink" right>
        <template slot="toggler">
            <span>{{ __('deliverer-agrip::general.manage') }}</span>
        </template>
        <cm-link href="{{route('deliverer-agrip.settings.index')}}">   {{ __('deliverer-agrip::general.manage_settings') }}</cm-link>
        <cm-link href="{{route('deliverer-agrip.configurations.index')}}">   {{ __('deliverer-agrip::general.manage_configurations') }}</cm-link>
        <cm-link href="{{route('deliverer-agrip.formatters.index')}}">   {{ __('deliverer-agrip::general.manage_formatters') }}</cm-link>
        <cm-link href="{{route('deliverer-agrip.formatter_ranges.index')}}">   {{ __('deliverer-agrip::general.manage_formatter_ranges') }}</cm-link>
    </context-menu>
</div>
