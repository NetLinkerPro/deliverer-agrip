<?php


namespace NetLinker\DelivererAgrip\Sections\Settings\Boot\Validators;


use Cron\CronExpression;
use Illuminate\Support\Facades\Validator;

class CronValidator
{

    /**
     * Boot
     */
    public function boot(){
        Validator::extend('cron', function ($attribute, $value, $parameters, $validator) {
            return CronExpression::isValidExpression($value);
        });
        Validator::replacer('cron', function ($message, $attribute, $rule, $parameters) {
            return __('deliverer-agrip::general.error_cron_validate');
        });
    }
}