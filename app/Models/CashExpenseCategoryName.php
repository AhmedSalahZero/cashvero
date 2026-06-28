<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $odoo_id
 * @property string|null $odoo_chart_of_account_number
 * @property int $company_id
 * @property int $cash_expense_category_id
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CashExpenseCategory $cashExpenseCategory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashExpense> $cashExpenses
 * @property-read int|null $cash_expenses_count
 * @property-read bool|null $cash_expenses_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereCashExpenseCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereOdooChartOfAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashExpenseCategoryName whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashExpenseCategoryName extends Model
{
	
	protected $guarded = ['id'];
	
	public function cashExpenseCategory()
	{
		return $this->belongsTo(CashExpenseCategory::class,'cash_expense_category_id','id');
	}
	public static function findByOdooChatOfAccountNumber(int $companyId , string $code)
	{
		return self::where('company_id',$companyId)->where('odoo_chart_of_account_number',$code)->first();
	}
	public function getName()
	{
		return $this->name;
	}
	public function cashExpenses()
	{
		return $this->hasMany(CashExpense::class,'cash_expense_category_name_id','id');
	}
	public static function getAllForCompany(Company $company){
		return self::where('company_id',$company->id)->get();
	} 		
	public function getOdooId()
	{
		return $this->odoo_id ;
	}
	public function getOdooChartOfAccountNumber()
	{
		return $this->odoo_chart_of_account_number;
	}
}
