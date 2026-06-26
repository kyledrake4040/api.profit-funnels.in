<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

final class JobRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_id'   => ['nullable', 'integer'],
            'title'        => ['required', 'string', 'max:180'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'status'       => ['nullable', Rule::in(config('custom.job.status'))],
            'scheduled_at' => ['nullable', 'date'],
            'value'        => ['nullable', 'numeric', 'min:0'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'address'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
