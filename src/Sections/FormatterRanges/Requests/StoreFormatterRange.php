<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NetLinker\DelivererAgrip\Ownerable;

class StoreFormatterRange extends FormRequest
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
            'formatter_uuid' => 'required|string|max:36',
            'range' => ['required', 'string', 'max:64', Rule::unique('deliverer_agrip_formatter_ranges')->where(function ($query) {
                return $query->where('formatter_uuid', $this->formatter_uuid)
                    ->where('range', $this->range)
                    ->where('owner_uuid', $this->getAuthOwnerUuid());
            })],
            'actions' => 'required|array',
            'actions.*.action' => 'required|string|max:255',
            'actions.*.active' => 'required|boolean',
            'actions.*.configuration' => 'nullable|string',
        ];
    }
}


