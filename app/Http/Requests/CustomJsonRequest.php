<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

abstract class CustomJsonRequest  extends FormRequest
{
    abstract public function authorize(): bool;

    abstract public static function rules() :array;

    protected function failedValidation(Validator $validator): ValidationException
    {

        throw new HttpResponseException(
            response()->json([
                'status'    => false,
                'message'   => $validator->errors()->first(),
                'showAlert' => true,
            ], 400)
        );
    }
}
