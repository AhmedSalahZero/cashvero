<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $model_name
 * @property string|null $status هل الملف دا اترفع فعلا وبالتالي هظهره ولا هو لسه بيترفع حاليا
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereModelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LastUploadFileName whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LastUploadFileName extends Model
{
	const CURRENT ='current';
	const SUCCESS ='success';
	protected $guarded = [
		'id'
	];
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
}
