<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class FunnelRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in(config('custom.funnel.status'))],
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
            'name' => __('Name'),
            'description' => __('Description'),
            'status' => __('Status'),
        ];
    }
}
