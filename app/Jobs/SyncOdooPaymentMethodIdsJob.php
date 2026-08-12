<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\FinancialInstitutionAccount;
use App\Models\User;
use App\Services\Api\OdooService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * * بتشتغل بعد كل لوجين
 * * بتدور على الفروع و الحسابات البنكية اللي ليها odoo_code
 * * و اعمدة الـ payment method فيها مش ارقام (null او '[]' او اي حاجة تانية)
 * * و تحاول تجيبهم من اودو تاني
 *
 * * المفروض متضربش اي ايرور مهما حصل : كل صف في try/catch لوحده
 * * و لو صف فشل بنكمل على اللي بعده و بنسجل في اللوج بس
 */
class SyncOdooPaymentMethodIdsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * * الاعمدة اللي بنراجعها
     */
    private const PAYMENT_METHOD_COLUMNS = [
        'odoo_inbound_transfer_payment_method_id',
        'odoo_outbound_transfer_payment_method_id',
        'odoo_inbound_cheque_payment_method_id',
        'odoo_outbound_cheque_payment_method_id',
    ];

    /**
     * * كل مزامنة لحساب واحد بتسحب شجرة حسابات اودو كاملة
     * * فلو ١٠ مستخدمين عملوا لوجين ورا بعض هنضرب اودو بلا داعي
     * * علشان كدا بنشتغل مرة كل ربع ساعة للشركة الواحدة
     */
    private const MINUTES_BETWEEN_RUNS = 15;

    /**
     * * مش عايزين ريتراي : لو فشلت مرة تعالج نفسها في اللوجين اللي بعده
     */
    public int $tries = 1;

    /**
     * * الـ timeout الافتراضي للـ worker دقيقة واحدة و دي مش كفاية
     * * لان كل حساب بياخد كذا نداء على اودو و اودو نفسه بطيء احيانا
     * * القيمة اللي هنا بتغلب اللي على الـ worker من غير ما نغير امر التشغيل
     * * ملحوظة : لازم تفضل اقل من retry_after في config/queue.php
     * * (متظبطة عندنا على قيمة عالية جدا فمفيش مشكلة)
     */
    public int $timeout = 900;

    public function __construct(private int $userId)
    {
    }

    public function handle(): void
    {
        try {
            /**
             * @var User|null $user
             */
            $user = User::find($this->userId);
            if (! $user) {
                return ;
            }

            foreach ($user->companies as $company) {
                $this->syncCompany($company, $user);
            }
        } catch (\Throwable $exception) {
            $this->logFailure('handle', $exception);
        }
    }

    private function syncCompany(Company $company, User $user): void
    {
        try {
            if (! $company->hasOdooIntegrationCredentials($user)) {
                return ;
            }

            $accounts = $this->rowsNeedingSync('financial_institution_accounts', $company->id);
            $branchesOdooCodes = $this->rowsNeedingSync('branch', $company->id)->pluck('odoo_code');

            if ($accounts->isEmpty() && $branchesOdooCodes->isEmpty()) {
                return ;
            }

            if (! $this->canRunNowFor($company)) {
                return ;
            }

            /**
             * * بنعدي على الكونتينر مش new مباشرة علشان نقدر نستبدلها في الاختبارات
             */
            $odooService = app(OdooService::class, ['company' => $company, 'user' => $user]);

            foreach ($accounts as $account) {
                $this->syncAccount($odooService, $company, (int) $account->id);
            }

            foreach ($branchesOdooCodes as $odooCode) {
                $this->syncBranch($odooService, $company, (string) $odooCode);
            }
        } catch (\Throwable $exception) {
            $this->logFailure('company : ' . $company->id, $exception);
        }
    }

    /**
     * * الصفوف اللي ليها كود اودو و فيها عمود واحد على الاقل مش رقم
     */
    private function rowsNeedingSync(string $table, int $companyId)
    {
        return DB::table($table)
            ->where('company_id', $companyId)
            ->whereNotNull('odoo_code')
            ->where('odoo_code', '!=', '')
            ->where(function ($query) {
                foreach (self::PAYMENT_METHOD_COLUMNS as $column) {
                    $query->orWhereNull($column)
                        ->orWhereRaw("`$column` NOT REGEXP '^[0-9]+$'");
                }
            })
            ->get(['id', 'odoo_code']);
    }

    private function syncAccount(OdooService $odooService, Company $company, int $accountId): void
    {
        try {
            $account = FinancialInstitutionAccount::find($accountId);
            if ($account) {
                $odooService->syncFinancialInstitutions($account);
            }
        } catch (\Throwable $exception) {
            $this->logFailure('company : ' . $company->id . ' , account : ' . $accountId, $exception);
        }
    }

    private function syncBranch(OdooService $odooService, Company $company, string $odooCode): void
    {
        try {
            $odooService->syncBranchSafe($odooCode, $company->id);
        } catch (\Throwable $exception) {
            $this->logFailure('company : ' . $company->id . ' , branch odoo code : ' . $odooCode, $exception);
        }
    }

    private function canRunNowFor(Company $company): bool
    {
        return Cache::add(
            'sync-odoo-payment-method-ids:' . $company->id,
            true,
            now()->addMinutes(self::MINUTES_BETWEEN_RUNS)
        );
    }

    private function logFailure(string $context, \Throwable $exception): void
    {
        Log::warning('SyncOdooPaymentMethodIdsJob failed [ ' . $context . ' ] : ' . $exception->getMessage());
    }

    /**
     * * حتى لو الكيو نفسه فشّل الجوب لاي سبب , مش عايزينها تظهر للمستخدم
     */
    public function failed(\Throwable $exception): void
    {
        $this->logFailure('job failed', $exception);
    }
}
