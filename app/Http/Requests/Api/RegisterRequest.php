<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

final class RegisterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:191',
            'email'    => 'required|email|max:191|unique:users,email',
            'password' => 'required|string|min:8|max:20|confirmed',
        ];
    }
}
