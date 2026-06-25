<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

/**
 * @property-read string|null $name
 */
final class AgencyRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:150'],
            'brand_name'    => ['nullable', 'string', 'max:150'],
            'custom_domain' => ['nullable', 'string', 'max:190'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_url'      => ['nullable', 'url', 'max:500'],
        ];
    }
}
