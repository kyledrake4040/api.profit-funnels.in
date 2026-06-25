<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class OpportunityRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pipeline_id'       => ['required', 'integer'],
            'stage_id'          => ['required', 'integer'],
            'contact_id'        => ['nullable', 'integer'],
            'name'              => ['required', 'string', 'max:180'],
            'value'             => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'status'            => ['nullable', Rule::in(config('custom.opportunity.status'))],
            'expected_close_at' => ['nullable', 'date'],
        ];
    }
}
