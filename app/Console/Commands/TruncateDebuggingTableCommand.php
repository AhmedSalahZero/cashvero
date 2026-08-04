<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TruncateDebuggingTableCommand
 * ------------------------------------------------------------------
 * جدول `debugging` كان بيتملى من تريجر
 * before_insert_current_account_bank_statements اللي كان بيعمل ٣ عمليات
 * insert مع كل صف بيتضاف لكشف الحساب البنكي — كود تشخيص قديم فضل شغال
 * في الإنتاج. التريجر اتنضّف، فالجدول ما بيكبرش تاني، لكن الصفوف
 * المتراكمة قبل كده لسه موجودة — دي مهمة الأمر ده.
 *
 * بيستخدم DELETE مش TRUNCATE عشان:
 *   - TRUNCATE في MySQL أمر DDL بيعمل implicit commit، فما ينفعش يترجع
 *   - DELETE ممكن يتقسّم على دفعات، فما يقفلش الجدول فترة طويلة على
 *     سيرفر إنتاج شغال
 *
 * الاستخدام:
 *   php artisan debugging:truncate               # يعرض العدد ويطلب تأكيد
 *   php artisan debugging:truncate --force       # من غير تأكيد (للـ CI/الإنتاج)
 *   php artisan debugging:truncate --keep-days=7 # يسيب آخر ٧ أيام ويحذف الأقدم
 *   php artisan debugging:truncate --chunk=5000  # حجم الدفعة
 */
class TruncateDebuggingTableCommand extends Command
{
    protected $signature = 'debugging:truncate
        {--force : نفّذ من غير سؤال تأكيد}
        {--keep-days= : سيب صفوف آخر N يوم واحذف الأقدم منها فقط}
        {--chunk=5000 : عدد الصفوف اللي تتحذف في كل دفعة}';

    protected $description = 'يفرّغ جدول debugging المتراكم من كود تشخيص قديم';

    private const TABLE = 'debugging';

    public function handle(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            $this->info('جدول `'.self::TABLE.'` غير موجود — لا شيء لعمله.');

            return self::SUCCESS;
        }

        $keepDays = $this->option('keep-days');
        $hasCreatedAt = Schema::hasColumn(self::TABLE, 'created_at');

        if ($keepDays !== null && ! $hasCreatedAt) {
            $this->error('--keep-days بيحتاج عمود created_at وهو مش موجود في الجدول.');

            return self::FAILURE;
        }

        $scope = fn () => DB::table(self::TABLE)
            ->when($keepDays !== null, fn ($q) => $q->where('created_at', '<', now()->subDays((int) $keepDays)));

        $total = DB::table(self::TABLE)->count();
        $target = $scope()->count();

        $this->line('');
        $this->line('  إجمالي الصفوف الحالية : '.number_format($total));
        $this->line('  المستهدف للحذف        : '.number_format($target)
            .($keepDays !== null ? "  (الأقدم من {$keepDays} يوم)" : '  (الجدول كله)'));
        $this->line('');

        if ($target === 0) {
            $this->info('✔ لا توجد صفوف مطابقة — لا حاجة لأي حذف.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('تأكيد الحذف؟', false)) {
            $this->warn('تم الإلغاء — لم تُحذف أي بيانات.');

            return self::SUCCESS;
        }

        $chunk = max(100, (int) $this->option('chunk'));
        $deleted = 0;

        $bar = $this->output->createProgressBar($target);
        $bar->start();

        // على دفعات عشان ما نقفلش الجدول فترة طويلة على سيرفر إنتاج شغال
        do {
            $affected = $scope()->limit($chunk)->delete();
            $deleted += $affected;
            $bar->advance($affected);
        } while ($affected > 0);

        $bar->finish();
        $this->line('');
        $this->line('');

        $remaining = DB::table(self::TABLE)->count();
        $this->info('✔ تم حذف '.number_format($deleted).' صفًا. المتبقي في الجدول: '.number_format($remaining));

        if ($keepDays === null && $remaining > 0) {
            $this->warn('لسه فيه صفوف — على الأغلب اتكتبت أثناء التنفيذ. شغّل الأمر تاني لو محتاج.');
        }

        return self::SUCCESS;
    }
}
