<?php

namespace App\Http\Requests;

class UpdateFactoringContractRequest extends StoreFactoringContractRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
