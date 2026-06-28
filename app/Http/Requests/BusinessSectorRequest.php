<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessSectorRequest extends FormRequest
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

    public function rules()
    {
        return [
            'business_sector_name'=>'required|max:255'
        ];
    }
    public function messages()
    {
        return [
            'business_sector_name.required'=>__('Please Enter Business Sector Name'),
            'business_sector_name.max'=>__('Max Letters Numbers For Business Sector Name'),
        ];
    }
}
