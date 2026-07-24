<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgIssuanceImportRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'errors' => 'array',
    ];
}
