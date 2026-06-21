<?php

namespace App\Http\Requests\Api;

class SubscriptionRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
            'gateway' => 'nullable|string|max:50',
            'gateway_reference' => 'nullable|string|max:191',
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
            'plan_id' => __('Plan'),
            'gateway' => __('Payment gateway'),
            'gateway_reference' => __('Gateway reference'),
        ];
    }
}
