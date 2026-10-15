<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $view_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank whereNameAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Bank whereViewName($value)
 * @mixin \Eloquent
 */
class Bank extends Model
{
	protected $guarded = ['id'];
	public function getName($lang = null)
	{
		$lang = $lang ?: app()->getLocale();
		return $this['name_'.$lang];
	}
	public function getViewName()
	{
		return $this->view_name;
	}
}
