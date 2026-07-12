<?php
namespace App\Helpers;

use App\Models\CashVeroBusinessSector;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HGlobal
{
	public static function getFieldTypeAndClassFromTitle($modelName,string $title):array
{

    if ($modelName === 'ContractLoanSchedule' && isDraweeBankImportHeading($title)) {
        $bankNames = getCompanyDraweeBankNames((int) getCurrentCompanyId());

        return [
            'type' => 'select',
            'class' => 'js-contract-loan-drawee-bank',
            'default_value' => '',
            'name' => 'drawee_bank',
            'options' => ['' => __('Select')] + (array_combine($bankNames, $bankNames) ?: []),
        ];
    }

    if ($modelName === 'ContractLoanSchedule' && (
        Str::contains($title, 'Account Number')
        || $title === __('Account Number')
        || mb_strtolower(trim($title)) === 'account_number'
        || $title === 'رقم الحساب'
    )) {
        return [
            'type' => 'select',
            'class' => 'js-contract-loan-account-number',
            'default_value' => '',
            'name' => 'account_number',
            'options' => ['' => __('Select')],
        ];
    }

    if ($modelName === 'ContractLoanSchedule' && (
        Str::contains($title, [
            'Beginning Balance',
            'Cheque Amount',
            'Interest Amount',
            'Principle Amount',
            'End Balance',
            __('Beginning Balance'),
            __('Cheque Amount'),
            __('Interest Amount'),
            __('Principle Amount'),
            __('End Balance'),
        ])
    )) {
        return [
            'type' => 'numeric',
            'class' => 'only-greater-than-or-equal-zero-allowed',
            'default_value' => 0,
        ];
    }

    if (Str::contains($title, 'Customer Name') && $modelName != 'SalesGathering') {
        return [
            'type'=>'select',
            'class'=>'',
            'default_value'=>'',
            'name'=>'customer_id',
            'options'=>Partner::where('company_id', getCurrentCompanyId())->where('is_customer', 1)->pluck('name', 'id')->toArray(),
        ];
    }
    if (Str::contains($title, 'Sales Order Number')) {
        return [
            'type'=>'select',
            'class'=>'',
            'default_value'=>'',
            'name'=>'sales_order_id',
            'options'=>[],
        ];
    }
    if (Str::contains($title, 'Project Name')) {
        return [
            'type'=>'select',
            'class'=>'',
            'default_value'=>'',
            
            'name'=>'contract_id',
            'options'=>[],
        ];
    }
    if (Str::contains($title, 'Supplier Name')) {
        return [
            'type'=>'select',
            'class'=>'',
            'default_value'=>'',
            'name'=>'supplier_id',
            'options'=>Partner::where('company_id', getCurrentCompanyId())->where('is_supplier', 1)->pluck('name', 'id')->toArray(),
        ];
    }
    if (Str::contains($title, 'Business Sector') && $modelName != 'SalesGathering') {
        return [
            'type'=>'select',
            'class'=>'',
            'default_value'=>'',
            'name'=>'business_sector',
            'options'=>CashVeroBusinessSector::where('company_id', getCurrentCompanyId())->pluck('name', 'name')->toArray(),
        ];
    }
    
    // if(Str::contains($title, 'Supplier Name') ) {
    // 	return [
    // 		'type'=>'select',
    // 		'class'=>'',
    // 		'default_value'=>'',
    // 		'options'=>Partner::where('company_id',getCurrentCompanyId())->where('is_customer',1)->get()
    // 	];
    // }
    if (Str::contains($title, 'date') || Str::contains($title, 'Date') || Str::contains($title, 'Estimated')) {
        return [
            'type'=>'date',
            'class'=>'',
            'default_value'=>now()
        ];

    }
    if (isImportIdentifierHeading($title)) {
        return [
            'type'=>'text',
            'class'=>'',
            'default_value'=>''
        ];
    }
    if (Str::contains($title, getNumericExportFields())) {
        return [
            'type'=>'numeric',
            'class'=>'only-greater-than-or-equal-zero-allowed',
            'default_value'=>0
        ];
    }
    if (Str::contains($title, getNumericWithNegativeAllowedExportFields())) {
        return [
            'type'=>'numeric',
            'class'=>'only-numeric-allowed',
            'default_value'=>0
        ];
    }
    
    return [
        'type'=>'text',
        'class'=>'',
        'default_value'=>''
    ];
}
}
