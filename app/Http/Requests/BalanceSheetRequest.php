<?php

namespace App\Http\Requests;



class BalanceSheetRequest extends CustomJsonRequest
{

	protected $stopOnFirstFailure = true;

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize(): bool
	{
		return true;
	}



	public function messages(): array
	{
		return [];
	}
	public static function rules(): array
	{

		return [];
	}
}
