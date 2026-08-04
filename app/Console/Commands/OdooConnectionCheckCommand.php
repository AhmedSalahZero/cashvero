<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * OdooConnectionCheckCommand
 * ------------------------------------------------------------------
 * Answers "why does this user not connect to Odoo?" with the actual
 * reason instead of a guess.
 *
 * The connection is set up in AuthTrait::__construct(), which used to
 * swallow every failure (`catch { $uid = null; }`) and then stored that
 * null on the user — so a bad password, an unreachable server and a
 * wrong database name all looked identical from the outside: nothing
 * worked and nothing said why.
 *
 * This command walks the exact same steps and reports each one:
 *   1. company Odoo url / db name present?
 *   2. user odoo_username / odoo_db_password present?
 *   3. the stored odoo_id
 *   4. a live authenticate() call against Odoo, with the raw response
 *      or the real exception
 *   5. a real read through xmlrpc/2/object, to prove the uid works and
 *      not just that the login was accepted
 *   6. how many of that user's records are currently unsynced, with the
 *      last recorded Odoo error
 *
 * USAGE
 *   php artisan odoo:check --company=92 --user=64
 *   php artisan odoo:check --company=92            # every user of the company
 */
class OdooConnectionCheckCommand extends Command
{
    protected $signature = 'odoo:check
        {--company= : Company id to test against (required)}
        {--user= : User id to test; omit to test every user that has Odoo credentials}';

    protected $description = 'Diagnose why a user does or does not connect to Odoo, step by step';

    public function handle(): int
    {
        $companyId = $this->option('company');

        if (! $companyId) {
            $this->error('--company is required.');

            return self::FAILURE;
        }

        $company = Company::find($companyId);

        if (! $company) {
            $this->error("Company {$companyId} not found.");

            return self::FAILURE;
        }

        $this->line('');
        $this->info('COMPANY '.$company->id.' — '.$company->getName());
        $this->step('odoo_db_url', $company->getOdooDBUrl());
        $this->step('odoo_db_name', $company->getOdooDBName());

        if (! $company->hasOdooCredentials()) {
            $this->error('  ✗ الشركة نفسها مالهاش بيانات أودو — مفيش داعي نكمل');

            return self::SUCCESS;
        }

        foreach ($this->users() as $user) {
            $this->checkUser($company, $user);
        }

        return self::SUCCESS;
    }

    /**
     * @return iterable<User>
     */
    private function users(): iterable
    {
        if ($userId = $this->option('user')) {
            $user = User::find($userId);

            if (! $user) {
                $this->error("User {$userId} not found.");

                return [];
            }

            return [$user];
        }

        return User::whereNotNull('odoo_username')->where('odoo_username', '!=', '')->get();
    }

    private function checkUser(Company $company, User $user): void
    {
        $this->line('');
        $this->info('USER '.$user->id.' — '.$user->name);

        $username = $user->getOdooDBUserName();
        $password = $user->getOdooDBPassword();

        $this->step('odoo_username', $username);
        $this->step('odoo_db_password', $password ? '(set, '.strlen($password).' chars)' : null);
        $this->step('stored odoo_id', $user->getOdooId());

        if (! $username || ! $password) {
            $this->error('  ✗ بيانات دخول اليوزر ناقصة — الاتصال مستحيل ينجح');

            return;
        }

        require_once public_path('apis/ripcord.php');

        // --- step 4: authenticate -------------------------------------
        $uid = null;

        try {
            $common = \ripcord::client($company->getOdooDBUrl().'/xmlrpc/2/common');
            $uid = $common->authenticate($company->getOdooDBName(), $username, $password, []);
        } catch (Throwable $e) {
            $this->error('  ✗ authenticate رمى استثناء: '.get_class($e).' — '.$e->getMessage());
            $this->line('     (السيرفر مش متاح، أو ال url غلط، أو الشبكة مقفولة)');

            return;
        }

        if (is_array($uid)) {
            $this->error('  ✗ أودو رجّع fault: '.json_encode($uid, JSON_UNESCAPED_UNICODE));
            $this->line('     (غالباً اسم الداتابيز غلط)');

            return;
        }

        if (! is_numeric($uid)) {
            $this->error('  ✗ أودو رفض بيانات الدخول (رجّع '.json_encode($uid).')');
            $this->line('     (اليوزر أو الباسورد غلط، أو اليوزر متوقف في أودو)');

            return;
        }

        $this->line('  ✓ authenticate نجح — uid = '.$uid);

        if ($user->getOdooId() && (int) $user->getOdooId() !== (int) $uid) {
            $this->warn('  ! الـ odoo_id المحفوظ ('.$user->getOdooId().') مش نفس اللي أودو بيرجّعه ('.$uid.')');
        }

        // --- step 5: prove the uid can actually read ------------------
        try {
            $models = \ripcord::client($company->getOdooDBUrl().'/xmlrpc/2/object');
            $result = $models->execute_kw($company->getOdooDBName(), $uid, $password, 'res.users', 'read', [[(int) $uid]], ['fields' => ['login']]);

            if (is_array($result) && isset($result['faultCode'])) {
                $this->error('  ✗ الـ uid اتقبل بس القراءة اترفضت: '.($result['faultString'] ?? ''));

                return;
            }

            $this->line('  ✓ قراءة فعلية نجحت — '.json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            $this->error('  ✗ القراءة رمت استثناء: '.$e->getMessage());

            return;
        }

        $this->reportSyncFailures($user);
    }

    private function reportSyncFailures(User $user): void
    {
        foreach (['cash_expenses', 'money_payments', 'money_received'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id') || ! Schema::hasColumn($table, 'synced_with_odoo')) {
                continue;
            }

            $failed = DB::table($table)->where('user_id', $user->id)->where('synced_with_odoo', 0);
            $count = (clone $failed)->count();
            $total = DB::table($table)->where('user_id', $user->id)->count();

            if ($count === 0) {
                $this->line("  · {$table}: {$total} صف، كلهم متزامنين");

                continue;
            }

            $this->warn("  ! {$table}: {$count} صف غير متزامن من {$total}");

            $last = (clone $failed)->whereNotNull('odoo_error_message')->orderByDesc('id')->first(['id', 'odoo_error_message']);

            if ($last) {
                $this->line("     آخر خطأ (#{$last->id}): ".$last->odoo_error_message);
            }
        }
    }

    private function step(string $label, $value): void
    {
        $ok = $value !== null && $value !== '';
        $this->line(sprintf('  %s %-18s %s', $ok ? '✓' : '✗', $label, $ok ? $value : '(فاضي)'));
    }
}
