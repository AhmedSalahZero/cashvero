<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RepairStatementBalancesCommand
 * ------------------------------------------------------------------
 * كشوف الحسابات في النظام (بنوك / خزائن / اعتمادات / خطابات ضمان /
 * شركاء) بتخزّن الرصيد الجاري (beginning_balance / end_balance) بدل ما
 * تحسبه وقت العرض. التريجرات كانت بتسلسل الرصيد بترتيب `date` (دقة يوم
 * + tiebreak بالـ id) بينما التقارير بتعرض الصفوف مرتبة بـ `full_date`
 * (دقة ثانية) — فلما يبقى فيه أكتر من حركة في نفس اليوم الترتيبين
 * بيختلفوا، ويظهر صف رصيده الافتتاحي مش مساوي للرصيد الختامي للصف
 * اللي قبله على الشاشة. دي كانت مصدر الفرق مع أودو.
 *
 * التريجرات اتصلحت (بقت تسلسل بـ full_date زي employee/tax اللي كانوا
 * سليمين أصلًا)، لكن الصفوف المخزّنة قبل الإصلاح فضلت بالقيم القديمة —
 * دي مهمة الأمر ده.
 *
 * إزاي بيصلح: مش بيكتب الأرصدة بنفسه. بيلمس updated_at لكل صف بالترتيب
 * الصحيح (full_date, id) جوه transaction، فالتريجر المُصلَّح هو اللي
 * يعيد الحساب. كده الناتج مطابق ١٠٠٪ لأي حركة جديدة هتتسجل بعد كده.
 *
 * ⚠️ لازم `php artisan run:sql` يتنفّذ الأول عشان التريجرات المُصلَّحة
 *    تتثبّت في قاعدة البيانات — الأمر بيتحقق من ده ويرفض الاشتغال لو لأ.
 *
 * الاستخدام:
 *   php artisan statements:repair-balances                  # فحص فقط (الافتراضي، لا يكتب)
 *   php artisan statements:repair-balances --fix            # فحص ← إصلاح ← إعادة فحص
 *   php artisan statements:repair-balances --table=current_account_bank_statements
 *   php artisan statements:repair-balances --company=3 --fix
 */
class RepairStatementBalancesCommand extends Command
{
    protected $signature = 'statements:repair-balances
        {--fix : نفّذ الإصلاح فعليًا. بدونها الأمر بيفحص ويطبع النتيجة من غير أي كتابة}
        {--table=* : قصر التنفيذ على جداول معيّنة}
        {--company= : قصر التنفيذ على شركة واحدة (الجداول اللي فيها company_id بس)}
        {--samples=3 : عدد الصفوف المكسورة اللي تتعرض كعيّنة}
        {--skip-trigger-check : تخطّي التحقق من تثبيت التريجرات (للتشخيص فقط)}';

    protected $description = 'يفحص ويصلّح تسلسل الأرصدة المخزّنة في كشوف الحسابات (يعيد الحساب بترتيب full_date)';

    /**
     * مفاتيح تجميع كل جدول — مأخوذة حرفيًا من شرط الـ where في تريجره،
     * مش تخمين. أي اختلاف هنا معناه مقارنة صفوف من سلاسل مختلفة.
     *
     * ⚠️ جداول الـ overdraft (clean / fully_secured / lc / assignment /
     *    commercial_paper) مستبعدة عمدًا: عندها عمود `priority` بيرتّب صفوف
     *    الفوايد قبل الأصل في نفس اليوم، وتسلسلها لسه بـ date + priority.
     *    تحويلها لـ full_date هيلغي ترتيب الـ priority — محتاجة قرار منفصل.
     *
     * @var array<string, array{keys: list<string>, filter: string|null}>
     */
    private const TABLES = [
        // كشوف الشركاء
        'shareholder_statements' => ['keys' => ['company_id', 'partner_id', 'currency_name'], 'filter' => null],
        'employee_statements' => ['keys' => ['company_id', 'partner_id', 'currency_name'], 'filter' => null],
        'other_partner_statements' => ['keys' => ['company_id', 'partner_id', 'currency_name'], 'filter' => null],
        'subsidiary_company_statements' => ['keys' => ['company_id', 'partner_id', 'currency_name'], 'filter' => null],
        'tax_statements' => ['keys' => ['company_id', 'partner_id', 'currency_name'], 'filter' => null],
        // كشوف البنوك والخزائن والتسهيلات (اللي ملهاش عمود priority)
        'cash_in_safe_statements' => ['keys' => ['company_id', 'currency', 'branch_id'], 'filter' => null],
        'current_account_bank_statements' => ['keys' => ['company_id', 'financial_institution_account_id'], 'filter' => 'is_active = 1'],
        'letter_of_credit_statements' => ['keys' => ['company_id', 'currency', 'lc_facility_id', 'financial_institution_id', 'source', 'lc_type'], 'filter' => null],
        'letter_of_credit_cash_cover_statements' => ['keys' => ['company_id', 'currency', 'lc_facility_id', 'financial_institution_id', 'source', 'lc_type'], 'filter' => null],
        'letter_of_guarantee_statements' => ['keys' => ['company_id', 'currency', 'lg_facility_id', 'financial_institution_id', 'source', 'lg_type'], 'filter' => null],
        'letter_of_guarantee_cash_cover_statements' => ['keys' => ['company_id', 'currency', 'lg_facility_id', 'financial_institution_id', 'source', 'lg_type'], 'filter' => null],
        'loan_statements' => ['keys' => ['company_id', 'financial_institution_account_id'], 'filter' => null],
    ];

    /** الترتيب المرجعي — نفس اللي التقارير بتعرض بيه ونفس اللي التريجر بيسلسل بيه بعد الإصلاح */
    private const ORDER = 'full_date asc, id asc';

    public function handle(): int
    {
        $tables = $this->resolveTables();
        if ($tables === []) {
            $this->error('لا توجد جداول مطابقة للـ --table المُمرَّر.');

            return self::FAILURE;
        }

        $fix = (bool) $this->option('fix');

        if ($fix && ! $this->option('skip-trigger-check') && ! $this->assertTriggersInstalled($tables)) {
            return self::FAILURE;
        }

        $this->line('');
        $this->info('══════ الفحص قبل الإصلاح ══════');
        $before = $this->auditAll($tables, true);
        $totalBroken = array_sum(array_column($before, 'broken'));

        if ($totalBroken === 0) {
            $this->line('');
            $this->info('✔ كل السلاسل سليمة — لا حاجة لأي إصلاح.');

            return self::SUCCESS;
        }

        if (! $fix) {
            $this->line('');
            $this->warn("تم رصد {$totalBroken} صفًا بسلسلة أرصدة مكسورة.");
            $this->warn('ده فحص فقط ولم تُكتب أي بيانات. أضف --fix للتنفيذ الفعلي.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('══════ تنفيذ الإصلاح ══════');
        $touched = 0;
        foreach ($tables as $table) {
            if ($before[$table]['broken'] === 0) {
                continue;
            }
            $touched += $this->repairTable($table);
        }
        $this->line("تم إعادة حساب {$touched} صفًا.");

        $this->line('');
        $this->info('══════ التحقق بعد الإصلاح ══════');
        $after = $this->auditAll($tables, false);
        $stillBroken = array_sum(array_column($after, 'broken'));

        $this->line('');
        $this->table(
            ['الجدول', 'الصفوف', 'مكسور قبل', 'مكسور بعد', 'النتيجة'],
            collect($tables)->map(fn (string $t) => [
                $t,
                $before[$t]['rows'],
                $before[$t]['broken'],
                $after[$t]['broken'],
                $after[$t]['broken'] === 0
                    ? ($before[$t]['broken'] > 0 ? '✔ تم الإصلاح' : '✔ سليم')
                    : '✖ ما زال مكسورًا',
            ])->all()
        );

        if ($stillBroken > 0) {
            $this->error("لسه فيه {$stillBroken} صفًا مكسورًا — التريجرات على الأغلب مش متثبّتة. شغّل: php artisan run:sql");

            return self::FAILURE;
        }

        $this->info('✔ كل السلاسل سليمة بعد الإصلاح.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function resolveTables(): array
    {
        $all = array_keys(self::TABLES);
        $requested = (array) $this->option('table');

        return $requested === [] ? $all : array_values(array_intersect($all, $requested));
    }

    /**
     * التريجر المُصلَّح بيسلسل بـ full_date. لو اللي متثبّت في القاعدة لسه
     * القديم (date)، الإصلاح هيكتب نفس القيم الغلط تاني — فبنمنعه من البداية.
     *
     * @param  list<string>  $tables
     */
    private function assertTriggersInstalled(array $tables): bool
    {
        $stale = [];
        foreach ($tables as $table) {
            $body = DB::table('information_schema.triggers')
                ->where('trigger_schema', DB::getDatabaseName())
                ->where('event_object_table', $table)
                ->where('action_timing', 'BEFORE')
                ->where('event_manipulation', 'UPDATE')
                ->value('action_statement');

            if ($body === null) {
                $stale[] = "{$table} (التريجر غير موجود)";
            } elseif (! str_contains((string) $body, 'full_date < new.full_date')) {
                $stale[] = "{$table} (لسه بيسلسل بـ date)";
            }
        }

        if ($stale !== []) {
            $this->error('التريجرات المُصلَّحة غير مثبّتة في قاعدة البيانات:');
            foreach ($stale as $s) {
                $this->line("  • {$s}");
            }
            $this->warn('شغّل الأول:  php artisan run:sql');

            return false;
        }

        $this->info('✔ التريجرات المُصلَّحة مثبّتة (تسلسل بـ full_date).');

        return true;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, array{rows:int, broken:int}>
     */
    private function auditAll(array $tables, bool $showSamples): array
    {
        $out = [];
        foreach ($tables as $table) {
            $out[$table] = $this->audit($table);
            $line = sprintf('%-52s صفوف: %-6d مكسور: %d', $table, $out[$table]['rows'], $out[$table]['broken']);
            $out[$table]['broken'] > 0 ? $this->warn($line) : $this->line($line);

            if ($showSamples && $out[$table]['broken'] > 0) {
                $this->showSamples($table);
            }
        }

        return $out;
    }

    /**
     * صف يُعد مكسورًا لو رصيده الافتتاحي ≠ الرصيد الختامي للصف السابق له
     * في الترتيب المرجعي، أو لو حسابه الداخلي نفسه مش مظبوط.
     *
     * @return array{rows:int, broken:int}
     */
    private function audit(string $table): array
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS rows_count,
                    COALESCE(SUM(
                        CASE WHEN ABS(end_balance - (beginning_balance + COALESCE(debit,0) - COALESCE(credit,0))) > 0.01
                          OR (prev_end IS NOT NULL AND ABS(beginning_balance - prev_end) > 0.01)
                        THEN 1 ELSE 0 END), 0) AS broken
             FROM ('.$this->chainSelect($table).') x',
            $this->bindings($table)
        );

        return ['rows' => (int) $row->rows_count, 'broken' => (int) $row->broken];
    }

    private function showSamples(string $table): void
    {
        $limit = max(1, (int) $this->option('samples'));
        $rows = DB::select(
            'SELECT id, full_date, beginning_balance, debit, credit, end_balance, prev_end
             FROM ('.$this->chainSelect($table).') x
             WHERE (prev_end IS NOT NULL AND ABS(beginning_balance - prev_end) > 0.01)
                OR ABS(end_balance - (beginning_balance + COALESCE(debit,0) - COALESCE(credit,0))) > 0.01
             ORDER BY full_date, id LIMIT '.$limit,
            $this->bindings($table)
        );

        $this->table(
            ['id', 'full_date', 'افتتاحي مخزّن', 'مدين', 'دائن', 'ختامي', 'الافتتاحي الصحيح'],
            collect($rows)->map(fn ($r) => [
                $r->id, $r->full_date, $r->beginning_balance,
                $r->debit, $r->credit, $r->end_balance,
                $r->prev_end === null ? '0.00' : $r->prev_end,
            ])->all()
        );
    }

    /** نافذة LAG على الترتيب المرجعي داخل كل سلسلة، حسب مفاتيح الجدول */
    private function chainSelect(string $table): string
    {
        // اسم الجدول ومفاتيحه من ثابت داخلي (self::TABLES) — مفيش أي مدخل مستخدم هنا
        $meta = self::TABLES[$table];
        $partition = implode(', ', $meta['keys']);

        $where = array_filter([$meta['filter'], $this->companyFilter($table)]);
        $whereSql = $where === [] ? '' : ' WHERE '.implode(' AND ', $where);

        return 'SELECT id, full_date, beginning_balance, debit, credit, end_balance,
                    LAG(end_balance) OVER (PARTITION BY '.$partition.' ORDER BY full_date, id) AS prev_end
                FROM `'.$table.'`'.$whereSql;
    }

    private function companyFilter(string $table): ?string
    {
        if (! $this->option('company') || ! in_array('company_id', self::TABLES[$table]['keys'], true)) {
            return null;
        }

        return 'company_id = ?';
    }

    /** @return list<mixed> */
    private function bindings(string $table): array
    {
        return $this->companyFilter($table) ? [(int) $this->option('company')] : [];
    }

    /**
     * بيلمس updated_at بالترتيب المرجعي جوه transaction فالتريجر يعيد الحساب.
     * كل سلسلة لوحدها عشان القفل يفضل صغير.
     */
    private function repairTable(string $table): int
    {
        $meta = self::TABLES[$table];

        $base = fn () => DB::table($table)
            ->when($meta['filter'], fn ($q) => $q->whereRaw($meta['filter']))
            ->when(
                $this->option('company') && in_array('company_id', $meta['keys'], true),
                fn ($q) => $q->where('company_id', (int) $this->option('company'))
            );

        $groups = $base()->select($meta['keys'])->distinct()->get();

        $touched = 0;
        $bar = $this->output->createProgressBar($groups->count());
        $bar->setFormat("  {$table}: %current%/%max% [%bar%] %percent:3s%%");
        $bar->start();

        foreach ($groups as $group) {
            DB::transaction(function () use ($base, $table, $meta, $group, &$touched) {
                $q = $base();
                foreach ($meta['keys'] as $key) {
                    $value = $group->{$key};
                    $value === null ? $q->whereNull($key) : $q->where($key, $value);
                }

                $ids = $q->orderByRaw(self::ORDER)->lockForUpdate()->pluck('id');

                $now = now();
                foreach ($ids as $id) {
                    DB::table($table)->where('id', $id)->update(['updated_at' => $now]);
                    $touched++;
                }
            });
            $bar->advance();
        }

        $bar->finish();
        $this->line('');

        return $touched;
    }
}
