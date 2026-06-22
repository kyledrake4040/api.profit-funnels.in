<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class FunnelRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'status'      => ['nullable', Rule::in(config('custom.funnel.status'))],
        ];
    }
}
