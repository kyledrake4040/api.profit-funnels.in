<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class ContactRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:120'],
            'last_name'     => ['nullable', 'string', 'max:120'],
            'email'         => ['nullable', 'email', 'max:190'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'company'       => ['nullable', 'string', 'max:160'],
            'status'        => ['nullable', Rule::in(config('custom.contact.status'))],
            'source'        => ['nullable', 'string', 'max:80'],
            'tags'          => ['nullable', 'array'],
            'tags.*'        => ['string', 'max:40'],
            'custom_fields' => ['nullable', 'array'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ];
    }
}
