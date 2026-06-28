<?php

namespace App;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $customer_coming_dues_invoices_notifications_days
 * @property int $customer_past_dues_invoices_notifications_days
 * @property int $cheques_in_safe_notifications_days
 * @property int $cheques_under_collection_notifications_days
 * @property int $supplier_coming_dues_invoices_notifications_days
 * @property int $supplier_past_dues_invoices_notifications_days
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $coming_payable_cheques_notifications_days
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereChequesInSafeNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereChequesUnderCollectionNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereComingPayableChequesNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereCustomerComingDuesInvoicesNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereCustomerPastDuesInvoicesNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereSupplierComingDuesInvoicesNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereSupplierPastDuesInvoicesNotificationsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereUpdatedAt($value)
 * @property int $coming_receivable_cheques_notifications_days
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\NotificationSetting whereComingReceivableChequesNotificationsDays($value)
 * @mixin \Eloquent
 */
class NotificationSetting extends Model
{
    protected $guarded = ['id'];
	
	const CUSTOMER_COMING_DUES_INVOICES_NOTIFICATIONS_DAYS = 3;
	const CUSTOMER_PAST_DUES_INVOICES_NOTIFICATIONS_DAYS = 1;
	const SUPPLIER_COMING_DUES_INVOICES_NOTIFICATIONS_DAYS = 3;
	const SUPPLIER_PAST_DUES_INVOICES_NOTIFICATIONS_DAYS = 1;
	const CHEQUES_IN_SAFE_NOTIFICATIONS_DAYS = 3;
	const COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS = 3;
	const COMING_PAYABLE_CHEQUES_NOTIFICATIONS_DAYS = 3;
	
	public function getId()
	{
		return $this->id;
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
	/**
	 * * هو عبارة عن عدد الايام اللي المفروض السيستم يبعت قبلها نوتيفكشن في حالة استحقاق فواتير العملاء
	 */
	public function getCustomerComingDuesInvoicesNotificationsDays()
	{
		// before due date
		return $this->customer_coming_dues_invoices_notifications_days;
	}

	
	/**
	 * * هو عبارة عن عدد الايام اللي المفروض السيستم يبعت بعدها نوتيفكشن في حالة لو الفاتورة المتاخره في السداد من فواتير العملاء
	 */
	public function getCustomerPastDuesInvoicesNotificationsDays()
	{
		return $this->customer_past_dues_invoices_notifications_days;
	}

	
	 /**
	 * * هو عبارة عن عدد الايام اللي قبل ما الشيك يستحق علشان اروح ابعته البنك
	 */
	public function getChequesInSafeNotificationsDays()
	{
		return $this->cheques_in_safe_notifications_days;
	}

	
		
	 /**
	 * *
	 */
	public function getComingReceivableChequesNotificationsDays()
	{
		return $this->coming_receivable_cheques_notifications_days;
	} 
	
	/**
	 * * هو عبارة عن عدد الايام اللي المفروض ينبهني ان الشيك تم تحصيلة ولا لا لان ممكن يكون الشيك ارتد
	 * * ودا هيتحسب من تاريخ ال
	 * * expected_collection_date
	 */
	public function getComingPayableChequeNotificationDays()
	{
		return $this->coming_payable_cheques_notifications_days;
	}
	
	
		/**
	 * * هو عبارة عن عدد الايام اللي المفروض السيستم يبعت قبلها نوتيفكشن في حالة استحقاق فواتير الموريدين
	 */
	public function getSupplierComingDuesInvoicesNotificationsDays()
	{
		return $this->supplier_coming_dues_invoices_notifications_days;
	}

	
	/**
	 * * هو عبارة عن عدد الايام اللي المفروض السيستم يبعت بعدها نوتيفكشن في حالة لو الفاتورة المتاخره في السداد من فواتير الموردين
	 */
	public function getSupplierPastDuesInvoicesNotificationsDays()
	{
		return $this->supplier_past_dues_invoices_notifications_days;
	}
	

	
	
	
}
