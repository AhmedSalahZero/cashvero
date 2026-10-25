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
        $replacements = [];
        foreach ($partners as $partner) {
            $bucket = $this->bucket($partner);
            $counters[$bucket] = ($counters[$bucket] ?? 0) + 1;
            $n = $counters[$bucket];
            $index = $n - 1;
            $id = (int) $partner->id;
            $byPartner[$id] = [
                'partner' => $this->partnerLabel($bucket, $n),
                'company' => self::COMPANY_NAMES[$index % count(self::COMPANY_NAMES)],
                'project' => self::PROJECT_NAMES[$index % count(self::PROJECT_NAMES)],
                'site' => self::SITE_NAMES[$index % count(self::SITE_NAMES)],
                'contract' => self::CONTRACT_NAMES[$index % count(self::CONTRACT_NAMES)],
                'person' => self::SALES_PERSONS[$index % count(self::SALES_PERSONS)],
                'unit' => self::BUSINESS_UNITS[$index % count(self::BUSINESS_UNITS)],
                'sector' => self::BUSINESS_SECTORS[$index % count(self::BUSINESS_SECTORS)],
            ];
            $commentName = in_array($bucket, ['both', 'customer', 'supplier'], true)
                ? $byPartner[$id]['company']
                : $byPartner[$id]['partner'];
            $this->rememberReplacement($replacements, (string) $partner->name, $commentName);

            DB::table('partners')->where('id', $partner->id)->update([
                'name' => $byPartner[$id]['partner'],
            ]);
        }

        $this->rememberInvoiceNames('customer_invoices', 'customer_id', 'customer_name', $companyId, $byPartner, $replacements);
        $this->rememberInvoiceNames('supplier_invoices', 'supplier_id', 'supplier_name', $companyId, $byPartner, $replacements);
        $this->renameInvoices('customer_invoices', 'customer_id', 'customer_name', $companyId, $byPartner);
        $this->renameInvoices('supplier_invoices', 'supplier_id', 'supplier_name', $companyId, $byPartner);
        $this->renameComments($companyId, $replacements);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function rememberReplacement(array &$replacements, string $old, string $new): void
    {
        $old = trim($old);
        if (mb_strlen($old) < 3 || $old === $new || isset($replacements[$old])) {
            return;
        }

        $replacements[$old] = $new;
    }

    /**
     * A comment may quote the invoice display name rather than partners.name.
     *
     * @param  array<int, array<string, string>>  $byPartner
     * @param  array<string, string>  $replacements
     */
    private function rememberInvoiceNames(string $table, string $partnerColumn, string $nameColumn, int $companyId, array $byPartner, array &$replacements): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $nameColumn)) {
            return;
        }

        $rows = DB::table($table)->where('company_id', $companyId)->get([$partnerColumn, $nameColumn]);
        foreach ($rows as $row) {
            $names = $byPartner[(int) $row->{$partnerColumn}] ?? null;
            if ($names === null) {
                continue;
            }
            $this->rememberReplacement($replacements, (string) $row->{$nameColumn}, $names['company']);
        }
    }

    /**
     * One pass over the original text. Longer old names are replaced first,
     * and the new text is not scanned again.
     *
     * @param  array<string, string>  $replacements
     */
    private function renameComments(int $companyId, array $replacements): void
    {
        if ($replacements === []) {
            return;
        }

        uksort($replacements, function (string $a, string $b): int {
            $byLength = mb_strlen($b) <=> mb_strlen($a);

            return $byLength !== 0 ? $byLength : strcmp($b, $a);
        });

        foreach ($this->commentColumns() as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)
                    ->where('company_id', $companyId)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->select(['id', $column])
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table, $column, $replacements) {
                        foreach ($rows as $row) {
                            $original = (string) $row->{$column};
                            $updated = $this->replaceNames($original, $replacements);
                            if ($updated !== $original) {
                                DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                            }
                        }
                    }, 'id');
            }
        }
    }

    /**
     * @param  array<string, string>  $replacements  longest old name first
     */
    private function replaceNames(string $text, array $replacements): string
    {
        $tokens = [];
        $i = 0;
        foreach ($replacements as $old => $new) {
            if (! str_contains($text, $old)) {
                continue;
            }
            $token = "\x1E".$i."\x1E";
            $i++;
            $text = str_replace($old, $token, $text);
            $tokens[$token] = $new;
        }

        return $tokens === [] ? $text : strtr($text, $tokens);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function commentColumns(): array
    {
        $database = DB::getDatabaseName();
        $companyTables = array_flip(getTableNamesThatHasColumn('company_id'));
        $rows = DB::table('information_schema.columns as columns')
            ->join('information_schema.tables as tables', function ($join) {
                $join->on('tables.table_schema', '=', 'columns.table_schema')
                    ->on('tables.table_name', '=', 'columns.table_name');
            })
            ->where('columns.table_schema', $database)
            ->where('tables.table_type', 'BASE TABLE')
            ->where('columns.column_name', 'like', '%comment%')
            ->whereIn('columns.data_type', ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'])
            ->selectRaw('columns.TABLE_NAME as table_name, columns.COLUMN_NAME as column_name')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $table = (string) $row->table_name;
            $column = (string) $row->column_name;
            if (! isset($companyTables[$table]) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }
            $out[$table][] = $column;
        }

        return $out;
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
