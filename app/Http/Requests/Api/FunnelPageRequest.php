<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class FunnelPageRequest extends ApiFormRequest
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
            'type' => ['nullable', Rule::in(config('custom.page.types'))],
            'content' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => ['nullable', Rule::in(config('custom.page.status'))],
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
            'type' => __('Type'),
            'content' => __('Content'),
            'sort_order' => __('Sort order'),
            'status' => __('Status'),
        ];
    }
}
