<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $system_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem whereSystemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CompanySystem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CompanySystem extends Model
{
	// protected $table = 'company_system';
	protected $guarded = [
		'id'
	];
	
	public static function getAllSystemNames()
	{
		return [
			CASH_VERO ,
		];
	}
	public function company()
	{
		return $this->belongsTo(Company::class,'company_id','id');
	}
	public function getName():string 
	{
		return $this->system_name ;
	}
}
