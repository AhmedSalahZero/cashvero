<?php

namespace App\Services\CompanyData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces real names on the cloned company only.
 * Amounts, dates, and invoice numbers are left as copied.
 */
class DemoCompanyAnonymizer
{
    /** @var array<int, string> */
    public const COMPANY_NAMES = [
        'شركة النيل للمقاولات',
        'شركة الأهرام للتوريدات',
        'مؤسسة القاهرة التجارية',
        'شركة الدلتا للصناعات',
        'شركة الإسكندرية للمقاولات',
        'مجموعة مصر للتجارة',
        'شركة الصعيد للتنمية',
        'شركة قناة السويس للخدمات',
    ];

    /** @var array<int, string> */
    public const PROJECT_NAMES = [
        'مشروع العاصمة الإدارية',
        'مشروع العلمين الجديدة',
        'مشروع مدينة نصر',
        'مشروع السادس من أكتوبر',
    ];

    /** @var array<int, string> */
    public const SITE_NAMES = [
        'موقع التجمع الخامس',
        'موقع مدينة نصر',
        'موقع الإسكندرية',
        'موقع أسيوط',
    ];

    /** @var array<int, string> */
    public const CONTRACT_NAMES = [
        'عقد توريد وتشغيل',
        'عقد أعمال مدنية',
        'عقد صيانة سنوية',
        'عقد توريد معدات',
    ];

    /** @var array<int, string> */
    public const SALES_PERSONS = [
        'محمد عبد الرحمن',
        'أحمد حسن علي',
        'محمود إبراهيم',
        'يوسف عبد الله',
    ];

    /** @var array<int, string> */
    public const BUSINESS_UNITS = [
        'وحدة القاهرة',
        'وحدة الإسكندرية',
        'وحدة الجيزة',
    ];

    /** @var array<int, string> */
    public const BUSINESS_SECTORS = [
        'المقاولات',
        'التوريدات',
        'الصناعات',
        'التجارة',
    ];

    public function anonymize(int $companyId): void
    {
        $partners = DB::table('partners')
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get();

        $counters = [];
        $byPartner = [];
        foreach ($partners as $partner) {
            $bucket = $this->bucket($partner);
            $counters[$bucket] = ($counters[$bucket] ?? 0) + 1;
            $n = $counters[$bucket];
            $index = $n - 1;
            $byPartner[(int) $partner->id] = [
                'partner' => $this->partnerLabel($bucket, $n),
                'company' => self::COMPANY_NAMES[$index % count(self::COMPANY_NAMES)],
                'project' => self::PROJECT_NAMES[$index % count(self::PROJECT_NAMES)],
                'site' => self::SITE_NAMES[$index % count(self::SITE_NAMES)],
                'contract' => self::CONTRACT_NAMES[$index % count(self::CONTRACT_NAMES)],
                'person' => self::SALES_PERSONS[$index % count(self::SALES_PERSONS)],
                'unit' => self::BUSINESS_UNITS[$index % count(self::BUSINESS_UNITS)],
                'sector' => self::BUSINESS_SECTORS[$index % count(self::BUSINESS_SECTORS)],
            ];

            DB::table('partners')->where('id', $partner->id)->update([
                'name' => $byPartner[(int) $partner->id]['partner'],
            ]);
        }

        $this->renameInvoices('customer_invoices', 'customer_id', 'customer_name', $companyId, $byPartner);
        $this->renameInvoices('supplier_invoices', 'supplier_id', 'supplier_name', $companyId, $byPartner);
    }

    /**
     * @param  object  $partner
     */
    private function bucket(object $partner): string
    {
        $customer = (int) ($partner->is_customer ?? 0) === 1;
        $supplier = (int) ($partner->is_supplier ?? 0) === 1;
        if ($customer && $supplier) {
            return 'both';
        }
        if ($customer) {
            return 'customer';
        }
        if ($supplier) {
            return 'supplier';
        }
        foreach ([
            'is_employee' => 'employee',
            'is_shareholder' => 'shareholder',
            'is_subsidiary_company' => 'subsidiary',
            'is_tax' => 'tax',
            'is_other_partner' => 'other',
        ] as $column => $bucket) {
            if ((int) ($partner->{$column} ?? 0) === 1) {
                return $bucket;
            }
        }

        return 'partner';
    }

    private function partnerLabel(string $bucket, int $n): string
    {
        return match ($bucket) {
            'both' => 'Customer / Supplier '.$n,
            'customer' => 'Customer '.$n,
            'supplier' => 'Supplier '.$n,
            'employee' => 'Employee '.$n,
            'shareholder' => 'Shareholder '.$n,
            'subsidiary' => 'Subsidiary '.$n,
            'tax' => 'Tax '.$n,
            'other' => 'Other Partner '.$n,
            default => 'Partner '.$n,
        };
    }

    /**
     * @param  array<int, array<string, string>>  $byPartner
     */
    private function renameInvoices(string $table, string $partnerColumn, string $nameColumn, int $companyId, array $byPartner): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->where('company_id', $companyId)->orderBy('id')->get(['id', $partnerColumn]);
        foreach ($rows as $row) {
            $partnerId = (int) $row->{$partnerColumn};
            $names = $byPartner[$partnerId] ?? null;
            if ($names === null) {
                continue;
            }

            $update = [];
            if (Schema::hasColumn($table, $nameColumn)) {
                $update[$nameColumn] = $names['company'];
            }
            foreach ([
                'project_name' => 'project',
                'site_name' => 'site',
                'contract_name' => 'contract',
                'sales_person' => 'person',
                'business_unit' => 'unit',
                'business_sector' => 'sector',
            ] as $column => $key) {
                if (Schema::hasColumn($table, $column)) {
                    $update[$column] = $names[$key];
                }
            }

            if ($update !== []) {
                DB::table($table)->where('id', $row->id)->update($update);
            }
        }
    }
}
