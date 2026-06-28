<?php

namespace App\Interfaces\Validators;

use Illuminate\Http\Request;

interface IValidateModel
{
    public function validate(Request $request);
}
