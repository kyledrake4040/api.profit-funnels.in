<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class SubscriptionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id'           => 'required|integer|exists:plans,id',
            'gateway'           => 'nullable|string|max:50',
            'gateway_reference' => 'nullable|string|max:191',
        ];
    }
}
