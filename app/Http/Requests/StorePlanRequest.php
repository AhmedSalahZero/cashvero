<?php

namespace App\Http\Requests;



class StorePlanRequest extends CustomJsonRequest
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
		return [
			//'duration.required' => __('Please Enter Financial Statement Duration'),
			//'duration.numeric' => __('Invalid Duration'),
			//'name.required' => __('Please Enter Financial Statement Name'),
			// 'name.max' => __('Max characters For Name (255)')
		];
	}
	public static function rules(): array
	{
		return [
			//'duration' => 'required|numeric',
			// 'name' => 'required|max:255'
		];
	}
}
