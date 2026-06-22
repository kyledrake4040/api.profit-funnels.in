<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class FunnelPageRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:150',
            'type'       => ['nullable', Rule::in(config('custom.page.types'))],
            'content'    => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => ['nullable', Rule::in(config('custom.page.status'))],
        ];
    }
}
