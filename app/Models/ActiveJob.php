<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * الصف ده معناه "في رفع إكسل شغال دلوقتي" — و صفحة الرفع بتخفي فورمة
 * * الرفع طول ما هو موجود
 *
 * * لو الـ job مات من غير ما يرمي ImportFailed (الـ worker اتقفل ، الذاكرة
 * * خلصت ، انقطاع) الصف كان بيفضل موجود للأبد ، فالصفحة تفضل بتقول "جاري
 * * المعالجة" و المستخدم عمره ما يقدر يرفع تاني و لا يعرف السبب
 *
 * @property int $id
 * @property int $company_id
 * @property string $status
 * @property string $model
 * @property string $model_name
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property string|null $failure_reason
 */
class ActiveJob extends Model
{
    /**
     * * بعد المدة دي من غير ما يخلص بنعتبره واقف ، مش شغال
     *
     * * الرفع الكبير بياخد دقايق مش ساعة ، و الـ timeout المضبوط على
     * * الاستيراد نفسه أطول من كده بكتير — فالساعة رقم متحفظ : اللي
     * * بيعدّيها يبقى مات فعلا
     */
    public const STALE_AFTER_MINUTES = 60;

    protected $table = 'active_jobs';

    protected $guarded = [];

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    // Company Scoop
    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id);
    }

    public function hasFailed(): bool
    {
        return $this->failed_at !== null;
    }

    /**
     * * واقف : عدّى عليه وقت طويل من غير ما يخلص و من غير ما يبلّغ بفشل
     */
    public function isStale(): bool
    {
        if ($this->hasFailed()) {
            return false;
        }

        return $this->created_at !== null
            && $this->created_at->lt(Carbon::now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * * محتاج تدخل من المستخدم : يا اما فشل و قال السبب ، يا اما واقف
     */
    public function needsAttention(): bool
    {
        return $this->hasFailed() || $this->isStale();
    }

    public function markFailed(?string $reason): void
    {
        $this->update([
            'failed_at' => Carbon::now(),
            'failure_reason' => $reason ? mb_substr($reason, 0, 2000) : null,
        ]);
    }

    /**
     * * الرسالة اللي المستخدم بيقراها : السبب المسجّل ، و لو مفيش (مات من
     * * غير ما يبلّغ) بنقول كده صراحةً بدل ما نسيبه قدام شاشة ساكتة
     */
    public function statusMessage(): string
    {
        if ($this->failure_reason) {
            return $this->failure_reason;
        }

        if ($this->hasFailed()) {
            return __('The import failed, but no reason was recorded.');
        }

        return __('The import stopped responding and did not finish. It may have run out of memory or been interrupted.');
    }
}
