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
	
		
		$canViewCustomerInvoicePastDueNotification = $user->can('view customer invoice past due notification');
		$canViewCustomerInvoiceComingDueNotification = $user->can('view customer invoice coming due notification');
		$canViewCustomerInvoiceCurrentDueNotification = $user->can('view customer invoice current due notification');
		$canViewCustomerInvoicesNotifications = $canViewCustomerInvoicePastDueNotification || $canViewCustomerInvoiceComingDueNotification||$canViewCustomerInvoiceCurrentDueNotification ;
		
		
		
		$canViewSupplierInvoicesPastDueNotifications = $user->can('view supplier invoices past due notifications');
		$canViewSupplierInvoicesCurrentDueNotification = $user->can('view supplier invoices current due notifications');
		$canViewSupplierInvoicesComingDueNotification = $user->can('view supplier invoices coming due notifications');
		$canViewSupplierInvoicesNotifications = $canViewSupplierInvoicesPastDueNotifications || $canViewSupplierInvoicesCurrentDueNotification || $canViewSupplierInvoicesComingDueNotification;
		
		
		$canViewChequePastDueNotifications = $user->can('view cheque past due notifications');
		$canViewChequeComingDueNotifications = $user->can('view cheque current due notifications');
		$canViewChequeUnderCollectionTodayNotifications = $user->can('view cheque under collection today notifications');
		$canViewChequeUnderCollectionSinceDaysNotifications = $user->can('view cheque under collection since days notifications');
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
		if($user->can('view current payable cheques notifications') || $user->can('view coming payable cheques notifications')){
			$items[self::CURRENT_PAYABLE_CHEQUE] = [
				'title'=>__('Payable Cheques') ,
				'subitems'=>HArr::filterTrulyValue([
					$user->can('view current payable cheques notifications') ? self::CURRENT_PAYABLE_CHEQUES:false,
					$user->can('view coming payable cheques notifications') ? self::COMING_PAYABLE_CHEQUES:false,
					
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
