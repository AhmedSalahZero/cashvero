<?php 
namespace App\Enums;
class LgSources
{
	public const LG_FACILITY = 'lg-facility';
	public const AGAINST_CD = 'against-cd';
	public const AGAINST_TD = 'against-td';
	public const HUNDRED_PERCENTAGE_CASH_COVER = 'hundred-percentage-cash-cover';
	public static function getAll():array 
	{
		return [
			self::LG_FACILITY => __('LG Facility') , 
			self::AGAINST_CD => __('Against CD'),
			self::AGAINST_TD=>__('Against TD'),
			self::HUNDRED_PERCENTAGE_CASH_COVER=>__('100% Cash Cover')
		];
	}
	
	 
}
