<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class AutomationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:150'],
            'trigger_event'  => ['required', Rule::in(config('custom.automation.events'))],
            'is_active'      => ['nullable', 'boolean'],
            'actions'        => ['nullable', 'array'],
            'actions.*.type' => ['required', Rule::in(config('custom.automation.actions'))],
            'actions.*.config' => ['nullable', 'array'],
        ];
    }
}
