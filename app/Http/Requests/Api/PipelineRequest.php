<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class PipelineRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:150'],
            // Optional explicit stage names; omit to seed the default stages.
            'stages'   => ['nullable', 'array', 'min:1'],
            'stages.*' => ['string', 'max:120'],
        ];
    }
}
