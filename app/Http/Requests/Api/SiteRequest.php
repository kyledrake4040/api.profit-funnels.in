<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class SiteRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:160'],
            'headline'      => ['nullable', 'string', 'max:200'],
            'about'         => ['nullable', 'string', 'max:5000'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'email'         => ['nullable', 'email', 'max:190'],
            'city'          => ['nullable', 'string', 'max:120'],
            'services'      => ['nullable', 'array'],
            'services.*'    => ['string', 'max:80'],
            'theme_color'   => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'published'     => ['nullable', 'boolean'],
        ];
    }
}
