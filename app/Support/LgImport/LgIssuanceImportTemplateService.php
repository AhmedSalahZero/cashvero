<?php

namespace App\Support\LgImport;

use App\Enums\LgSources;
use App\Enums\LgTypes;
use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Models\TimeOfDeposit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class LgIssuanceImportTemplateService
{
    public const COMMON_COLUMNS = [
        'category_name',
        'transaction_name',
        'financial_institution_name',
        'lg_type',
        'lg_code',
        'partner_name',
        'contract_name',
        'purchase_order_number',
        'purchase_order_date',
        'transaction_reference',
        'transaction_date',
        'issuance_date',
        'lg_duration_months',
        'renewal_date',
        'lg_amount',
        'lg_currency',
        'lg_commission_rate',
        'lg_commission_amount',
        'min_lg_commission_fees',
        'issuance_fees',
        'lg_commission_interval',
        'lg_fees_and_commission_account_type_name',
        'lg_fees_and_commission_account_number',
        'user_comment',
    ];

    public static function columnsBySource(string $source): array
    {
        $sourceColumns = [
            LgSources::LG_FACILITY => [
                'lg_facility_name',
                'cash_cover_rate',
                'cash_cover_amount',
                'cash_cover_deducted_from_account_type_name',
                'cash_cover_deducted_from_account_number',
            ],
            LgSources::AGAINST_CD => [
                'cd_or_td_account_type_name',
                'cd_or_td_account_number',
            ],
            LgSources::AGAINST_TD => [
                'cd_or_td_account_type_name',
                'cd_or_td_account_number',
            ],
            LgSources::HUNDRED_PERCENTAGE_CASH_COVER => [
                'cash_cover_rate',
                'cash_cover_amount',
            ],
        ];

        return array_merge(self::COMMON_COLUMNS, $sourceColumns[$source] ?? []);
    }

    public static function templateToCanonicalColumnMap(): array
    {
        return [
            'financial_institution_name' => 'financial_institution_id',
            'partner_name' => 'partner_id',
            'contract_name' => 'contract_id',
            'purchase_order_number' => 'purchase_order_id',
            'lg_fees_and_commission_account_type_name' => 'lg_fees_and_commission_account_type',
            'lg_fees_and_commission_account_number' => 'lg_fees_and_commission_account_id',
            'lg_facility_name' => 'lg_facility_id',
            'cash_cover_deducted_from_account_type_name' => 'cash_cover_deducted_from_account_type',
            'cash_cover_deducted_from_account_number' => 'cash_cover_deducted_from_account_id',
            'cd_or_td_account_type_name' => 'cd_or_td_account_type_id',
            'cd_or_td_account_number' => 'cd_or_td_id',
        ];
    }

    public static function dropDownOptions(Company $company, string $source): array
    {
        $financialInstitutions = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $fiOptions = [];
        foreach ($financialInstitutions as $bank) {
            $fiOptions[(string) $bank->id] = $bank->getName();
        }

        $facilityOptions = [];
        foreach (LetterOfGuaranteeFacility::where('company_id', $company->id)->get() as $facility) {
            $facilityOptions[(string) $facility->id] = $facility->getName();
        }

        $partnerOptions = [];
        foreach (Partner::onlyCompany($company->id)->onlyCustomers()->get() as $partner) {
            $partnerOptions[(string) $partner->id] = $partner->getName();
        }

        $contractOptions = [];
        foreach (Contract::onlyForCompany($company->id)->get() as $contract) {
            $contractOptions[(string) $contract->id] = $contract->getName();
        }

        $purchaseOrderOptions = [];
        foreach (SalesOrder::onlyForCompany($company->id)->get() as $salesOrder) {
            $purchaseOrderOptions[(string) $salesOrder->id] = $salesOrder->so_number;
        }

        $accountTypeOptions = [];
        foreach (AccountType::all() as $accountType) {
            $accountTypeOptions[(string) $accountType->id] = $accountType->getName();
        }

        $accountOptions = [];
        foreach (FinancialInstitutionAccount::where('company_id', $company->id)->where('is_active', 1)->get() as $account) {
            $accountOptions[(string) $account->id] = $account->account_number;
        }

        return [
            'category_name' => self::asCanonicalOptions(LetterOfGuaranteeIssuance::getCategories()),
            'lg_type' => self::asCanonicalOptions(LgTypes::getAll()),
            'lg_commission_interval' => self::asCanonicalOptions(getCommissionInterval()),
            'financial_institution_name' => $fiOptions,
            'lg_facility_name' => $facilityOptions,
            'partner_name' => $partnerOptions,
            'contract_name' => $contractOptions,
            'purchase_order_number' => $purchaseOrderOptions,
            'lg_fees_and_commission_account_type_name' => $accountTypeOptions,
            'cash_cover_deducted_from_account_type_name' => $accountTypeOptions,
            'lg_fees_and_commission_account_number' => $accountOptions,
            'cash_cover_deducted_from_account_number' => $accountOptions,
            'cd_or_td_account_type_name' => $accountTypeOptions,
            'lg_currency' => getCurrencies(),
        ];
    }

    public static function normalizeRow(array $row, array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $normalized[$column] = $value === '' ? null : $value;
        }

        return $normalized;
    }

    public static function resolveTemplateRowToCanonical(Company $company, string $source, array $normalizedRow): array
    {
        $canonical = [];
        foreach ($normalizedRow as $column => $value) {
            $canonical[self::templateToCanonicalColumnMap()[$column] ?? $column] = $value;
        }

        $errors = [];
        $resolverMaps = self::buildResolverMaps($company);

        foreach (self::templateToCanonicalColumnMap() as $templateColumn => $canonicalColumn) {
            if (! array_key_exists($templateColumn, $normalizedRow) || $normalizedRow[$templateColumn] === null) {
                continue;
            }

            if ($templateColumn === 'cd_or_td_account_number') {
                continue;
            }

            $lookupMap = $resolverMaps[$templateColumn] ?? null;
            if (! is_array($lookupMap)) {
                continue;
            }

            $resolved = self::resolveWithMap($lookupMap, $normalizedRow[$templateColumn]);
            if (is_array($resolved) && isset($resolved['error'])) {
                $errors[$templateColumn][] = $resolved['error'];
                continue;
            }

            $canonical[$canonicalColumn] = $resolved;
        }

        if (in_array($source, [LgSources::AGAINST_CD, LgSources::AGAINST_TD], true) && ! isset($errors['cd_or_td_account_type_name']) && ! isset($errors['cd_or_td_account_number'])) {
            $accountTypeId = $canonical['cd_or_td_account_type_id'] ?? null;
            $accountNumber = $normalizedRow['cd_or_td_account_number'] ?? null;
            if ($accountTypeId && $accountNumber) {
                $accountType = AccountType::find($accountTypeId);
                if (! $accountType) {
                    $errors['cd_or_td_account_type_name'][] = __('Unknown account type.');
                } elseif ($accountType->isCertificateOfDeposit()) {
                    $cd = CertificatesOfDeposit::where('company_id', $company->id)->where('account_number', $accountNumber)->first();
                    if (! $cd) {
                        $errors['cd_or_td_account_number'][] = __('Unknown CD account number.');
                    } else {
                        $canonical['cd_or_td_id'] = $cd->id;
                    }
                } elseif ($accountType->isTimeOfDeposit()) {
                    $td = TimeOfDeposit::where('company_id', $company->id)->where('account_number', $accountNumber)->first();
                    if (! $td) {
                        $errors['cd_or_td_account_number'][] = __('Unknown TD account number.');
                    } else {
                        $canonical['cd_or_td_id'] = $td->id;
                    }
                } else {
                    $errors['cd_or_td_account_type_name'][] = __('Selected account type is not CD/TD.');
                }
            }
        }

        return [$canonical, $errors];
    }

    public static function validateRow(Company $company, string $source, array $row): array
    {
        $rules = [
            'category_name' => 'required|in:new-issuance,opening-balance',
            'transaction_name' => 'required|string',
            'financial_institution_id' => 'required|exists:financial_institutions,id',
            'lg_type' => 'required|in:'.implode(',', array_keys(LgTypes::getAll())),
            'lg_code' => 'required|string',
            'partner_id' => 'required|exists:partners,id',
            'issuance_date' => 'required|date',
            'lg_duration_months' => 'required|numeric|min:1',
            'renewal_date' => 'required|date',
            'lg_amount' => 'required|numeric|gt:0',
            'lg_currency' => 'required|string',
            'lg_commission_rate' => 'nullable|numeric|min:0',
            'lg_commission_amount' => 'nullable|numeric|min:0',
            'min_lg_commission_fees' => 'nullable|numeric|min:0',
            'issuance_fees' => 'nullable|numeric|min:0',
            'lg_commission_interval' => 'required|in:quarterly,annually',
            'lg_fees_and_commission_account_type' => 'required|exists:account_types,id',
            'lg_fees_and_commission_account_id' => 'required|exists:financial_institution_accounts,id',
        ];

        if ($source === LgSources::LG_FACILITY) {
            $rules['lg_facility_id'] = 'required|exists:letter_of_guarantee_facilities,id';
            $rules['cash_cover_rate'] = 'required|numeric|min:0';
            $rules['cash_cover_amount'] = 'required|numeric|min:0';
            $rules['cash_cover_deducted_from_account_type'] = 'required|exists:account_types,id';
            $rules['cash_cover_deducted_from_account_id'] = 'required|exists:financial_institution_accounts,id';
        }

        if (in_array($source, [LgSources::AGAINST_CD, LgSources::AGAINST_TD], true)) {
            $rules['cd_or_td_account_type_id'] = 'required|exists:account_types,id';
            $rules['cd_or_td_id'] = 'required|numeric';
        }

        if ($source === LgSources::HUNDRED_PERCENTAGE_CASH_COVER) {
            $rules['cash_cover_rate'] = 'required|numeric|min:0';
            $rules['cash_cover_amount'] = 'required|numeric|min:0';
        }

        $validator = Validator::make($row, $rules);

        $validator->after(function ($validator) use ($company, $row) {
            if (! empty($row['financial_institution_id']) && ! FinancialInstitution::where('id', $row['financial_institution_id'])->where('company_id', $company->id)->exists()) {
                $validator->errors()->add('financial_institution_id', __('Invalid value for this company scope.'));
            }
            if (! empty($row['partner_id']) && ! Partner::where('id', $row['partner_id'])->where('company_id', $company->id)->exists()) {
                $validator->errors()->add('partner_id', __('Invalid value for this company scope.'));
            }
            if (! empty($row['contract_id']) && ! Contract::where('id', $row['contract_id'])->where('company_id', $company->id)->exists()) {
                $validator->errors()->add('contract_id', __('Invalid value for this company scope.'));
            }
            if (! empty($row['purchase_order_id']) && ! SalesOrder::where('id', $row['purchase_order_id'])->where('company_id', $company->id)->exists()) {
                $validator->errors()->add('purchase_order_id', __('Invalid value for this company scope.'));
            }
        });

        return $validator->errors()->toArray();
    }

    protected static function buildResolverMaps(Company $company): array
    {
        $map = [
            'category_name' => self::buildKeyOrValueResolverMap(LetterOfGuaranteeIssuance::getCategories()),
            'lg_type' => self::buildKeyOrValueResolverMap(LgTypes::getAll()),
            'lg_commission_interval' => self::buildKeyOrValueResolverMap(getCommissionInterval()),
            'lg_currency' => self::buildKeyOrValueResolverMap(getCurrencies()),
            'financial_institution_name' => self::buildModelResolverMap(FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get()->map(function ($bank) {
                return ['value' => $bank->getName(), 'id' => $bank->id];
            })->all()),
            'partner_name' => self::buildModelResolverMap(Partner::onlyCompany($company->id)->onlyCustomers()->get()->map(function ($partner) {
                return ['value' => $partner->getName(), 'id' => $partner->id];
            })->all()),
            'contract_name' => self::buildModelResolverMap(Contract::onlyForCompany($company->id)->get()->map(function ($contract) {
                return ['value' => $contract->getName(), 'id' => $contract->id];
            })->all()),
            'purchase_order_number' => self::buildModelResolverMap(SalesOrder::onlyForCompany($company->id)->get()->map(function ($order) {
                return ['value' => (string) $order->so_number, 'id' => $order->id];
            })->all()),
            'lg_facility_name' => self::buildModelResolverMap(LetterOfGuaranteeFacility::where('company_id', $company->id)->get()->map(function ($facility) {
                return ['value' => $facility->getName(), 'id' => $facility->id];
            })->all()),
            'lg_fees_and_commission_account_type_name' => self::buildModelResolverMap(AccountType::all()->map(function ($type) {
                return ['value' => $type->getName(), 'id' => $type->id];
            })->all()),
            'cash_cover_deducted_from_account_type_name' => self::buildModelResolverMap(AccountType::all()->map(function ($type) {
                return ['value' => $type->getName(), 'id' => $type->id];
            })->all()),
            'cd_or_td_account_type_name' => self::buildModelResolverMap(AccountType::all()->map(function ($type) {
                return ['value' => $type->getName(), 'id' => $type->id];
            })->all()),
            'lg_fees_and_commission_account_number' => self::buildModelResolverMap(FinancialInstitutionAccount::where('company_id', $company->id)->where('is_active', 1)->get()->map(function ($account) {
                return ['value' => (string) $account->account_number, 'id' => $account->id];
            })->all()),
            'cash_cover_deducted_from_account_number' => self::buildModelResolverMap(FinancialInstitutionAccount::where('company_id', $company->id)->where('is_active', 1)->get()->map(function ($account) {
                return ['value' => (string) $account->account_number, 'id' => $account->id];
            })->all()),
        ];

        return $map;
    }

    protected static function buildKeyOrValueResolverMap(array $options): array
    {
        $map = [];
        foreach ($options as $key => $value) {
            $normalizedKey = self::normalizeLookupValue((string) $key);
            $normalizedValue = self::normalizeLookupValue((string) $value);
            $map[$normalizedKey] = (string) $key;
            $map[$normalizedValue] = (string) $key;
        }

        return $map;
    }

    protected static function buildModelResolverMap(array $pairs): array
    {
        $map = [];
        foreach ($pairs as $pair) {
            $normalized = self::normalizeLookupValue((string) $pair['value']);
            if (! isset($map[$normalized])) {
                $map[$normalized] = $pair['id'];
                continue;
            }
            $existing = $map[$normalized];
            $map[$normalized] = is_array($existing) ? array_unique(array_merge($existing, [$pair['id']])) : [$existing, $pair['id']];
        }

        return $map;
    }

    protected static function resolveWithMap(array $resolverMap, string $value)
    {
        $normalized = self::normalizeLookupValue($value);
        if (! array_key_exists($normalized, $resolverMap)) {
            return ['error' => __('Unknown value [:value]. Please choose from template dropdown list.', ['value' => $value])];
        }

        if (is_array($resolverMap[$normalized])) {
            return ['error' => __('Ambiguous value [:value]. Please choose a unique option.', ['value' => $value])];
        }

        return $resolverMap[$normalized];
    }

    protected static function normalizeLookupValue(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }

    public static function buildLocalExampleTemplateRow(Company $company, string $source, array $columns): array
    {
        $options = self::dropDownOptions($company, $source);
        $purchaseOrderSample = self::firstUniquePurchaseOrderNumber($company);
        $today = Carbon::today();
        $renewal = $today->copy()->addMonths(12);

        $defaults = [
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'transaction_name' => 'Sample LG Issuance',
            'lg_type' => LgTypes::BID_BOND,
            'lg_code' => 'LG-SAMPLE-001',
            'purchase_order_number' => $purchaseOrderSample,
            'purchase_order_date' => $purchaseOrderSample ? $today->format('Y-m-d') : null,
            'transaction_reference' => 'TRX-REF-001',
            'transaction_date' => $today->format('Y-m-d'),
            'issuance_date' => $today->format('Y-m-d'),
            'lg_duration_months' => 12,
            'renewal_date' => $renewal->format('Y-m-d'),
            'lg_amount' => 100000,
            'lg_currency' => 'EGP',
            'lg_commission_rate' => 1.5,
            'lg_commission_amount' => 1500,
            'min_lg_commission_fees' => 1000,
            'issuance_fees' => 500,
            'lg_commission_interval' => 'quarterly',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
            'user_comment' => 'Sample local row',
            'cd_or_td_account_number' => null,
        ];

        $row = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $defaults)) {
                $row[] = $defaults[$column];
                continue;
            }

            if (isset($options[$column]) && is_array($options[$column]) && count($options[$column])) {
                $row[] = array_values($options[$column])[0];
                continue;
            }

            $row[] = null;
        }

        return $row;
    }

    protected static function asCanonicalOptions(array $options): array
    {
        $result = [];
        foreach ($options as $key => $value) {
            $result[(string) $key] = (string) $key;
        }

        return $result;
    }

    protected static function firstUniquePurchaseOrderNumber(Company $company): ?string
    {
        $counts = [];
        foreach (SalesOrder::onlyForCompany($company->id)->get() as $order) {
            $number = (string) $order->so_number;
            if ($number === '') {
                continue;
            }
            $counts[$number] = ($counts[$number] ?? 0) + 1;
        }

        foreach ($counts as $number => $count) {
            if ($count === 1) {
                return $number;
            }
        }

        return null;
    }
}
