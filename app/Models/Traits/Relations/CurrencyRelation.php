<?php
namespace App\Models\Traits\Relations ;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait CurrencyRelation
{

    public function creator():BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }
}
