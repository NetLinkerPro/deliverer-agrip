<?php

namespace NetLinker\DelivererAgrip\Sections\Settings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSetting extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function getValidatorInstance()
    {
        if (is_array($this->owner_supervisor_uuid)) {
            $this->merge(['owner_supervisor_uuid' => $this->owner_supervisor_uuid[0] ?? '']);
        }

        return parent::getValidatorInstance();
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'url_1' => 'nullable|string|max:255',
            'url_2' => 'nullable|string|max:255',
            'login'  => 'nullable|string|max:255',
            'pass'  => 'nullable|string|max:255',
            'login2'  => 'nullable|string|max:255',
            'pass2'  => 'nullable|string|max:255',
            'token'  => 'nullable|string|max:255',
            'debug' => 'nullable|boolean',
            'from_add_product'  => 'nullable|string|max:255',
            'add_products_cron' => 'nullable|cron',
            'owner_supervisor_uuid'=> 'required|string|max:36',
            'update_exist_images_disk' => 'nullable|boolean',
            'max_width_images_disk' => 'nullable|integer',
            'limit_products' => 'nullable|integer',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'url_1' => __('deliverer-agrip::settings.attributes.url_1'),
            'url_2' => __('deliverer-agrip::settings.attributes.url_2'),
            'login' =>__('deliverer-agrip::settings.attributes.email'),
            'pass' => __('deliverer-agrip::settings.attributes.password'),
            'login2' =>__('deliverer-agrip::settings.attributes.login2'),
            'pass2' => __('deliverer-agrip::settings.attributes.password2'),
            'token' => __('deliverer-agrip::settings.attributes.token'),
        ];
    }
}


