<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AuthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        //dd(\Request::route()->getName());
        //dd(\Route::current()->getName());
        return [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
//    public function messages()
//    {
//        return [
//            'password.required' => __('Password is required'),
//            'password.min' => __('Password minimum length is :min characters'),
//            'password.max' => __('Password maximum length is :max characters')
//        ];
//    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return ['password' => __('Password')];
    }

    /**
     * If validator fails return the exception in json form
     *
     * @param  Validator  $validator
     *
     * @return array
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'code' => 422,
                "success" => false,
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
