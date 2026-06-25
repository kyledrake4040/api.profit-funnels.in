<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

/**
 * @property-read int|null $agency_id
 * @property-read string|null $name
 */
final class AccountRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'name'      => ['required', 'string', 'max:150'],
        ];
    }
}
