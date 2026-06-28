<?php

namespace App\Models;

use App\Models\Traits\Accessors\CurrencyAccessor;
use App\Models\Traits\Relations\CurrencyRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $odoo_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Currency whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Currency extends Model
{
    use  CurrencyRelation , CurrencyAccessor;
	
	public static function getOdooId(string $currencyName):int 
	{
		return DB::table('currencies')->where('name',$currencyName)->first()->odoo_id;
	}
	public static function getIdFromOddoId(int $odooCurrencyId){
		return DB::table('currencies')->where('odoo_id',$odooCurrencyId)->first()->id;
	}
	public static function getNameFromOddoId(int $odooCurrencyId){
		return DB::table('currencies')->where('odoo_id',$odooCurrencyId)->first()->name;
	}
}
