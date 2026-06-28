<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property int $company_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashExpenseCategoryName> $cashExpenseCategoryNames
 * @property-read int|null $cash_expense_category_names_count
 * @property-read bool|null $cash_expense_category_names_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategory whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class CashExpenseCategory extends Model
{
	use HasBasicStoreRequest ;
	protected $guarded = ['id'];
	public function getId()
	{
		return $this->id ;
	}
	
	public function getName()
	{
		return $this->name ;
	}
	public function cashExpenseCategoryNames()
	{
		return $this->hasMany(CashExpenseCategoryName::class,'cash_expense_category_id','id');
	}
}
