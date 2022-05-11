<?php

namespace NetLinker\DelivererAgrip\Sections\Formatters\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NetLinker\DelivererAgrip\Ownerable;

class UpdateFormatter extends FormRequest
{
    use Ownerable;

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
            'name' => ['required', 'string', 'max:64', Rule::unique('deliverer_agrip_formatters')->where(function ($query) {
                return $query->where('name', $this->name)
                    ->where('owner_uuid', $this->getAuthOwnerUuid());
            })->ignore($this->id)],
            'description' => 'nullable|string',
            'identifier_type' => 'required|string|max:38',
            'name_lang' => 'required|string|max:8',
            'name_type' => 'required|string|max:15',
            'url_type' => 'required|string|max:15',
            'price_currency' => 'required|string|max:15',
            'price_type' => 'required|string|max:15',
            'tax_country' => 'required|string|max:36',
            'stock_type' => 'required|string|max:15',
            'category_lang' => 'required|string|max:8',
            'category_type' => 'required|string|max:15',
            'image_lang' => 'required|string|max:8',
            'image_type' => 'required|string|max:15',
            'attribute_lang' => 'required|string|max:8',
            'attribute_type' => 'required|string|max:15',
            'description_lang' => 'required|string|max:8',
            'description_type' => 'required|string|max:15',
        ];
    }
}


