<?php

namespace App\Models;

use App\Services\Api\OdooService;
use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCreatedAt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property int|null $odoo_id
 * @property int $company_id
 * @property string $name
 * @property int $is_customer
 * @property int $is_supplier
 * @property int $is_employee
 * @property int $is_shareholder
 * @property int $is_other_partner
 * @property int $is_subsidiary_company
 * @property int $is_tax هنضيف حساب
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $due_to_chart_of_account_number_odoo_code خاصين بال subsidiary
 * @property int|null $due_to_chart_of_account_number_odoo_id خاصين بال subsidiary
 * @property int|null $due_from_chart_of_account_number_odoo_code خاصين بال subsidiary
 * @property int|null $due_from_chart_of_account_number_odoo_id خاصين بال subsidiary
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerInvoice> $CustomerInvoice
 * @property-read int|null $customer_invoice_count
 * @property-read bool|null $customer_invoice_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupplierInvoice> $SupplierInvoice
 * @property-read int|null $supplier_invoice_count
 * @property-read bool|null $supplier_invoice_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contract> $contracts
 * @property-read int|null $contracts_count
 * @property-read bool|null $contracts_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyCustomers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyCustomersOrOtherPartners()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyEmployees()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyForCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyHasInvoicesWithCurrency(string $currencyName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyOtherPartners()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyShareholders()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlySubsidiaryCompanies()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlySuppliers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyTaxes()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyThatHaveContracts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner onlyThatHaveCustomerContracts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereDueFromChartOfAccountNumberOdooCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereDueFromChartOfAccountNumberOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereDueToChartOfAccountNumberOdooCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereDueToChartOfAccountNumberOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsCustomer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsEmployee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsOtherPartner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsShareholder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsSubsidiaryCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsSupplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereIsTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Partner whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Partner extends Model
{
    const PARTNERS = 'partners';
    const CUSTOMERS = 'customers';
    const SUPPLIERS = 'suppliers';
    const EMPLOYEES = 'employees';
    const SHAREHOLDERS = 'shareholders';
    const SUBSIDIARY_COMPANIES = 'subsidiary-companies';
    const OTHER_PARTNERS = 'other-partners';
    const TAXES = 'taxes';
    use HasCreatedAt,HasBasicStoreRequest;
	
    protected $dates = [
    ];
    public function contracts():HasMany
    {
        return $this->hasMany(Contract::class, 'partner_id', 'id');
    }

    protected $guarded = [];


  
    public function getId()
    {
        return $this->id ;
    }
    public function getOdooId():?int
    {
        return $this->odoo_id;
    }
    public function getName()
    {
        return $this->name ;
    }
    public function getCustomerName()
    {
		
		
        return $this->getName();
    }
    public function scopeOnlyCompany(Builder $query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    public function scopeOnlyThatHaveContracts(Builder $query)
    {
        return $query->has('contracts');
    }
	public function scopeOnlyThatHaveCustomerContracts(Builder $query)
    {
        return $query->whereHas('contracts', function (Builder $builder) {
            $builder->where('model_type', 'Customer');
        });
    }
    public function scopeOnlyHasInvoicesWithCurrency(Builder $query, string $currencyName)
    {
        return $query->whereHas('SupplierInvoice', function (Builder $builder) use ($currencyName) {
            $builder->where('currency', $currencyName);
        });
    }
    public function scopeOnlyForCompany(Builder $query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    public function scopeOnlyCustomers(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_customer', 1);
        });
    }
    public function scopeOnlyCustomersOrOtherPartners(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_customer', 1)->orWhere('is_other_partner', 1);
        });
    }
    public function scopeOnlySuppliers(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_supplier', 1);
        });
    }
    public function getTypeFormatted(string $partnerType):string
    {

        return [
            'is_customer'=>__('Customer'),
            'is_supplier'=>__('Supplier'),
            'is_employee'=>__('Employee'),
            'is_tax'=>__('Taxes'),
            'is_shareholder'=>__('Shareholders'),
            'is_subsidiary_company'=>__('Subsidiary Company'),
            'is_other_partner'=>__('Other Partner'),
        ][$partnerType];
    }
    public function scopeOnlyEmployees(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_employee', 1);
        });
    }
    public function scopeOnlyTaxes(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_tax', 1);
        });
    }
    public function scopeOnlyShareholders(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_shareholder', 1);
        });
    }
    public function scopeOnlySubsidiaryCompanies(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_subsidiary_company', 1);
        });
    }
    public function scopeOnlyOtherPartners(Builder $query)
    {
        return $query->where(function ($q) {
            $q->where('is_other_partner', 1);
        });
    }

    public function isCustomer()
    {
        return $this->is_customer == 1 ;
    }
    
    public function isSupplier()
    {
        return $this->is_supplier == 1 ;
    }

    public function isEmployee()
    {
        return $this->is_employee == 1 ;
    }
    public function isTax()
    {
        return $this->is_tax == 1 ;
    }
    public function isSubsidiaryCompany()
    {
        return $this->is_subsidiary_company == 1 ;
    }
    public function isShareholder()
    {
        return $this->is_shareholder == 1 ;
    }
    public function isOtherPartner()
    {
        return $this->is_other_partner == 1 ;
    }
    public function CustomerInvoice():HasMany
    {
        return $this->hasMany(CustomerInvoice::class, 'customer_id', 'id');
    }
    public function SupplierInvoice():HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'supplier_id', 'id');
    }
    public function updateNamesInAllTables(array $columnNames, string $oldPartnerName, string $newPartnerName, int $companyId, array $additionalWhere = [])
    {
        $tables = getTableNames();
        foreach ($tables as $tableName) {
            foreach ($columnNames as $columnName) {
                if (Schema::hasColumn($tableName, $columnName)) {
                    if ($tableName == 'sales_gathering') {
                        continue;
                    }
                    $query = DB::table($tableName)->where('company_id', $companyId)
                    ->where($columnName, $oldPartnerName);
            
                    if ($tableName == 'money_received' || $tableName == 'money_payments') {
                        
                        $query->where($additionalWhere[0], $additionalWhere[1], $additionalWhere[2]);
                    }
                    $query->update([$columnName=>$newPartnerName])
                    ;
                }
            }
            
        }
    }
    public static function getPartnerFromName(string $name, int $companyId):?self
    {
        return self::where('name', $name)->where('company_id', $companyId)->first();
    }
    public function getCustomerType()
    {
        foreach ($this->toArray() as $columnName => $colValue) {
            if (in_array($columnName, array_keys(getAllPartnerTypesForCustomers())) && $colValue == 1) {
                return $columnName;
            }
        }
        throw new \Exception('Custom Exception .. No Available Partner Type');
    }
    public function getSupplierType()
    {
        foreach ($this->toArray() as $columnName => $colValue) {
            if (in_array($columnName, array_keys(getAllPartnerTypesForSuppliers())) && $colValue == 1) {
                return $columnName;
            }
        }
        throw new \Exception('Custom Exception .. No Available Partner Type');
    }
    public static function getCustomersForCompany(int $companyId)
    {
        return Partner::where('company_id', $companyId)
        ->where('is_customer', 1)->orderBy('name')->pluck('name', 'id');
    }
    
    public static function getSuppliersForCompany(int $companyId)
    {
        return self::where('company_id', $companyId)->where('is_supplier', 1)->orderBy('name')->pluck('name', 'id');
        
    }
    public static function getSuppliersForCompanyFormattedForSelect(Company $company)
    {
        return self::where('company_id', $company->id)->where('is_supplier', 1)->orderBy('name', 'asc')->get()->formattedForSelect(true, 'getId', 'getName');
    }
    public static function findByOdooId(int $id, int $companyId)
    {
        return self::where('odoo_id', $id)->where('is_tax', 0)->where('company_id', $companyId)->first();
    }
    public static function findByName(string $name, int $companyId)
    {
        return self::where('name', $name)->where('is_tax', 0)->where('company_id', $companyId)->first();
    }
    public static function handlePartnerForOdoo($odooPartnerId, $odooPartnerName, $isCustomer, $isSupplier, $isEmployee, $isOtherPartner, $companyId):int
    {
        $partner = Partner::findByOdooId($odooPartnerId, $companyId);
        // if(is_null($partner)){
        // 	// $partner = Partner::findByName($odooPartnerName,$companyId);
        // 	// if($partner){
        // 		// $oldIsCustomer = $partner->is_customer;
        // 		// $oldIsSupplier = $partner->is_supplier;
        // 		// $oldIsEmployee = $partner->is_employee;
        // 		// $oldIsOtherPartner = $partner->is_other_partner;
        // 		$partner->update([
        // 			'odoo_id'=>$odooPartnerId,
        // 			'is_customer'=>$isCustomer,
        // 			'is_supplier'=>$isSupplier,
        // 			'is_employee'=>$isEmployee,
        // 			'is_other_partner'=>$isOtherPartner,
                        
        // 			// 'is_customer'=>$oldIsCustomer?:$isCustomer,
        // 			// 'is_supplier'=>$oldIsSupplier?:$isSupplier,
        // 			// 'is_employee'=>$oldIsEmployee?:$isEmployee,
        // 			// 'is_other_partner'=>$oldIsOtherPartner?:$isOtherPartner,
        // 		]);
        // 		return $partner->id;
        // 	// }
        // }
        if (is_null($partner)) {
            $partner = Partner::createNewForOdoo($odooPartnerId, $odooPartnerName, $companyId, $isCustomer, $isSupplier, $isEmployee, $isOtherPartner);
        } else {
            // $partner->update([
            // 	'name'=>$odooPartnerName,
            // 	'odoo_id'=>$odooPartnerId,
            // 	'is_customer'=>$isCustomer,
            // 	'is_supplier'=>$isSupplier ,
            // 	'is_employee'=>$isEmployee,
            // 	'is_other_partner'=>$isOtherPartner,
            // ]);
                
            if ($isSupplier) {
                $partner->update([
                    'is_supplier'=>1 ,
                    'odoo_id'=>$odooPartnerId,
                    'name'=>$odooPartnerName
                ]);
            }
            if ($isCustomer) {
                $partner->update([
                    'is_customer'=>1 ,
                    'odoo_id'=>$odooPartnerId,
                    'name'=>$odooPartnerName
                ]);
            }
            if ($isEmployee) {
                $partner->update([
                    'is_employee'=>1,
                    'odoo_id'=>$odooPartnerId,
                    'name'=>$odooPartnerName
                ]);
            }
            if ($isOtherPartner) {
                $partner->update([
                    'is_other_partner'=>1 ,
                    'odoo_id'=>$odooPartnerId,
                    'name'=>$odooPartnerName
                ]);
            }
        }
            
        return $partner->id ;
    }
    public static function createNewForOdoo(int $id, string $partnerName, int $companyId, int $isCustomer, int $isSupplier, int $isEmployee, int $isOtherPartner)
    {
        
       
        $partner = Partner::create([
            'odoo_id'=>$id ,
            'is_customer'=>$isCustomer ,
            'is_supplier'=>$isSupplier,
            'is_employee'=>$isEmployee,
            'is_other_partner'=>$isOtherPartner,
            'company_id'=>$companyId ,
            'name'=>$partnerName
        ]);
        return $partner;
    }
    public function dueToChartOfAccountNumberCode()
    {
        if (is_null($this->due_to_chart_of_account_number_odoo_code)) {
            throw new \Exception('Due To Chart Of Account Number Not Found .. Please Add It From Other Odoo Setting Form');
        }
        return  $this->due_to_chart_of_account_number_odoo_code;
    }
    public function dueToChartOfAccountNumberId()
    {
        if (!$this->due_to_chart_of_account_number_odoo_id) {
            throw new \Exception('Due To Chart Of Account Number Not Found .. Please Add It From Other Odoo Setting Form');
        }
        return  $this->due_to_chart_of_account_number_odoo_id;
    }
    public function dueFromChartOfAccountNumberCode()
    {
        if (!$this->due_from_chart_of_account_number_odoo_code) {
            throw new \Exception('Due To Chart Of Account Number Not Found .. Please Add It From Other Odoo Setting Form');
        }
        return  $this->due_from_chart_of_account_number_odoo_code;
    }
    public function dueFromChartOfAccountNumberId()
    {
        if (is_null($this->due_to_chart_of_account_number_odoo_id)) {
            throw new \Exception('Due To Chart Of Account Number Not Found .. Please Add It From Other Odoo Setting Form');
        }
        return  $this->due_to_chart_of_account_number_odoo_id;
    }
    public static function getTaxesNames():array
    {
        return  [
            'vat_taxes_code'=>'VAT Taxes',
            'credit_withhold_taxes_code'=>'Credit Withhold Taxes',
            'salary_taxes_code'=>'Salary Taxes',
            'social_insurance_code' => 'Social Insurance',
            'income_taxes_code'=>'Income Taxes',
            'real_estate_taxes_code'=>'Real Estate Taxes',
            'stamp_duty_taxes_code'=>'Stamp Duty Taxes',
            'other_taxes_code'=>'Other Taxes',
            'takaful_code'=>'Takaful Contribution Tax',
            'tax_for_victims_code'=>'Tax for the Support of Victims Fund'
        ];
        ;
    }
    public static function handleTaxesColumnsToPartnerTable(Company $company)
    {
        foreach (self::getTaxesNames() as $name) {
            $row = Partner::where('company_id', $company->id)->where('is_tax', 1)->where('name', $name)->first();
            $data = [
                'name'=>$name ,
                'is_tax'=>1 ,
                'is_customer'=>0,
                'is_supplier'=>0 ,
                'company_id'=>$company->id,
            ];
            if ($row) {
                $row->update($data);
            } else {
                Partner::create($data);
            }
        }
    }
    public function syncAccounts(Request $request, Company $company)
    {
        if (!$company->hasOdooIntegrationCredentials()) {
            return ;
        }
        $odooService = new OdooService($company);
        $code = $request->input('due_from_chart_of_account_number_odoo_code') ;
        $this->due_from_chart_of_account_number_odoo_code = $code;
        $journal = $odooService->fetchData('account.account', ['code','name'], [[['code','=',$code]]]);
        $odooId = $journal[0]['id'] ?? null ;
        if ($odooId) {
            $this->due_from_chart_of_account_number_odoo_id  = $odooId;
        }
        $code = $request->input('due_to_chart_of_account_number_odoo_code') ;
        $this->due_to_chart_of_account_number_odoo_code = $code;
        $journal = $odooService->fetchData('account.account',['code','name'],[[['code','=',$code]]]);
        $odooId = $journal[0]['id'] ?? null ;
        if ($odooId) {
            $this->due_to_chart_of_account_number_odoo_id  = $odooId;
        }
        $this->save();
            
    }
    

}
