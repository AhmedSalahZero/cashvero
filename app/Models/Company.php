<?php

namespace App\Models;

use App\Formatter\Select2Formatter;
use App\Helpers\HAuth;


use App\NotificationSetting;
use App\OdooSetting;
use App\Traits\HasBasicStoreRequest;
use App\Traits\StaticBoot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;


/**
 * @property int $id
 * @property int|null $odoo_id
 * @property string|null $odoo_integration_start_date
 * @property string|null $opening_balance_date
 * @property string|null $labeling_logo
 * @property string|null $labeling_report_title
 * @property string|null $labeling_stamp
 * @property string|null $labeling_logo_3
 * @property string|null $labeling_logo_2
 * @property string|null $labeling_logo_1
 * @property array<array-key, mixed>|null $labeling_print_headers
 * @property string|null $no_rows_for_each_page_labeling
 * @property string|null $print_labeling_type
 * @property array<array-key, mixed>|null $generate_labeling_code_fields
 * @property int|null $labeling_use_client_logo
 * @property string|null $labeling_client_logo
 * @property string|null $labeling_pagination_per_page
 * @property string|null $labeling_type
 * @property string|null $qrcode_height
 * @property string|null $qrcode_width
 * @property string|null $label_height
 * @property string|null $label_width
 * @property string|null $logo_width
 * @property string|null $labeling_paper_size
 * @property array<array-key, mixed> $name
 * @property string $sub_of
 * @property int $is_caching_now
 * @property string|null $main_functional_currency
 * @property int|null $updated_by
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string|null $odoo_db_url
 * @property string|null $odoo_db_name
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BuyOrSellCurrency> $bankToBankBuyOrSellCurrencies
 * @property-read int|null $bank_to_bank_buy_or_sell_currencies_count
 * @property-read bool|null $bank_to_bank_buy_or_sell_currencies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InternalMoneyTransfer> $bankToBankInternalMoneyTransfers
 * @property-read int|null $bank_to_bank_internal_money_transfers_count
 * @property-read bool|null $bank_to_bank_internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcSettlementInternalMoneyTransfer> $bankToLcSettlementInternalMoneyTransfers
 * @property-read int|null $bank_to_lc_settlement_internal_money_transfers_count
 * @property-read bool|null $bank_to_lc_settlement_internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BuyOrSellCurrency> $bankToSafeBuyOrSellCurrencies
 * @property-read int|null $bank_to_safe_buy_or_sell_currencies_count
 * @property-read bool|null $bank_to_safe_buy_or_sell_currencies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InternalMoneyTransfer> $bankToSafeInternalMoneyTransfers
 * @property-read int|null $bank_to_safe_internal_money_transfers_count
 * @property-read bool|null $bank_to_safe_internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashVeroBranch> $branches
 * @property-read int|null $branches_count
 * @property-read bool|null $branches_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashVeroBusinessSector> $businessSectors
 * @property-read int|null $business_sectors_count
 * @property-read bool|null $business_sectors_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashVeroBusinessUnit> $businessUnits
 * @property-read int|null $business_units_count
 * @property-read bool|null $business_units_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BuyOrSellCurrency> $buyOrSellCurrencies
 * @property-read int|null $buy_or_sell_currencies_count
 * @property-read bool|null $buy_or_sell_currencies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashExpense> $cashExpenses
 * @property-read int|null $cash_expenses_count
 * @property-read bool|null $cash_expenses_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashProjection> $cashProjects
 * @property-read int|null $cash_projects_count
 * @property-read bool|null $cash_projects_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashflowReport> $cashflowReports
 * @property-read int|null $cashflow_reports_count
 * @property-read bool|null $cashflow_reports_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CleanOverdraft> $cleanOverdrafts
 * @property-read int|null $clean_overdrafts_count
 * @property-read bool|null $clean_overdrafts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contract> $contracts
 * @property-read int|null $contracts_count
 * @property-read bool|null $contracts_exists
 * @property-read \App\Models\CustomerOpeningBalance|null $customerOpeningBalance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $customers
 * @property-read int|null $customers_count
 * @property-read bool|null $customers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Deduction> $deductions
 * @property-read int|null $deductions_count
 * @property-read bool|null $deductions_exists
 * @property-read int|null $departments_count
 * @property-read bool|null $departments_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $employees
 * @property-read int|null $employees_count
 * @property-read bool|null $employees_exists
 * @property-read int|null $existing_branches_count
 * @property-read bool|null $existing_branches_exists
 * @property-read int|null $expense_names_count
 * @property-read bool|null $expense_names_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FinancialInstitution> $financialInstitutions
 * @property-read int|null $financial_institutions_count
 * @property-read bool|null $financial_institutions_exists
 * @property-read int|null $fixed_asset_names_count
 * @property-read bool|null $fixed_asset_names_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraft> $fullySecuredOverdrafts
 * @property-read int|null $fully_secured_overdrafts_count
 * @property-read bool|null $fully_secured_overdrafts_exists
 * @property-read int|null $general_departments_count
 * @property-read bool|null $general_departments_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InterestRevenueAccount> $interestRevenuesAccounts
 * @property-read int|null $interest_revenues_accounts_count
 * @property-read bool|null $interest_revenues_accounts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InternalMoneyTransfer> $internalMoneyTransfers
 * @property-read int|null $internal_money_transfers_count
 * @property-read bool|null $internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LastUploadFileName> $lastUploadFileNames
 * @property-read int|null $last_upload_file_names_count
 * @property-read bool|null $last_upload_file_names_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcSettlementInternalMoneyTransfer> $lcSettlementInternalMoneyTransfers
 * @property-read int|null $lc_settlement_internal_money_transfers_count
 * @property-read bool|null $lc_settlement_internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditFacility> $letterOfCreditFacilities
 * @property-read int|null $letter_of_credit_facilities_count
 * @property-read bool|null $letter_of_credit_facilities_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditIssuance> $letterOfCreditIssuances
 * @property-read int|null $letter_of_credit_issuances_count
 * @property-read bool|null $letter_of_credit_issuances_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeFacility> $letterOfGuaranteeFacilities
 * @property-read int|null $letter_of_guarantee_facilities_count
 * @property-read bool|null $letter_of_guarantee_facilities_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeIssuance> $letterOfGuaranteeIssuances
 * @property-read int|null $letter_of_guarantee_issuances_count
 * @property-read bool|null $letter_of_guarantee_issuances_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Log> $logs
 * @property-read int|null $logs_count
 * @property-read bool|null $logs_exists
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read bool|null $media_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MediumTermLoan> $mediumTermLoans
 * @property-read int|null $medium_term_loans_count
 * @property-read bool|null $medium_term_loans_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyPayment> $moneyPayments
 * @property-read int|null $money_payments_count
 * @property-read bool|null $money_payments_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $moneyReceived
 * @property-read int|null $money_received_count
 * @property-read bool|null $money_received_exists
 * @property-read \App\NotificationSetting|null $notificationSetting
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read bool|null $notifications_exists
 * @property-read \App\OdooSetting|null $odooSetting
 * @property-read \App\Models\OpeningBalance|null $openingBalance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $otherPartners
 * @property-read int|null $other_partners_count
 * @property-read bool|null $other_partners_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContract> $overdraftAgainstAssignmentOfContracts
 * @property-read int|null $overdraft_against_assignment_of_contracts_count
 * @property-read bool|null $overdraft_against_assignment_of_contracts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaper> $overdraftAgainstCommercialPapers
 * @property-read int|null $overdraft_against_commercial_papers_count
 * @property-read bool|null $overdraft_against_commercial_papers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $partners
 * @property-read int|null $partners_count
 * @property-read bool|null $partners_exists
 * @property-read int|null $properties_count
 * @property-read bool|null $properties_exists
 * @property-read int|null $property_categories_count
 * @property-read bool|null $property_categories_exists
 * @property-read int|null $property_natures_count
 * @property-read bool|null $property_natures_exists
 * @property-read int|null $property_ownerships_count
 * @property-read bool|null $property_ownerships_exists
 * @property-read int|null $property_types_count
 * @property-read bool|null $property_types_exists
 * @property-read int|null $property_usage_statues_count
 * @property-read bool|null $property_usage_statues_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BuyOrSellCurrency> $safeToBankBuyOrSellCurrencies
 * @property-read int|null $safe_to_bank_buy_or_sell_currencies_count
 * @property-read bool|null $safe_to_bank_buy_or_sell_currencies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InternalMoneyTransfer> $safeToBankInternalMoneyTransfers
 * @property-read int|null $safe_to_bank_internal_money_transfers_count
 * @property-read bool|null $safe_to_bank_internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BuyOrSellCurrency> $safeToSafeBuyOrSellCurrencies
 * @property-read int|null $safe_to_safe_buy_or_sell_currencies_count
 * @property-read bool|null $safe_to_safe_buy_or_sell_currencies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InternalMoneyTransfer> $safeToSafeInternalMoneyTransfers
 * @property-read int|null $safe_to_safe_internal_money_transfers_count
 * @property-read bool|null $safe_to_safe_internal_money_transfers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashVeroSalesChannel> $salesChannels
 * @property-read int|null $sales_channels_count
 * @property-read bool|null $sales_channels_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashVeroSalesPerson> $salesPersons
 * @property-read int|null $sales_persons_count
 * @property-read bool|null $sales_persons_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $shareholders
 * @property-read int|null $shareholders_count
 * @property-read bool|null $shareholders_exists
 * @property-read int|null $studies_count
 * @property-read bool|null $studies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $subsidiaryCompanies
 * @property-read int|null $subsidiary_companies_count
 * @property-read bool|null $subsidiary_companies_exists
 * @property-read \App\Models\SupplierOpeningBalance|null $supplierOpeningBalance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $suppliers
 * @property-read int|null $suppliers_count
 * @property-read bool|null $suppliers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanySystem> $systems
 * @property-read int|null $systems_count
 * @property-read bool|null $systems_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Partner> $taxes
 * @property-read int|null $taxes_count
 * @property-read bool|null $taxes_exists
 * @property-read int|null $tenants_count
 * @property-read bool|null $tenants_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read bool|null $users_exists
 * @method static \Database\Factories\CompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereGenerateLabelingCodeFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereIsCachingNow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingClientLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingLogo1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingLogo2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingLogo3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingPaginationPerPage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingPaperSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingPrintHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingReportTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingStamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLabelingUseClientLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereLogoWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereMainFunctionalCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereNoRowsForEachPageLabeling($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereOdooDbName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereOdooDbUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereOdooIntegrationStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereOpeningBalanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company wherePrintLabelingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereQrcodeHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereQrcodeWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereSubOf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Company whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class Company extends Model implements HasMedia
{
    use
        StaticBoot,
        InteractsWithMedia ,
        Notifiable,
        HasBasicStoreRequest,
        HasFactory;
    protected $guarded = [];
    protected $connection ='mysql';
    public function getIdentifier():int
    {
	
        return $this->{$this->getRouteKeyName()};
    }
    public function getId()
    {
        return $this->getIdentifier();
    }
    protected $casts = ['name' => 'array','generate_labeling_code_fields'=>'array','labeling_print_headers'=>'array'];
    public function users():BelongsToMany
    {
        return $this->belongsToMany(User::class, 'companies_users');
    }
    // public function subCompanies():HasMany
    // {
    //     return $this->hasMany(Company::class, 'sub_of');
    // }
    // public function branches()
    // {
    //     return $this->hasMany(Branch::class);
    // }
    // public function getBranchesWithSectionsAttribute()
    // {
    //     $branches = [];
    //     foreach ($this->branches as $branch) {
    //         @count($branch->sections) == 0 ?: array_push($branches, $branch);
    //     }


    //     return $branches;
    // }

    public function exportableModelFields($modelName)
    {
        return $this->hasOne(CustomizedFieldsExportation::class)->where('model_name', $modelName);
    }

    public function getName(): string
    {
        return $this->name[App()->getLocale()];
    }
    public function logs()
    {
        return $this->hasMany(Log::class, 'company_id', 'id');
    }
    public function isCachingNow()
    {
        return $this->is_caching_now;
    }
    public function getMainFunctionalCurrency():string
    {
        return strtoupper($this->main_functional_currency) ?: __('Main Functional Currency');
    }
   

  
    public function notificationSetting()
    {
        return $this->hasOne(NotificationSetting::class, 'company_id', 'id');
    }
    public function odooSetting()
    {
        return $this->hasOne(OdooSetting::class, 'company_id', 'id');
    }
    
    public function getCustomerComingDuesInvoicesNotificationsDays():int
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getCustomerComingDuesInvoicesNotificationsDays() : NotificationSetting::CUSTOMER_COMING_DUES_INVOICES_NOTIFICATIONS_DAYS ;
    }
    public function getSupplierComingDuesInvoicesNotificationsDays():int
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getSupplierComingDuesInvoicesNotificationsDays() : NotificationSetting::SUPPLIER_COMING_DUES_INVOICES_NOTIFICATIONS_DAYS ;
    }
    public function getCustomerPastDuesInvoicesNotificationsDays():int
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getCustomerPastDuesInvoicesNotificationsDays() : NotificationSetting::CUSTOMER_PAST_DUES_INVOICES_NOTIFICATIONS_DAYS ;
    }
    public function getSupplierPastDuesInvoicesNotificationsDays():int
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getSupplierPastDuesInvoicesNotificationsDays() : NotificationSetting::SUPPLIER_PAST_DUES_INVOICES_NOTIFICATIONS_DAYS ;
    }
    public function getChequesInSafeNotificationDays():int
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getChequesInSafeNotificationsDays() : NotificationSetting::CHEQUES_IN_SAFE_NOTIFICATIONS_DAYS ;
    }
    public function getComingReceivableChequesNotificationDays()
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getComingReceivableChequesNotificationsDays() : NotificationSetting::COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS ;
    }
    public function getComingPayableChequeNotificationDays()
    {
        $notificationSetting = $this->notificationSetting ;
        return  $notificationSetting  ? $notificationSetting->getComingPayableChequeNotificationDays() : NotificationSetting::COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS ;
    }
    public function letterOfGuaranteeIssuances():HasMany
    {
        return $this->hasMany(LetterOfGuaranteeIssuance::class, 'company_id', 'id')->orderByRaw("case status when 'cancelled' then 2 else 1 end , renewal_date asc ");
    }
    public function letterOfCreditIssuances():HasMany
    {
        return $this->hasMany(LetterOfCreditIssuance::class, 'company_id', 'id');
    }
    public function openingBalance():HasOne
    {
        return $this->hasOne(OpeningBalance::class, 'company_id');
    }
    public function customerOpeningBalance():HasOne
    {
        return $this->hasOne(CustomerOpeningBalance::class, 'company_id');
    }
    public function supplierOpeningBalance():HasOne
    {
        return $this->hasOne(SupplierOpeningBalance::class, 'company_id');
    }
   
    public function contracts():HasMany
    {
        return $this->hasMany(Contract::class, 'company_id', 'id');
    }
    public function lcSettlementInternalMoneyTransfers():HasMany
    {
        return $this->hasMany(LcSettlementInternalMoneyTransfer::class, 'company_id', 'id');
    }
    public function internalMoneyTransfers():HasMany
    {
        return $this->hasMany(InternalMoneyTransfer::class, 'company_id', 'id');
    }
    public function bankToBankInternalMoneyTransfers():HasMany
    {
        return $this->internalMoneyTransfers()->where('type', InternalMoneyTransfer::BANK_TO_BANK);
    }
    public function safeToBankInternalMoneyTransfers():HasMany
    {
        return $this->internalMoneyTransfers()->where('type', InternalMoneyTransfer::SAFE_TO_BANK);
    }
    public function bankToSafeInternalMoneyTransfers():HasMany
    {
        return $this->internalMoneyTransfers()->where('type', InternalMoneyTransfer::BANK_TO_SAFE);
    }
    public function safeToSafeInternalMoneyTransfers():HasMany
    {
        return $this->internalMoneyTransfers()->where('type', InternalMoneyTransfer::SAFE_TO_SAFE);
    }
    public function bankToLcSettlementInternalMoneyTransfers():HasMany
    {
        return $this->lcSettlementInternalMoneyTransfers()->where('type', LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT);
    }
    public function getBankToLcSettlementInternalMoneyTransfers(?string $startDate = null, ?string $endDate = null, $activeTab = null):HasMany
    {
        return $this->bankToLcSettlementInternalMoneyTransfers()
        ->whereBetween('transfer_date', [$startDate, $endDate])
        ->when($activeTab == LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT, function ($query) {
            $searchFieldName = Request('field');
            $value = Request('value');
            $from = Request('from');
            $to = Request('to');
            $query->when($searchFieldName == 'transfer_date', function () use ($query, $from, $to) {
                $query->whereBetween('transfer_date', [$from, $to]);
            })
            ->when($searchFieldName == 'from_bank_id', function () use ($query, $value) {
                $query->whereHas('fromBank', function ($query) use ($value) {
                    $query->where('name', 'like', '%' . $value . '%');
                });
            })
            ->when($searchFieldName == 'from_account_type_id', function () use ($query, $value) {
                $query->whereHas('fromAccountType', function ($query) use ($value) {
                    $query->where('name', 'like', '%' . $value . '%');
                });
            })
            ->when($searchFieldName == 'from_account_number', function () use ($query, $value) {
                $query->where('from_account_number', 'like', '%' . $value . '%');
            })
            ->when($searchFieldName == 'currency', function () use ($query, $value) {
                $query->where('currency', 'like', '%' . $value . '%');
            })
            ->when($searchFieldName == 'to_letter_of_credit_issuance_id', function () use ($query, $value) {
                $query->whereHas('letterOfCreditIssuance', function ($query) use ($value) {
                    $query->where('transaction_name', 'like', '%' . $value . '%');
                });
            });
        })
        ->with([
            'fromBank:id,name,type,bank_id',
            'fromAccountType:id,name_en,name_ar',
            'letterOfCreditIssuance:id,transaction_name',
        ])
        ->orderByDesc('transfer_date')
        ->orderByDesc('id');
    }
    
    
    
    
    
    
    public function buyOrSellCurrencies():HasMany
    {
        return $this->hasMany(BuyOrSellCurrency::class, 'company_id', 'id');
    }
    public function bankToBankBuyOrSellCurrencies():HasMany
    {
        return $this->buyOrSellCurrencies()->where('type', BuyOrSellCurrency::BANK_TO_BANK);
    }
    public function safeToBankBuyOrSellCurrencies():HasMany
    {
        return $this->buyOrSellCurrencies()->where('type', BuyOrSellCurrency::SAFE_TO_BANK);
    }
    public function bankToSafeBuyOrSellCurrencies():HasMany
    {
        return $this->buyOrSellCurrencies()->where('type', BuyOrSellCurrency::BANK_TO_SAFE);
    }
    public function safeToSafeBuyOrSellCurrencies():HasMany
    {
        return $this->buyOrSellCurrencies()->where('type', BuyOrSellCurrency::SAFE_TO_SAFE);
    }
    
    
    public function getHeadOfficeId()
    {
        return DB::table('branch')->where('company_id', $this->id)->orderByRaw('created_at asc')->first()->id;
    }
    public function cleanOverdrafts()
    {
        return $this->hasMany(CleanOverdraft::class, 'company_id', 'id');
    }
    public function fullySecuredOverdrafts()
    {
        return $this->hasMany(FullySecuredOverdraft::class, 'company_id', 'id');
    }
    public function overdraftAgainstCommercialPapers()
    {
        return $this->hasMany(OverdraftAgainstCommercialPaper::class, 'company_id', 'id');
    }
    public function overdraftAgainstAssignmentOfContracts()
    {
        return $this->hasMany(OverdraftAgainstAssignmentOfContract::class, 'company_id', 'id');
    }
    public function financialInstitutions()
    {
        return $this->hasMany(FinancialInstitution::class, 'company_id', 'id')
        ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
        ->selectRaw('financial_institutions.* , banks.name_en as bank_name')
        ->orderBy('bank_name');
    }
    public function getNotificationsBasedOnType($type):Collection
    {
        return $this->notifications->where('data.tap_type', $type);
    }
    public function cashExpenses()
    {
        return $this->hasMany(CashExpense::class, 'company_id', 'id');
    }
    public function getCashExpenseCashPayments(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->cashExpenses()->where('type', CashExpense::CASH_PAYMENT)->whereNull('opening_balance_id')->filterByPaymentDate($startDate, $endDate)
		->when($activeTab == CashExpense::CASH_PAYMENT, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'payment_date',function() use ($query,$from,$to){
				$query->whereBetween('payment_date', [$from, $to]);
			})
			->when($searchFieldName == 'paid_amount',function() use ($query,$value){
				$query->where('paid_amount', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'expense_name',function() use ($query,$value){
				$query->whereHas('cashExpenseCategoryName',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'delivery_branch_name',function() use ($query,$value){
				$query->whereHas('cashPayment.deliveryBranch',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			})
			// ->when($searchFieldName == 'payment_currency',function() use ($query,$value){
			// 	$query->where('payment_currency', 'like', '%'.$value.'%');
			// })
			->when($searchFieldName == 'receipt_number',function() use ($query,$value){
				$query->whereHas('cashPayment',function($query) use ($value){
					$query->where('receipt_number', 'like', '%'.$value.'%');
				});
			});
		})
		->with([
			'partner:id,name',
			'cashPayment.deliveryBranch:id,name',
			'cashExpenseCategoryName:id,name,cash_expense_category_id',
			'cashExpenseCategoryName.cashExpenseCategory:id,name',
		])
		->orderByDesc('payment_date');

    }
    public function getCashExpenseOutgoingTransfer(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->cashExpenses()->where('type', CashExpense::OUTGOING_TRANSFER)->filterByPaymentDate($startDate, $endDate)
		->when($activeTab == CashExpense::OUTGOING_TRANSFER, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'payment_date',function() use ($query,$from,$to){
				$query->whereBetween('payment_date', [$from, $to]);
			})
			->when($searchFieldName == 'paid_amount',function() use ($query,$value){
				$query->where('paid_amount', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'expense_name',function() use ($query,$value){
				$query->whereHas('cashExpenseCategoryName',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'delivery_branch_name',function() use ($query,$value){
				$query->whereHas('cashPayment.deliveryBranch',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'payment_currency',function() use ($query,$value){
				$query->where('payment_currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'receipt_number',function() use ($query,$value){
				$query->whereHas('cashPayment',function($query) use ($value){
					$query->where('receipt_number', 'like', '%'.$value.'%');
				});
			});
		})
		->with([
			'partner:id,name',
			'outgoingTransfer.deliveryBank.bank:id,view_name,name_en,name_ar',
			'cashExpenseCategoryName:id,name,cash_expense_category_id',
			'cashExpenseCategoryName.cashExpenseCategory:id,name',
		])
		->orderByDesc('payment_date') ;
    }
    public function getCashExpensePayableCheques(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->cashExpenses()->where('type', CashExpense::PAYABLE_CHEQUE)->filterByPaymentDate($startDate, $endDate)
		->when($activeTab == CashExpense::PAYABLE_CHEQUE, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'payment_date',function() use ($query,$from,$to){
				$query->whereBetween('payment_date', [$from, $to]);
			})
			->when($searchFieldName == 'paid_amount',function() use ($query,$value){
				$query->where('paid_amount', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'expense_name',function() use ($query,$value){
				$query->whereHas('cashExpenseCategoryName',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'delivery_branch_name',function() use ($query,$value){
				$query->whereHas('cashPayment.deliveryBranch',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'payment_currency',function() use ($query,$value){
				$query->where('payment_currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'receipt_number',function() use ($query,$value){
				$query->whereHas('cashPayment',function($query) use ($value){
					$query->where('receipt_number', 'like', '%'.$value.'%');
				});
			});
		})
		->with([
			'partner:id,name',
			'payableCheque:id,cash_expense_id,money_payment_id,status,cheque_number,delivery_bank_id,account_type,account_number,due_date',
			'payableCheque.deliveryBank.bank:id,view_name,name_en,name_ar',
			'cashExpenseCategoryName:id,name,cash_expense_category_id',
			'cashExpenseCategoryName.cashExpenseCategory:id,name',
		])
		->orderByDesc('payment_date') ;
    }
    
    /**
     * * For Money Payments
     */
      
    public function moneyPayments()
    {
        return $this->hasMany(MoneyPayment::class, 'company_id', 'id')->with(['settlements'])
        // ->where('company_id',getCurrentCompanyId())
        ;
    }
    
    public function getMoneyPaymentCashPayments(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->moneyPayments()->whereNull('advanced_opening_balance_id')->where('type', MoneyPayment::CASH_PAYMENT)
		->whereNull('opening_balance_id')->filterByDeliveryDate($startDate, $endDate)
		->when($activeTab == MoneyPayment::CASH_PAYMENT, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			// $dateFieldName = ($searchFieldName === 'due_date') ? 'due_date' : 'delivery_date';
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'delivery_date',function() use ($query,$from,$to){
				$query->whereBetween('delivery_date', [$from, $to]);
			})
			->when($searchFieldName == 'delivery_branch_name',function() use ($query,$value){
				$query->whereHas('cashPayment.deliveryBranch',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'payment_currency',function() use ($query,$value){
				$query->where('payment_currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'receipt_number',function() use ($query,$value){
				$query->whereHas('cashPayment',function($query) use ($value){
					$query->where('receipt_number', 'like', '%'.$value.'%');
				});
			});
		})
		->with([
			'partner:id,name',
			'cashPayment.deliveryBranch:id,name',
		
		])
		->orderByDesc('delivery_date');
		

    }
	// public function getMoneyPaymentCashPayments(?string $startDate = null, ?string $endDate = null):Collection
    // {
    //     return $this->moneyPayments->whereNull('advanced_opening_balance_id')->where('type', MoneyPayment::CASH_PAYMENT)->whereNull('opening_balance_id')->filterByDeliveryDate($startDate, $endDate)->sortByDesc('delivery_date') ;
    // }
	
    public function getMoneyPaymentOutgoingTransfer(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->moneyPayments()->whereNull('advanced_opening_balance_id')->where('type', MoneyPayment::OUTGOING_TRANSFER)->filterByDeliveryDate($startDate, $endDate)
		->when($activeTab == MoneyPayment::OUTGOING_TRANSFER, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'delivery_date',function() use ($query,$from,$to){
				$query->whereBetween('delivery_date', [$from, $to]);
			})
			->when($searchFieldName == 'payment_bank_name',function() use ($query,$value){
				$query->whereHas('outgoingTransfer.deliveryBank.bank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			})
			->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'payment_currency',function() use ($query,$value){
				$query->where('payment_currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'account_number',function() use ($query,$value){
				$query->whereHas('outgoingTransfer',function($query) use ($value){
					$query->where('account_number', 'like', '%'.$value.'%');
				});
			});
			
		}) 
		->with([
			'partner:id,name',
			'outgoingTransfer.deliveryBank.bank:id,view_name',
		])
		->orderByDesc('delivery_date') ;
    }
	// public function getMoneyPaymentOutgoingTransfer(?string $startDate = null, ?string $endDate = null):Collection
    // {
    //     return $this->moneyPayments->whereNull('advanced_opening_balance_id')->where('type', MoneyPayment::OUTGOING_TRANSFER)->filterByDeliveryDate($startDate, $endDate)->sortByDesc('delivery_date') ;
    // }
    // public function getMoneyPaymentPayableCheques(?string $startDate = null, ?string $endDate = null):Collection
    // {
    //     return $this->moneyPayments->whereNull('advanced_opening_balance_id')->where('type', MoneyPayment::PAYABLE_CHEQUE)->filterByDeliveryDate($startDate, $endDate)->filter(function (MoneyPayment $moneyPayment) {
    //         $payableCheque = $moneyPayment->payableCheque ;
    //         return $payableCheque && in_array($payableCheque->getStatus(), [PayableCheque::PENDING,PayableCheque::PAID]) ;
    //     })->sortByDesc('delivery_date')->values();
    // }
	public function getMoneyPaymentPayableCheques(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
{
    return $this->moneyPayments() // لاحظ الأقواس هنا للتعامل مع قاعدة البيانات مباشرة
        ->whereNull('advanced_opening_balance_id')
        ->where('type', MoneyPayment::PAYABLE_CHEQUE)
        ->filterByDeliveryDate($startDate, $endDate) // تأكد أن هذا الـ Scope يعمل على Query Builder
        ->whereHas('payableCheque', function ($query) {
            $query->whereIn('status', [PayableCheque::PENDING, PayableCheque::PAID]);
        })
		->when($activeTab == MoneyPayment::PAYABLE_CHEQUE, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'delivery_date',function() use ($query,$from,$to){
				$query->whereBetween('delivery_date', [$from, $to]);
			})
			->when($searchFieldName == 'due_date',function() use ($query,$from,$to){
				$query->whereHas('payableCheque',function($query) use ($from,$to){
					$query->whereBetween('due_date', [$from, $to]);
				});
			})
			->when($searchFieldName == 'payment_bank_name',function() use ($query,$value){
				$query->whereHas('payableCheque.deliveryBank.bank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			})
			->when($searchFieldName == 'cheque_number',function() use ($query,$value){
				$query->whereHas('payableCheque',function($query) use ($value){
					$query->where('cheque_number', 'like', '%'.$value.'%');
				});
			})
			// ->when($searchFieldName == 'cheque_status',function() use ($query,$value){
			// 	$query->whereHas('payableCheque',function($query) use ($value){
			// 		$query->where('status', 'like', '%'.$value.'%');
			// 	});
			// })
			->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'payment_currency',function() use ($query,$value){
				$query->where('payment_currency', 'like', '%'.$value.'%');
			})
			->when($searchFieldName == 'receipt_number',function() use ($query,$value){
				$query->whereHas('cashPayment',function($query) use ($value){
					$query->where('receipt_number', 'like', '%'.$value.'%');
				});
			});
		})
		->with([
			'partner:id,name',
			'payableCheque.deliveryBank.bank:id,view_name',
			// 'payableCheque.accountType',
		])
		// ->with(['payableCheque.deliveryBank', 'payableCheque.accountType', 'partner'])
        ->orderBy(
			PayableCheque::select('due_date')
				->whereColumn('payable_cheques.money_payment_id', 'money_payments.id')
				->limit(1)
		);
}

    
    public function mediumTermLoans()
    {
        return $this->hasMany(MediumTermLoan::class, 'company_id', 'id');
    }

    public function leasingCompanies()
    {
        return $this->hasMany(LeasingCompany::class, 'company_id', 'id');
    }

    public function leasingContracts()
    {
        return $this->hasMany(LeasingContract::class, 'company_id', 'id');
    }

    public function factoringCompanies()
    {
        return $this->hasMany(FactoringCompany::class, 'company_id', 'id');
    }

    public function factoringContracts()
    {
        return $this->hasMany(FactoringContract::class, 'company_id', 'id');
    }

    public function factoringTransactions()
    {
        return $this->hasMany(FactoringTransaction::class, 'company_id', 'id');
    }
    public function systems()
    {
        return $this->hasMany(CompanySystem::class, 'company_id', 'id');
    }
    public function hasSystem(string $systemName)
    {
        return in_array($systemName, $this->getSystemsNames());
    }
    public function getSystemsNames():array
    {
        return $this->systems->pluck('system_name')->toArray();
    }
  

	public function hasCashvero():bool
    {
        // return true;
        return in_array(CASH_VERO, $this->getSystemsNames())
        || (auth()->check() && auth()->user()->isSuperAdmin());
    }
    /**
     * ⚠️ REAL BUG FIXED HERE (2026-07-25): same root cause and same fix
     * as UsersAndPermissionsController::update() — `HAuth::getPermissions()`
     * is the source of truth for what CAN be assigned, but syncing that
     * list into the actual `permissions` table is a separate, manual
     * step (`refresh:permissions` Artisan command). If a permission was
     * added to that list without re-running that command, editing a
     * company's Systems here would throw
     * Spatie\Permission\Exceptions\PermissionDoesNotExist the same way
     * saving a user's individual permissions did.
     *
     * Fixed the same way: firstOrCreate any permission name about to be
     * assigned, before calling syncPermissions(). Purely additive — only
     * ever creates the specific permission name(s) actually needed here,
     * never deletes or touches any other permission or any other user.
     */
    public function syncPermissionForAllUser(array $systemsToPreserve, array $newSystemsToBeAdded):void
    {
        $permissionsNamesToBePreserve = array_column(HAuth::getPermissions($systemsToPreserve), 'name');
        $permissionsNamesToBeAdded = array_column(HAuth::getPermissions($newSystemsToBeAdded), 'name');

        foreach ($permissionsNamesToBeAdded as $permissionName) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach ($this->users as $user) {
            $currentUserPermissions = array_values(array_intersect($user->permissions->pluck('name')->toArray(), $permissionsNamesToBePreserve));
            $permissions = array_merge($currentUserPermissions, $permissionsNamesToBeAdded);
            $user->syncPermissions($permissions);
        }
    }
    public function partners()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->orderBy('name');
    }
    public function customers()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_customer', 1)->orderBy('name');
    }
    public function suppliers()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_supplier', 1)->orderBy('name');
    }
    public function employees()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_employee', 1)->orderBy('name');
    }
    public function taxes()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_tax', 1)->orderBy('name');
    }
    public function shareholders()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_shareholder', 1)->orderBy('name');
    }
    public function subsidiaryCompanies()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_subsidiary_company', 1)->orderBy('name');
    }
    public function otherPartners()
    {
        return $this->hasMany(Partner::class, 'company_id', 'id')->where('is_other_partner', 1)->orderBy('name');
    }
    public function businessSectors()
    {
        return $this->hasMany(CashVeroBusinessSector::class, 'company_id', 'id')->orderBy('name');
    }
    public function salesChannels()
    {
        return $this->hasMany(CashVeroSalesChannel::class, 'company_id', 'id')->orderBy('name');
    }
    public function salesPersons()
    {
        return $this->hasMany(CashVeroSalesPerson::class, 'company_id', 'id')->orderBy('name');
    }
    public function businessUnits()
    {
        return $this->hasMany(CashVeroBusinessUnit::class, 'company_id', 'id')->orderBy('name');
    }
    public function branches()
    {
        return $this->hasMany(CashVeroBranch::class, 'company_id', 'id')->orderBy('name');
    }
    public function financialInstitutionsBanks():Collection
    {
        return $this->financialInstitutions->where('type', 'bank') ;
    }
    public function financialInstitutionsLeasingCompanies():Collection
    {
        return $this->financialInstitutions->where('type', 'leasing_companies') ;
    }
    public function financialInstitutionsFactoringCompanies():Collection
    {
        return $this->financialInstitutions->where('type', 'factoring_companies') ;
    }
    public function financialInstitutionsMortgageCompanies():Collection
    {
        return $this->financialInstitutions->where('type', 'mortgage_companies') ;
    }
	public function getReceivedChequesInSafe(?string $startDate = null, ?string $endDate = null, $activeTab = null):HasMany
    {
	
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')
		->whereHas('cheque',function($query) {
			$query->where('status', Cheque::IN_SAFE);
		})
		->when($activeTab == MoneyReceived::CHEQUE, function ( $query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'cheque_number',function() use ($query,$value){
				$query->whereHas('cheque',function($query) use ($value){
					$query->where('cheque_number', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'drawee_bank_name',function() use ($query,$value){
				$query->whereHas('cheque.draweeBank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			});
			$query->when($searchFieldName == 'due_date',function() use ($query,$from,$to){
				$query->whereHas('cheque',function($query) use ($from,$to){
					$query->whereBetween('due_date', [$from, $to]);
				});
			});
			// $query->when($searchFieldName == 'cheque_status',function() use ($query,$value){
			// 	$query->whereHas('cheque',function($query) use ($value){
			// 		$query->where('status', $value);
			// 	});
			// });
			$query->when($searchFieldName == 'received_amount',function() use ($query,$value){
				$query->where('received_amount', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
		
		})
		
		->filterByReceivingDate($startDate, $endDate)
		->orderBy(
			Cheque::select('due_date')
				->whereColumn('cheques.money_received_id', 'money_received.id')
				->limit(1)
		)
		->with([
			'partner:id,name',
			'cheque.draweeBank:id,name_en,name_ar',
			'cheque.accountType:id,name_en,name_ar',
			'cheque.moneyReceived:id,receiving_date',
		])
		;
		// ->filter(function (MoneyReceived $moneyReceived) {
        //     $cheque = $moneyReceived->cheque ;
        //     return $cheque && in_array($cheque->getStatus(), [Cheque::IN_SAFE]) ;
        // })   
    }
	
    // public function getReceivedChequesInSafe(?string $startDate = null, ?string $endDate = null):Collection
    // {
    //     return $this->moneyReceived->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CHEQUE)->filterByReceivingDate($startDate, $endDate)->filter(function (MoneyReceived $moneyReceived) {
    //         $cheque = $moneyReceived->cheque ;
    //         return $cheque && in_array($cheque->getStatus(), [Cheque::IN_SAFE]) ;
    //     })->values();
        
    // }
    /**
     * * هي الشيكات اللي اترفضت ورجعتها الخزنة تاني وليكن مثلا بسبب ان حساب العميل مفيهوش فلوس حاليا
     */
    public function getReceivedRejectedChequesInSafe(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CHEQUE)
		->whereHas('cheque',function($query) {
			$query->where('status', Cheque::REJECTED);
		})
		->when($activeTab == MoneyReceived::CHEQUE_REJECTED, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'cheque_number',function() use ($query,$value){
				$query->whereHas('cheque',function($query) use ($value){
					$query->where('cheque_number', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'drawee_bank_name',function() use ($query,$value){
				$query->whereHas('cheque.draweeBank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			});
			$query->when($searchFieldName == 'due_date',function() use ($query,$from,$to){
				$query->whereHas('cheque',function($query) use ($from,$to){
					$query->whereBetween('due_date', [$from, $to]);
				});
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'account_number',function() use ($query,$value){
				$query->whereHas('cheque',function($query) use ($value){
					$query->where('account_number', 'like', '%'.$value.'%');
				});
			});
		})
		->filterByReceivingDate($startDate, $endDate)
		->orderBy(
			Cheque::select('due_date')
				->whereColumn('cheques.money_received_id', 'money_received.id')
				->limit(1)
		)
		->with(['partner:id,name', 'cheque.draweeBank:id,name_en,name_ar', 'cheque.accountType:id,name_en,name_ar', 'cheque.moneyReceived:id,receiving_date']);
    }
    
    public function getCollectedCheques(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CHEQUE)
            ->filterByReceivingDate($startDate, $endDate)
           ->whereHas('cheque',function($query) {
			$query->where('status', Cheque::COLLECTED);
		})
		->when($activeTab == MoneyReceived::CHEQUE_COLLECTED, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'cheque_number',function() use ($query,$value){
				$query->whereHas('cheque',function($query) use ($value){
					$query->where('cheque_number', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'drawee_bank_name',function() use ($query,$value){
				$query->whereHas('cheque.draweeBank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			});
			$query->when($searchFieldName == 'due_date',function() use ($query,$from,$to){
				$query->whereHas('cheque',function($query) use ($from,$to){
					$query->whereBetween('due_date', [$from, $to]);
				});
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'account_number',function() use ($query,$value){
				$query->whereHas('cheque',function($query) use ($value){
					$query->where('account_number', 'like', '%'.$value.'%');
				});
			});
			
		})
		->orderBy(
			Cheque::select('due_date')
				->whereColumn('cheques.money_received_id', 'money_received.id')
				->limit(1)
		)
		->with(['partner:id,name', 'cheque.draweeBank:id,name_en,name_ar', 'cheque.accountType:id,name_en,name_ar', 'cheque.moneyReceived:id,receiving_date']);
    }
    
    public function getReceivedChequesUnderCollection(?string $startDate = null, ?string $endDate = null,$activeTab = null):HasMany
    {
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CHEQUE)
            ->filterByReceivingDate($startDate, $endDate)
           ->whereHas('cheque',function($query) {
			$query->where('status', Cheque::UNDER_COLLECTION);
		})
		->when($activeTab == MoneyReceived::CHEQUE_UNDER_COLLECTION, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'drawee_bank_name',function() use ($query,$value){
				$query->whereHas('cheque.draweeBank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			});
			$query->when($searchFieldName == 'due_date',function() use ($query,$from,$to){
				$query->whereHas('cheque',function($query) use ($from,$to){
					$query->whereBetween('due_date', [$from, $to]);
				});
			});
			$query->when($searchFieldName == 'cheque_number',function() use ($query,$value){
				$query->whereHas('cheque',function($query) use ($value){
					$query->where('cheque_number', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
		})
		->filterByReceivingDate($startDate, $endDate)
		->orderBy(
			Cheque::select('due_date')
				->whereColumn('cheques.money_received_id', 'money_received.id')
				->limit(1)
		)
            ->with(['partner:id,name', 'cheque.draweeBank:id,name_en,name_ar', 'cheque.accountType:id,name_en,name_ar', 'cheque.moneyReceived:id,receiving_date']);
			
			// ->filter(function (MoneyReceived $moneyReceived) {
            //     $cheque = $moneyReceived->cheque;
            //     return $cheque && in_array($cheque->getStatus(), [Cheque::UNDER_COLLECTION]);
            // })->values();
			// // ->with(['partner:id,name', 'cheque.draweeBank:id,name_en,name_ar', 'cheque.accountType:id,name_en,name_ar', 'cheque.moneyReceived:id,receiving_date'])
    }
    public function getReceivedCashesInSafe(?string $startDate = null, ?string $endDate = null , $activeTab = null):HasMany
    {
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CASH_IN_SAFE)->whereNull('opening_balance_id')
		->when($activeTab == MoneyReceived::CASH_IN_SAFE, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'receiving_branch_name',function() use ($query,$value){
				$query->whereHas('cashInSafe.receivingBranch',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			});
			$query->when($searchFieldName == 'received_amount',function() use ($query,$value){
				$query->where('received_amount', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receipt_number',function() use ($query,$value){
				$query->whereHas('cashInSafe',function($query) use ($value){
					$query->where('receipt_number', 'like', '%'.$value.'%');
				});
			});
		})
		->filterByReceivingDate($startDate, $endDate)
		->orderByDesc('receiving_date')
		->with([
			'partner:id,name',
			'cashInSafe.receivingBranch:id,name'
		])
		;
    }
	// public function getReceivedCashesInSafe(?string $startDate = null, ?string $endDate = null):Collection
    // {
    //     return $this->moneyReceived->whereNull('advanced_opening_balance_id')->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CASH_IN_SAFE)->whereNull('opening_balance_id')->filterByReceivingDate($startDate, $endDate) ;
    // }
    public function getReceivedCashesInBank(?string $startDate = null, ?string $endDate = null, $activeTab = null):HasMany
    {
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::CASH_IN_BANK)
		->when($activeTab == MoneyReceived::CASH_IN_BANK, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'receiving_bank_name',function() use ($query,$value){
				$query->whereHas('cashInBank.receivingBank.bank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			});
			$query->when($searchFieldName == 'received_amount',function() use ($query,$value){
				$query->where('received_amount', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'account_number',function() use ($query,$value){
				$query->whereHas('cashInBank',function($query) use ($value){
					$query->where('account_number', 'like', '%'.$value.'%');
				});
			});
		})
		->filterByReceivingDate($startDate, $endDate)
		->orderByDesc('receiving_date')
		->with([
			'partner:id,name',
			'cashInBank.receivingBank.bank:id,view_name',
			'cashInBank.accountType:id,name_en,name_ar',
		])
		;
    }
    public function getReceivedTransfer(?string $startDate = null, ?string $endDate = null, $activeTab = null):HasMany
    {
        return $this->moneyReceived()->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::INCOMING_TRANSFER)
		->when($activeTab == MoneyReceived::INCOMING_TRANSFER, function ($query) {
			$searchFieldName = Request('field');
			$value = Request('value');
			$from = Request('from');
			$to = Request('to');
			$query->when($searchFieldName == 'partner_name',function() use ($query,$value){
				$query->whereHas('partner',function($query) use ($value){
					$query->where('name', 'like', '%'.$value.'%');
				});
			})
			->when($searchFieldName == 'receiving_date',function() use ($query,$from,$to){
				$query->whereBetween('receiving_date', [$from, $to]);
			});
			$query->when($searchFieldName == 'receiving_bank_name',function() use ($query,$value){
				$query->whereHas('incomingTransfer.receivingBank.bank',function($query) use ($value){
					$query->where(function($query) use ($value){
						$query->where('name_en', 'like', '%'.$value.'%')
						->orWhere('name_ar', 'like', '%'.$value.'%');
					});
				});
			});
			$query->when($searchFieldName == 'received_amount',function() use ($query,$value){
				$query->where('received_amount', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'currency',function() use ($query,$value){
				$query->where('currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'receiving_currency',function() use ($query,$value){
				$query->where('receiving_currency', 'like', '%'.$value.'%');
			});
			$query->when($searchFieldName == 'account_number',function() use ($query,$value){
				$query->whereHas('incomingTransfer',function($query) use ($value){
					$query->where('account_number', 'like', '%'.$value.'%');
				});
			});
		})
		->filterByReceivingDate($startDate, $endDate)
		->orderByDesc('receiving_date')
		->with([
			'partner:id,name',
			'incomingTransfer.receivingBank.bank:id,view_name',
			'incomingTransfer.accountType:id,name_en,name_ar',
		])
		;
    }
	// public function getReceivedTransfer(?string $startDate = null, ?string $endDate = null):Collection
    // {
    //     return $this->moneyReceived->whereNull('advanced_opening_balance_id')->where('type', MoneyReceived::INCOMING_TRANSFER)->filterByReceivingDate($startDate, $endDate) ;
    // }
    public function moneyReceived()
    {
        return $this->hasMany(MoneyReceived::class, 'company_id', 'id')->with(['settlements']);
        // ->where('company_id',getCurrentCompanyId())
        ;
    }
    public function deductions()
    {
        return $this->hasMany(Deduction::class, 'company_id', 'id');
    }
    
    public function getOdooDBUrl()
    {
        return $this->odoo_db_url;
    }
    public function getOdooDBName()
    {
        return $this->odoo_db_name;
    }
   
    public function hasOdooCredentials(): bool
    {
        return (bool) ($this->getOdooDBUrl() && $this->getOdooDBName());
    }

    /**
     * * $user اختياري: لو مبعتش بياخد اليوزر اللي عامل لوجن زي الأول
     * * بنبعته صراحةً لما نسأل عن يوزر تاني غير اللي في السيشن
     */
    public function hasOdooIntegrationCredentials(?User $user = null):bool
    {
		$user = $user ?: auth()->user();
		/**
		 * @var User|null $user
		 */
        return $this->hasOdooCredentials() && $user && $user->getOdooDBUserName() && $user->getOdooDBPassword();
    }
    
    public function lastUploadFileNames()
    {
        return $this->hasMany(LastUploadFileName::class, 'company_id');
    }
    public function addNewFileUploadFileNameFor(string $fileName, string $modelName):LastUploadFileName
    {
        return $this->lastUploadFileNames()->create([
            'name'=>$fileName,
            'status'=>LastUploadFileName::CURRENT,
            'company_id'=>$this->id,
            'model_name'=>$modelName,
        ]);
    }
    public function updateLastUploadFileNameStatus(string $modelName)
    {
        $lastFile = $this->lastUploadFileNames()
            ->where('status', LastUploadFileName::CURRENT)
            ->where('model_name', $modelName)
            ->latest('id')
            ->first();

        if (!$lastFile) {
            return null;
        }

        return $lastFile->update([
            'status' => LastUploadFileName::SUCCESS,
        ]);
    }
    public function getCurrentLastFileNameForModel(string $modelName)
    {
        $lastFile = $this->lastUploadFileNames->where('status', LastUploadFileName::CURRENT)
        ->where('model_name', $modelName)
        ->last();
        return $lastFile ? $lastFile->name : __('N/A');
    }
    public function getSuccessLastFileNameForModel(string $modelName)
    {
        $lastFile = $this->lastUploadFileNames->where('status', LastUploadFileName::SUCCESS)
        ->where('model_name', $modelName)
        ->last();
        return $lastFile ? $lastFile->name : __('N/A');
    }
    public function hasLastSuccessfullyUploadFileForModel(string $modelName)
    {
        return  $this->lastUploadFileNames()->where('status', LastUploadFileName::SUCCESS)
        ->where('model_name', $modelName)
        ->exists();
    }
    public function hasLastCurrentUploadFileForModel(string $modelName)
    {
        return  $this->lastUploadFileNames()->where('status', LastUploadFileName::CURRENT)
        ->where('model_name', $modelName)
        ->exists();
    }
    
    public function deleteAllOldLastUploadFileNamesFor(string $modelName, string $status):void
    {
        $this->lastUploadFileNames->where('model_name', $modelName)
        ->where('status', $status)
        ->each(function ($lastUploadFileName) {
            $lastUploadFileName->delete();
        });
    }

 
   
   
   
   

   
    

    
    public function cashflowReports():HasMany
    {
        return $this->hasMany(CashflowReport::class, 'company_id', 'id');
    }
    public function cashProjects()
    {
        return $this->hasMany(CashProjection::class)->where('cashflow_report_id', 0);
    }
    public function resetCashflowReport()
    {
        DB::table('weekly_cashflow_custom_due_invoices')
        ->where('cashflow_report_id', 0)
        ->where('company_id', $this->id)->delete();
        
        DB::table('weekly_cashflow_custom_past_due_schedules')
        ->where('cashflow_report_id', 0)
        ->where('company_id', $this->id)
        ->delete();

        DB::table('cash_projections')
        ->where('cashflow_report_id', '=', 0)->where('company_id', $this->id)->delete();
    }
    public function getIntegrationStartDate():?string
    {
        return $this->odoo_integration_start_date?:'2025-01-01';
    }
    public function withinIntegrationDate(string $date)
    {
        $odooIntegrationStartDate = $this->getIntegrationStartDate();
        return Carbon::make($odooIntegrationStartDate)->lessThanOrEqualTo(Carbon::make($date));
    }
    public function interestRevenuesAccounts():HasMany
    {
        return $this->hasMany(InterestRevenueAccount::class, 'company_id', 'id');
    }
  
    public function letterOfCreditFacilities()
    {
        return $this->hasMany(LetterOfCreditFacility::class, 'company_id', 'id');
    }public function letterOfGuaranteeFacilities()
    {
        return $this->hasMany(LetterOfGuaranteeFacility::class, 'company_id', 'id');
    }

 
  
   
   
    
}
