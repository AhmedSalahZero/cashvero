<?php 
namespace App\Helpers;

use App\Models\Section;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Arr;

class HAuth {
	
public static function getSuperAdminSection()
{
    if (Auth::user()->hasrole('super-admin')) {
		if(Cache::has('superAdminSections')) {
			return Cache::get('superAdminSections');
		}
		$superAdminSections = Section::where('sub_of', 0)->where('section_side', 'admin')->where('trash', 0)->get();
		Cache::put('superAdminSections', $superAdminSections);
        return $superAdminSections;
    }
    if (Auth::user()->hasrole('company-admin')) {
		if(Cache::has('companyAdminSections')) {
			return Cache::get('companyAdminSections');
		}
		$companyAdminSections = Section::mainCompanyAdminSections()->get();
		Cache::put('companyAdminSections', $companyAdminSections);
        return $companyAdminSections;
    }
}


public static function getPermissions(array $systemsNames  = []):array
{
    $permissions =  [
        [
            'name'=>'view home',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'home',
            'view-name'=>'view'
        ],
        [
            'name'=>'update permissions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER],
            'group'=>'permissions',
            'view-name'=>'update'
        ],
        
       
     
        
        
        
        
    
        

        [
            'name'=>viewCustomerInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer invoices',
            'view-name'=>'view'
        ],
        [
            'name'=>uploadCustomerInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer invoices',
            'view-name'=>'upload'
        ],
        [
            'name'=>exportCustomerInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer invoices',
            'view-name'=>'export'
        ],

        [
            'name'=>deleteCustomerInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer invoices',
            'view-name'=>'delete'
        ],

        [
            'name'=>viewSupplierInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier invoices',
            'view-name'=>'view'
        ],
        [
            'name'=>uploadSupplierInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier invoices',
            'view-name'=>'upload'
        ],
        [
            'name'=>exportSupplierInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier invoices',
            'view-name'=>'export'
        ],

        [
            'name'=>deleteSupplierInvoiceData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier invoices',
            'view-name'=>'delete'
        ],
    
        [
            'name'=>viewLoanScheduleData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'loan schedule',
            'view-name'=>'view'
        ],
        [
            'name'=>uploadLoanScheduleData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'loan schedule',
            'view-name'=>'upload'
        ],
        [
            'name'=>exportLoanScheduleData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'loan schedule',
            'view-name'=>'export'
        ],

        [
            'name'=>deleteLoanScheduleData,
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'loan schedule',
            'view-name'=>'delete'
        ],
       
       
        [
            'name'=>'view customer aging',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'aging report',
            'view-name'=>'view customer aging report'
        ],
        [
            'name'=>'view collections effectiveness index',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'collection effectiveness index',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'view supplier aging',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'aging report',
            'view-name'=>'view supplier aging report'
        ],
        [
            'name'=>'view payments effectiveness index',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'payment effectiveness index',
            'view-name'=>'view'
        ],
        [
            'name'=>'view customer balances',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'balance report',
            'view-name'=>'view customers'
        ],  [
            'name'=>'view supplier balances',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'balance report',
            'view-name'=>'view suppliers'
        ],
        
        [
            'name'=>'view letter of guarantee facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee facility',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create letter of guarantee facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee facility',
            'view-name'=>'create'
        ],
        [
            'name'=>'update letter of guarantee facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee facility',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete letter of guarantee facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee facility',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view letter of guarantee issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee issuance',
            'view-name'=>'view'
        ],
        
        
        [
            'name'=>'create letter of guarantee issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee issuance',
            'view-name'=>'create'
        ],
        
        
        [
            'name'=>'update letter of guarantee issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee issuance',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete letter of guarantee issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of guarantee issuance',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view letter of credit issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit issuance',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create letter of credit issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit issuance',
            'view-name'=>'create'
        ],
        
        
        [
            'name'=>'update letter of credit issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit issuance',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete letter of credit issuance',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit issuance',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view letter of credit facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit facility',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create letter of credit facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit facility',
            'view-name'=>'create'
        ],
        [
            'name'=>'update letter of credit facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit facility',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete letter of credit facility',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'letter of credit facility',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view medium term loan',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'medium term loan',
            'view-name'=>'view'
        ],
        [
            'name'=>'create medium term loan',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'medium term loan',
            'view-name'=>'create'
        ],
        
        [
            'name'=>'update medium term loan',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'medium term loan',
            'view-name'=>'update'
        ],
        
        
        [
            'name'=>'delete medium term loan',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'medium term loan',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view certificate of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'certificate of deposit',
            'view-name'=>'view'
        ],
        [
            'name'=>'create certificate of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'certificate of deposit',
            'view-name'=>'create'
        ],
        [
            'name'=>'update certificate of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'certificate of deposit',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete certificate of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'certificate of deposit',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view time of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'time of deposit',
            'view-name'=>'view'
        ],
        [
            'name'=>'create time of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'time of deposit',
            'view-name'=>'create'
        ],
        [
            'name'=>'update time of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'time of deposit',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete time of deposit',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'time of deposit',
            'view-name'=>'delete'
        ],
        
        
        
        
        [
            'name'=>'view cash status dashboard',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'cash vero dashboard',
            'view-name'=>'view cash status dashboard'
        ],
        
        [
            'name'=>'view cash Forecast dashboard',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'cash vero dashboard',
            'view-name'=>'view cash forecast dashboard'
        ],
        
        [
            'name'=>'view lg & lc dashboard',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'cash vero dashboard',
            'view-name'=>'view lg & lc forecast dashboard'
        ],
        
        

        [
            'name'=>'view notification settings',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'notification & settings',
            'view-name'=>'view notification settings'
        ],
        [
            'name'=>'view customers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customers',
            'view-name'=>'view'
        ],
        [
            'name'=>'create customers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customers',
            'view-name'=>'create'
        ],
        [
            'name'=>'update customers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customers',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete customers',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'customers',
        // 	'view-name'=>'delete'
        // ],
        
        
        
        
        
        [
            'name'=>'view suppliers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'suppliers',
            'view-name'=>'view'
        ],
        [
            'name'=>'create suppliers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'suppliers',
            'view-name'=>'create'
        ],
        [
            'name'=>'update suppliers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'suppliers',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete suppliers',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'suppliers',
        // 	'view-name'=>'delete'
        // ],
        
        
        
        [
            'name'=>'view employees',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'employees',
            'view-name'=>'view'
        ],
        [
            'name'=>'create employees',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'employees',
            'view-name'=>'create'
        ],
        [
            'name'=>'update employees',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'employees',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete employees',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'employees',
        // 	'view-name'=>'delete'
        // ],
        
        
        [
            'name'=>'view shareholders',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'shareholders',
            'view-name'=>'view'
        ],
        [
            'name'=>'create shareholders',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'shareholders',
            'view-name'=>'create'
        ],
        [
            'name'=>'update shareholders',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'shareholders',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete shareholders',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'shareholders',
        // 	'view-name'=>'delete'
        // ],
        
        
        
        [
            'name'=>'view deductions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'deductions',
            'view-name'=>'view'
        ],
        [
            'name'=>'create deductions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'deductions',
            'view-name'=>'create'
        ],
        [
            'name'=>'update deductions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'deductions',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete deductions',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'deductions',
        // 	'view-name'=>'delete'
        // ],
        
        
        [
            'name'=>'view subsidiary companies',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'subsidiary companies',
            'view-name'=>'view'
        ],
        [
            'name'=>'create subsidiary companies',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'subsidiary companies',
            'view-name'=>'create'
        ],
        [
            'name'=>'update subsidiary companies',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'subsidiary companies',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete subsidiary companies',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'subsidiary companies',
        // 	'view-name'=>'delete'
        // ],
        
        [
            'name'=>'view business sectors',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business sectors',
            'view-name'=>'view'
        ],
        [
            'name'=>'create business sectors',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business sectors',
            'view-name'=>'create'
        ],
        [
            'name'=>'update business sectors',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business sectors setting',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete business sectors',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business sectors',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view other partners',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'other partners',
            'view-name'=>'view'
        ],
        [
            'name'=>'create other partners',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'other partners',
            'view-name'=>'create'
        ],
        [
            'name'=>'update other partners',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'other partners',
            'view-name'=>'update'
        ],
        // [
        //     'name'=>'delete other partners',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'other partners',
        // 	'view-name'=>'delete'
        // ],
        
        [
            'name'=>'view business units',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business units',
            'view-name'=>'view'
        ],
        [
            'name'=>'create business units',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business units',
            'view-name'=>'create'
        ],
        [
            'name'=>'update business units',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business units setting',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete business units',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'business units',
            'view-name'=>'delete'
        ],
    
        [
            'name'=>'view sales channels',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales channels',
            'view-name'=>'view'
        ],
        [
            'name'=>'create sales channels',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales channels',
            'view-name'=>'create'
        ],
        [
            'name'=>'update sales channels',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales channels setting',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete sales channels',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales channels',
            'view-name'=>'delete'
        ],
        
        
        
        [
            'name'=>'view sales persons',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales persons',
            'view-name'=>'view'
        ],
        [
            'name'=>'create sales persons',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales persons',
            'view-name'=>'create'
        ],
        [
            'name'=>'update sales persons',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales persons setting',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete sales persons',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'sales persons',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view branches',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'branches',
            'view-name'=>'view'
        ],
        [
            'name'=>'create branches',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'branches',
            'view-name'=>'create'
        ],
        [
            'name'=>'update branches',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'branches setting',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete branches',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'branches',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view cash expense categories',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'notification & settings',
            'view-name'=>'view cash expense categories'
        ],
        [
            'name'=>'view customer invoice past due notification',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'notification & settings',
            'view-name'=>'view customer invoice past due notification'
        ],
        [
            'name'=>'view customer invoice coming due notification',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer invoices notifications',
            'view-name'=>'view customer invoices'
        ],
        [
            'name'=>'view customer invoice current due notification',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                    'group'=>'customer invoices notifications',
            'view-name'=>'view customer invoices current due'
        ],
        
        [
            'name'=>'view supplier invoices past due notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'supplier invoices notifications',
            'view-name'=>'view supplier invoices past due'
        ],[
            'name'=>'view supplier invoices current due notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'supplier invoices notifications',
            'view-name'=>'view supplier invoices current due'
        ],[
            'name'=>'view supplier invoices coming due notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                            'group'=>'supplier invoices notifications',
            'view-name'=>'view supplier invoices coming due'
        ],[
            'name'=>'view cheque past due notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'receivable cheques notifications',
            'view-name'=>'view cheque past due'
        ],
        [
            'name'=>'view cheque current due notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
               'group'=>'receivable cheques notifications',
            'view-name'=>'view cheque current due'
        ],
        [
            'name'=>'view cheque under collection today notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
             'group'=>'receivable cheques notifications',
            'view-name'=>'view cheque under collection today'
        ],[
            'name'=>'view cheque under collection since days notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'receivable cheques notifications',
            'view-name'=>'view cheque under collection since days'
        ],
        [
            'name'=>'view current payable cheques notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'current payable cheques notifications',
            'view-name'=>'view current payable cheques'
        ],
        [
            'name'=>'view coming payable cheques notifications',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'coming payable cheques notifications',
            'view-name'=>'view coming payable cheques'
        ],
        [
            'name'=>'update cash & cheques opening balances',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'opening-balances',
            'view-name'=>'update cash & cheques'
        ],
        // [
        //     'name'=>'update lg opening balances',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'opening-balances',
        // 	'view-name'=>'update lg opening balances'
        // ],
        // [
        //     'name'=>'update lc opening balances',
        // 	'systems'=>[CASH_VERO],
        // 	'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
        // 	'group'=>'opening-balances',
        // 	'view-name'=>'update lc opening balances'
        // ]
        // ,
        [
            'name'=>'view customers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer contracts',
            'view-name'=>'view'
        ],
        [
            'name'=>'create customers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer contracts',
            'view-name'=>'create'
        ],
        [
            'name'=>'update customers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer contracts',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete customers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'customer contracts',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view suppliers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier contracts',
            'view-name'=>'view'
        ],
        [
            'name'=>'create suppliers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier contracts',
            'view-name'=>'create'
        ],
        [
            'name'=>'update suppliers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier contracts',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete suppliers contracts',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier contracts',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view safe statement report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'reports',
            'view-name'=>'safe statement'
        ],
        [
            'name'=>'view cash expense report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'reports',
            'view-name'=>'cash expense'
        ],
        [
            'name'=>'view partners statement report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'reports',
            'view-name'=>'partners statement'
        ],
        [
            'name'=>'view bank statement report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'reports',
            'view-name'=>'bank statement'
        ],[
            'name'=>'view lg by beneficiary name report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'reports',
            'view-name'=>'lg by beneficiary name report'
        ],
        [
            'name'=>'view lg by bank name report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'reports',
            'view-name'=>'lg by bank name report'
        ],
        [
            'name'=>'view lc & lg statement report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'reports',
            'view-name'=>'lc & lg statement'
        ],
        [
            'name'=>'view cash flow report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'reports',
            'view-name'=>'cash flow'
        ],
        [
            'name'=>'view contract cash flow report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'reports',
            'view-name'=>'contract cash flow'
        ],
        [
            'name'=>'view withdrawals settlement report',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'reports',
            'view-name'=>'withdrawals settlement'
        ],
        [
            'name'=>'view money received',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'money received',
            'view-name'=>'view'
        ],
        [
            'name'=>'review money received',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER],
            'group'=>'money received',
            'view-name'=>'review'
        ],
        [
            'name'=>'create money received',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'money received',
            'view-name'=>'create'
        ],
        [
            'name'=>'update money received',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'money received',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete money received',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'money received',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view supplier payment',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier payment',
            'view-name'=>'view'
        ],
        [
            'name'=>'review supplier payments',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER],
            'group'=>'supplier payment',
            'view-name'=>'review'
        ],
        [
            'name'=>'create supplier payment',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier payment',
            'view-name'=>'create'
        ],
        [
            'name'=>'update supplier payment',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier payment',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete supplier payment',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'supplier payment',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view cash expenses',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'cash expenses',
            'view-name'=>'view'
        ],
        [
            'name'=>'review cash expenses',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER],
                'group'=>'cash expenses',
            'view-name'=>'review'
        ],
        [
            'name'=>'create cash expenses',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
                'group'=>'cash expenses',
            'view-name'=>'create'
        ],
        
        [
            'name'=>'update cash expenses',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'cash expenses',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete cash expenses',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'cash expenses',
            'view-name'=>'delete'
        ],
        
        [
            'name'=>'view internal money transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'internal money transfer',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create internal money transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'internal money transfer',
            'view-name'=>'create'
        ],
        
        
        [
            'name'=>'update internal money transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'internal money transfer',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete internal money transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'internal money transfer',
            'view-name'=>'delete'
        ],
        
        [
            'name'=>'view lc settlement internal transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'lc settlement internal money transfer',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create lc settlement internal transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'lc settlement internal money transfer',
            'view-name'=>'create'
        ],
        
        
        [
            'name'=>'update lc settlement internal transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'lc settlement internal money transfer',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete lc settlement internal transfer',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'lc settlement internal money transfer',
            'view-name'=>'delete'
        ],
        
        [
            'name'=>'view buy or sell currency',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'buy or sell currency',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create buy or sell currency',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'buy or sell currency',
            'view-name'=>'create'
        ],
        
        [
            'name'=>'update buy or sell currency',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'buy or sell currency',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete buy or sell currency',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'buy or sell currency',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view foreign exchange rate',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'foreign exchange rate',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create foreign exchange rate',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'foreign exchange rate',
            'view-name'=>'create'
        ],
        
        
        [
            'name'=>'update foreign exchange rate',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'foreign exchange rate',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete foreign exchange rate',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'foreign exchange rate',
            'view-name'=>'delete'
        ],
        
     
        [
            'name'=>'view financial institutions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'financial institutions',
            'view-name'=>'view'
        ],
        [
            'name'=>'create financial institutions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'financial institutions',
            'view-name'=>'create'
        ],
        [
            'name'=>'update financial institutions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'financial institutions',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete financial institutions',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'financial institutions',
            'view-name'=>'delete'
        ],
        /////////
        
        
        [
            'name'=>'view fully secured overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'fully secured overdraft',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create fully secured overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'fully secured overdraft',
            'view-name'=>'create'
        ],
        
        [
            'name'=>'update fully secured overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'fully secured overdraft',
            'view-name'=>'update'
        ],
        
        [
            'name'=>'delete fully secured overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'fully secured overdraft',
            'view-name'=>'delete'
        ],
        [
            'name'=>'view clean overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'clean overdraft',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create clean overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'clean overdraft',
            'view-name'=>'create'
        ],
        [
            'name'=>'update clean overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'clean overdraft',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete clean overdraft',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'clean overdraft',
            'view-name'=>'delete'
        ],
        
        
        
        [
            'name'=>'view overdraft against commercial paper',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against commercial paper',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create overdraft against commercial paper',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against commercial paper',
            'view-name'=>'create'
        ],
        
        [
            'name'=>'update overdraft against commercial paper',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against commercial paper',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete overdraft against commercial paper',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against commercial paper',
            'view-name'=>'delete'
        ],
        
        
        [
            'name'=>'view overdraft against assignment of contract',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against assignment of contract',
            'view-name'=>'view'
        ],
        
        [
            'name'=>'create overdraft against assignment of contract',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against assignment of contract',
            'view-name'=>'create'
        ],
        
        [
            'name'=>'update overdraft against assignment of contract',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against assignment of contract',
            'view-name'=>'update'
        ],
        [
            'name'=>'delete overdraft against assignment of contract',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN,User::MANAGER,User::USER],
            'group'=>'overdraft against assignment of contract',
            'view-name'=>'delete'
        ],
        ////
  
       
        [
            'name'=>'view super admin',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN],
            'group'=>'super admin permissions',
            'view-name'=>'view'
        ],
        [
            'name'=>'view company admin',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN],
            'group'=>'company admin permissions',
            'view-name'=>'view'
        ],
        [
            'name'=>'create company admin',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN],
            'group'=>'company admin permissions',
            'view-name'=>'create'
        ],
        [
            'name'=>'view managers',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN],
             'group'=>'managers permissions',
            'view-name'=>'view'
        ],
        [
            'name'=>'create manager',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN],
                     'group'=>'managers permissions',
            'view-name'=>'create'
        ],
        [
            'name'=>'view users',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN],
                     'group'=>'users permissions',
            'view-name'=>'view'
        ],
        [
            'name'=>'create user',
            'systems'=>[CASH_VERO],
            'default-roles'=>[User::SUPER_ADMIN,User::COMPANY_ADMIN],
             'group'=>'users permissions',
            'view-name'=>'create'
        ]
    ];

  
    if (count($systemsNames)) {
        return filterPermissionForSystemName($permissions, $systemsNames);
    }
    return $permissions;
}

}