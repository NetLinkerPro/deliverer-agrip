<?php

namespace NetLinker\DelivererAgrip\Sections\Configurations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfiguration extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'url_1' => 'nullable|string|max:255',
            'url_2' => 'nullable|string|max:255',
            'login'  => 'nullable|string|max:255',
            'pass'  => 'nullable|string|max:255',
            'login2'  => 'nullable|string|max:255',
            'pass2'  => 'nullable|string|max:255',
            'token'  => 'nullable|string|max:255',
            'debug' =>'nullable|boolean',
            'baselinker' =>'nullable|array',
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
            'url_1' => __('deliverer-agrip::configurations.attributes.url_1'),
            'url_2' => __('deliverer-agrip::configurations.attributes.url_2'),
            'login' =>__('deliverer-agrip::configurations.attributes.email'),
            'pass' => __('deliverer-agrip::configurations.attributes.password'),
            'login2' =>__('deliverer-agrip::configurations.attributes.login2'),
            'pass2' => __('deliverer-agrip::configurations.attributes.password2'),
            'token' => __('deliverer-agrip::configurations.attributes.token'),
            'debug' => __('deliverer-agrip::configurations.attributes.debug'),
            'baselinker.api_token_baselinker' => __('deliverer-agrip::configurations.attributes.api_token_baselinker'),
            'baselinker.id_category_products' => __('deliverer-agrip::configurations.attributes.id_category_products_baselinker'),
        ];
    }
}

