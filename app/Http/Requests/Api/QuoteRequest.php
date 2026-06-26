<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class QuoteRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_id'          => ['nullable', 'integer'],
            'job_id'              => ['nullable', 'integer'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity'    => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
