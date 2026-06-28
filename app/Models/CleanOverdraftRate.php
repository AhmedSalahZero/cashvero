<?php

namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * Rate هنا المقصود بيها
 * * margin rate and so on
 *
 * @property int $id
 * @property int|null $company_id
 * @property int $clean_overdraft_id
 * @property string $date
 * @property numeric $borrowing_rate
 * @property numeric $margin_rate
 * @property numeric $interest_rate
 * @property numeric $min_interest_rate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CleanOverdraft|null $cleanOverdraft
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereBorrowingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereCleanOverdraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereMarginRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereMinInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftRate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CleanOverdraftRate extends Model  
{
    protected $guarded = [
        'id'
    ];
	
	/**
	 * * ال 
	 * * global scope 
	 * * دا خاص بس بجزئيه ال
	 * * commission 
	 * * ما عدا ذالك ملهوش اي لزمة هو والكولوم اللي اسمة
	 * * is_active
	 */
	protected static function boot()
    {
        parent::boot();

    }
	public function cleanOverdraft()
	{
		return $this->belongsTo(CleanOverdraft::class,'clean_overdraft_id','id');
	}
	public function overdraftModal()
	{
		return $this->cleanOverdraft(); 
	}
	public function getDate()
	{
		return $this->date ;
	}
	public function getDateFormatted()
	{
		$date = $this->getDate();
		return $date ? Carbon::make($date)->format('d-m-Y') : __('N/A'); 
	}	
	public function getBorrowingRate()
	{
		return $this->borrowing_rate?:0;
	}
	public function getBorrowingRateFormatted()
	{
		return number_format($this->getBorrowingRate(),2) . ' %';
	}
	public function getMarginRate()
	{
		return $this->margin_rate?:0;
	}
	public function getMarginRateFormatted()
	{
		return number_format($this->getMarginRate(),2) . ' %';
	}
	public function getMinInterestRate()
	{
		return $this->min_interest_rate?:0;
	}
	public function getMinInterestRateFormatted()
	{
		return number_format($this->getMinInterestRate(),2) . ' %';
	}
	public function getInterestRate()
	{
		return $this->interest_rate?:0;
	}
	public function getInterestRateFormatted()
	{
		return number_format($this->getInterestRate(),2) . ' %';
	}
	
}
