<?php

namespace NetLinker\DelivererAgrip\Sections\Categories\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NetLinker\DelivererAgrip\Ownerable;

class UpdateCategory extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('deliverer_agrip_categories')->where(function ($query) {
                return $query->where('name', $this->name)
                    ->where('owner_uuid', $this->getAuthOwnerUuid());
            })->ignore($this->id)],
            'description' => 'nullable|string',
            'active' => 'required|boolean',
            'uri' => 'nullable|string',
            'ctx' => 'nullable|string',
            'ctr' => 'nullable|string',
            'item_id' => 'required|string',
            'table_number' => 'required|integer',
            't' => 'nullable|string',
            'data' => 'required|string',
        ];
    }
}


