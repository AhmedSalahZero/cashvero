<?php 
namespace App\Models ; 

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Money newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Money newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Money query()
 * @mixin \Eloquent
 */
class Money  extends Model
{
	protected $table = 'money';
	public $timestamps = false ;
	
}
