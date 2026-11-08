<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $activity
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property int|null $user_id
 * @property int|null $company_id
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log whereActivity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Log whereUserId($value)
 * @mixin \Eloquent
 */
class Log extends Model
{
	protected $guarded = ['id'];

	public function user():BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}

	/**
	 * لو آخر سجل للمستخدم في نفس الشركة هو نفس القسم ولسه ما عداش عليه
	 * الوقت ده، مش بنكرر السطر. بعد الوقت ده بنسجل تاني عشان "آخر ظهور"
	 * ما يفضلش واقف على أول مرة دخل القسم.
	 */
	public const DEDUPE_MINUTES = 30;

	public static function storeNewLogRecord(string $type , ?User $user = null , ?string $sectionName = null , ?int $companyId = null ):?Log
	{
		$user = $user ?: Auth()->user();
		$companyId = $companyId ?? getCurrentCompanyId();
		if(is_null($user) || is_null($companyId) || !is_numeric($companyId)){
			return null;
		}
		$companyId = (int) $companyId;
		$message = Log::generateLogMessage($type,$sectionName);

		$lastRecord = Log::where('company_id',$companyId)->where('user_id',$user->id)->orderByDesc('id')->first();
		if($lastRecord && $lastRecord->activity === $message && !$lastRecord->isOlderThanMinutes(self::DEDUPE_MINUTES)){
			return null ;
		}

		return Log::create([
			'activity'=>$message,
			'created_at'=>now(),
			'user_id'=>$user->id,
			'company_id' => $companyId
		]);
	}

	/**
	 * الـ login والـ logout مالهمش شركة، فبيتسجلوا بـ company_id = null.
	 */
	public static function storeSessionEvent(string $type , User $user):Log
	{
		return Log::create([
			'activity'=>Log::generateLogMessage($type),
			'created_at'=>now(),
			'user_id'=>$user->id,
			'company_id' => null
		]);
	}

	public function isOlderThanMinutes(int $minutes):bool
	{
		try {
			$createdAt = $this->created_at;
		} catch (\Throwable $e) {
			return true;
		}
		return is_null($createdAt) || $createdAt->lt(now()->subMinutes($minutes));
	}
		
	public function company():BelongsTo
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}

	public static function generateLogMessage(string $type , ?string $sectionName=null):string
	{
		return [
			'successLogin'=> __('Logged In !'),
			'successLogout'=> __('Logged Out'),
			'enterSection'=>__('Enter Section') . ' [ ' . $sectionName . ' ]'
		][$type];
	}
}

// money_received
