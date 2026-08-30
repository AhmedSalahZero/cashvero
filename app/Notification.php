<?php

namespace App;

use App\Helpers\HArr;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property string $data
 * @property string|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereNotifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereNotifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Notification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Notification extends Model
{
	const CUSTOMER = 'customer';
	const SUPPLIER = 'supplier';
	const RECEIVABLE_CHEQUE = 'receivable_cheque';
	const CURRENT_PAYABLE_CHEQUE = 'current_payable_cheque';
	const COMING_PAYABLE_CHEQUE = 'coming_payable_cheque';
	const CUSTOMER_INVOICE_PAST_DUE = 'customer_invoice_past_due';
	const SUPPLIER_INVOICE_PAST_DUE = 'supplier_invoice_past_due';
	const CUSTOMER_INVOICE_CURRENT_DUE = 'customer_invoice_current_due';
	const SUPPLIER_INVOICE_CURRENT_DUE = 'supplier_invoice_current_due';
	const CUSTOMER_INVOICE_COMING_DUE = 'customer_invoice_coming_due';
	const SUPPLIER_INVOICE_COMING_DUE = 'supplier_invoice_coming_due';
	const CURRENT_PAYABLE_CHEQUES = 'current_payable_cheque';
	const COMING_PAYABLE_CHEQUES = 'coming_payable_cheque';
	const CHEQUE_PAST_DUE = 'cheque_past_due';
	const CHEQUE_CURRENT_DUE = 'cheque_current_due';
	const COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS = 'coming_receivable_cheques_notifications_days';

    protected $guarded = ['id'];

	/**
	 * Which existing screen permission grants each notification gate.
	 *
	 * The bell originally demanded a dedicated permission per notification
	 * type (`view customer invoice past due notification`, …). Those names
	 * were never added to PermissionRegistry, so the Roles & Permissions
	 * screen has no way to grant them — only the original seeded super-admin
	 * ever held them, and every account created since saw an empty bell.
	 *
	 * A notification is only an early warning about a record the user can
	 * already open, so the screen's own view permission is the honest gate:
	 * whoever may read customer balances may be told an invoice is overdue.
	 *
	 * The legacy name is still checked first, so nothing already granted is
	 * taken away.
	 */
	private const NOTIFICATION_GATES = [
		// Customer invoice due-date warnings → the customer balances screen.
		'view customer invoice past due notification'      => 'view customer balances',
		'view customer invoice coming due notification'    => 'view customer balances',
		'view customer invoice current due notification'   => 'view customer balances',

		// Supplier invoice due-date warnings → the supplier balances screen.
		'view supplier invoices past due notifications'    => 'view supplier balances',
		'view supplier invoices current due notifications' => 'view supplier balances',
		'view supplier invoices coming due notifications'  => 'view supplier balances',

		// Receivable cheques are collected from Money Received.
		'view cheque past due notifications'               => 'view money received',
		'view cheque current due notifications'            => 'view money received',
		'view cheque under collection today notifications' => 'view money received',
		'view cheque under collection since days notifications' => 'view money received',

		// Payable cheques are issued from Money Payment.
		'view current payable cheques notifications'       => 'view supplier payment',
		'view coming payable cheques notifications'        => 'view supplier payment',
	];

	/**
	 * True when the user holds the notification permission itself, or the
	 * view permission of the screen that notification points at.
	 */
	public static function userCanSee(?User $user, string $permission): bool
	{
		if (! $user) {
			return false;
		}

		return $user->can($permission)
			|| (isset(self::NOTIFICATION_GATES[$permission]) && $user->can(self::NOTIFICATION_GATES[$permission]));
	}

	public static function getAllMainTypes():array 
	{
		return [
			self::CUSTOMER_INVOICE_PAST_DUE => __('Customer Invoice Past Dues'),
			self::CUSTOMER_INVOICE_COMING_DUE => __('Customer Invoice Coming Dues'),
			self::CUSTOMER_INVOICE_CURRENT_DUE => __('Customer Invoice Current Dues'),
			self::SUPPLIER_INVOICE_PAST_DUE => __('Supplier Invoice Past Dues'),
			self::SUPPLIER_INVOICE_CURRENT_DUE => __('Supplier Invoice Current Dues'),
			self::SUPPLIER_INVOICE_COMING_DUE => __('Supplier Invoice Coming Dues'),
			self::CURRENT_PAYABLE_CHEQUES => __('Current Payable Cheques'),
			self::COMING_PAYABLE_CHEQUES => __('Coming Payable Cheques'),
			self::CHEQUE_PAST_DUE => __('Cheques Past Dues'),
			self::CHEQUE_CURRENT_DUE => __('Cheques Current Dues'),
			self::COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS => __('Cheque Coming Dues'),
			// self::CHEQUE_UNDER_COLLECTION_SINCE_DAYS => __('Cheques Under Collection Since Days'),
		
		];
	}
	public static function getAllTypesFormatted():array 
	{
		$user = auth()->user();
		/**
		 * @var User|null $user ;
		 */
		if(!$user){
			return [];
		}
	
		
		$canViewCustomerInvoicePastDueNotification = self::userCanSee($user, 'view customer invoice past due notification');
		$canViewCustomerInvoiceComingDueNotification = self::userCanSee($user, 'view customer invoice coming due notification');
		$canViewCustomerInvoiceCurrentDueNotification = self::userCanSee($user, 'view customer invoice current due notification');
		$canViewCustomerInvoicesNotifications = $canViewCustomerInvoicePastDueNotification || $canViewCustomerInvoiceComingDueNotification||$canViewCustomerInvoiceCurrentDueNotification ;
		
		
		
		$canViewSupplierInvoicesPastDueNotifications = self::userCanSee($user, 'view supplier invoices past due notifications');
		$canViewSupplierInvoicesCurrentDueNotification = self::userCanSee($user, 'view supplier invoices current due notifications');
		$canViewSupplierInvoicesComingDueNotification = self::userCanSee($user, 'view supplier invoices coming due notifications');
		$canViewSupplierInvoicesNotifications = $canViewSupplierInvoicesPastDueNotifications || $canViewSupplierInvoicesCurrentDueNotification || $canViewSupplierInvoicesComingDueNotification;
		
		
		$canViewChequePastDueNotifications = self::userCanSee($user, 'view cheque past due notifications');
		$canViewChequeComingDueNotifications = self::userCanSee($user, 'view cheque current due notifications');
		$canViewChequeUnderCollectionTodayNotifications = self::userCanSee($user, 'view cheque under collection today notifications');
		$canViewChequeUnderCollectionSinceDaysNotifications = self::userCanSee($user, 'view cheque under collection since days notifications');
		$canViewReceivableChequesNotifications = $canViewChequePastDueNotifications || $canViewChequeComingDueNotifications ||$canViewChequeUnderCollectionTodayNotifications || $canViewChequeUnderCollectionSinceDaysNotifications;
		 
		$items = [];
		if($canViewCustomerInvoicePastDueNotification){
			$items[self::CUSTOMER]=[
				'title'=>__('Customer Invoices') ,
				'subitems'=>HArr::filterTrulyValue([
					self::CUSTOMER_INVOICE_PAST_DUE  ,
					$canViewCustomerInvoicesNotifications ? self:: CUSTOMER_INVOICE_COMING_DUE : false,
					$canViewCustomerInvoiceCurrentDueNotification ? self::CUSTOMER_INVOICE_CURRENT_DUE : false ,
				])
				];
		}
		if($canViewSupplierInvoicesNotifications){
			$items[self::SUPPLIER] = [
				'title'=>__('Supplier Invoices'),
				'subitems'=>HArr::filterTrulyValue([
					$canViewSupplierInvoicesPastDueNotifications ? self::SUPPLIER_INVOICE_PAST_DUE : false,
					$canViewSupplierInvoicesCurrentDueNotification ?  self::SUPPLIER_INVOICE_CURRENT_DUE : false ,
					$canViewSupplierInvoicesComingDueNotification ? self::SUPPLIER_INVOICE_COMING_DUE : false ,
				])
				];
		}
		if($canViewReceivableChequesNotifications){
			$items[self::RECEIVABLE_CHEQUE] = [
				'title'=>__('Receivable Cheques') ,
				'subitems'=>HArr::filterTrulyValue([
					$canViewChequePastDueNotifications ?self::CHEQUE_PAST_DUE:false,
					$canViewChequeComingDueNotifications ? self::CHEQUE_CURRENT_DUE : false ,
					$canViewChequeUnderCollectionTodayNotifications ? self::COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS : false ,
					// $canViewChequeUnderCollectionSinceDaysNotifications ? self::CHEQUE_UNDER_COLLECTION_SINCE_DAYS : false
				])
				];
		}
		if(self::userCanSee($user, 'view current payable cheques notifications') || self::userCanSee($user, 'view coming payable cheques notifications')){
			$items[self::CURRENT_PAYABLE_CHEQUE] = [
				'title'=>__('Payable Cheques') ,
				'subitems'=>HArr::filterTrulyValue([
					self::userCanSee($user, 'view current payable cheques notifications') ? self::CURRENT_PAYABLE_CHEQUES:false,
					self::userCanSee($user, 'view coming payable cheques notifications') ? self::COMING_PAYABLE_CHEQUES:false,
					
				])
			];
		}
	
		return $items ; 
	}
	public static function formatForMenuItem(Company $company):array 
	{
		$formattedItems = [];

		foreach(self::getAllTypesFormatted() as $mainTypeId => $detailArray ){
			$mainCount = 0 ;
			
			$mainArr = [
				'title'=>$detailArray['title'],
				'link'=>'#',
				'show'=>true ,
			];
			$subItems = [];
			foreach($detailArray['subitems'] as $subItemId){
				$customerPastDues = $company->notifications
										->where('data.type',$subItemId)
										;
				$subCount = count($customerPastDues) ;
				$mainCount+=$subCount;
				$subItemTitle = self::getAllMainTypes()[$subItemId] ;
				$subItems[] = [
					'title'=>$subItemTitle,
					'show'=>true ,
					'data-show-notification-modal'=>$subItemId,
					'count'=>$subCount,
					'link'=>'#'
				];
				
				
				
				
			}
			$mainArr['count']=$mainCount;
			$mainArr['submenu'] = $subItems ;
			$formattedItems[] = $mainArr;
		}

		return $formattedItems;
	}
	public static function getSearchFieldsBasedOnTypes():array 
	{
		return [
			Notification::CUSTOMER=>[
				'created_at'=>__('Date')
			],
			Notification::SUPPLIER=>[
				'created_at'=>__('Date')
			],
			Notification::RECEIVABLE_CHEQUE=>[
				'created_at'=>__('Date')
			],
			// Notification::PENDING_PAYABLE_CHEQUE=>[
			// 	'created_at'=>__('Date')
			// ]
		];
	}
	
	
}
