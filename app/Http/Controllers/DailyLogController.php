<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\RecordActivity;
use App\Models\User;
use App\Support\Activity\ActivityRegistry;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * DailyLogController
 * ------------------------------------------------------------------
 * السجل اليومي : كل التعديلات المسجّلة في النظام في مكان واحد ، مقسّمة
 * على تابات حسب نوع السجل (ماني ريسيد ، ماني بايمنت ، مصروفات ... إلخ)
 *
 * قبل كده الـ logs كانت متاحة بس من زرار جوه كل صف — يعني لازم تعرف
 * السجل اللي بتدوّر عليه الأول عشان تشوف تاريخه . الصفحة دي بتقلب
 * السؤال : "إيه اللي حصل النهاردة ؟"
 *
 * و عشان كده بتفتح على النهاردة بس ، و بتعرض كل الأنواع اللي ليها حركة
 * في الفترة تحت بعضها ، و التابات فوق بقت روابط بتنزّلك على القسم بدل
 * ما تبدّل الصفحة . النوع اللي مالوش حركة في الفترة مش بيتعرض أصلا .
 *
 * كل الفلاتر — الفترة ، النوع ، الشخص ، البحث — بتتطبّق في قاعدة
 * البيانات ، و كل قسم ليه سقف صفوف : جدول record_activities بيكبر مع
 * الوقت ، و تحميله كله لعشر أقسام مع بعض كان هيبقى أبطأ كل يوم
 */
class DailyLogController extends Controller
{
    /** أقصى عدد صفوف بتتعرض في القسم الواحد قبل ما نقول "فيه أكتر" */
    private const ROWS_PER_SECTION = 50;

    public function __invoke(Request $request, Company $company)
    {
        abort_unless(
            PermissionResolver::allows($request->user(), 'daily_log.view'),
            403,
            __('You do not have permission to perform this action.')
        );

        $tabs = $this->availableTabs($request->user());

        if ($tabs === []) {
            abort(404);
        }

        /**
         * * الافتراضي : النهاردة بس .
         *
         * * السؤال اللي الصفحة دي بتجاوب عليه هو "إيه اللي حصل النهاردة" ،
         * * فأول ما تفتح لازم تجاوب عليه من غير ما حد يملا فلاتر . و لإن
         * * الافتراضي ده بيتحسب هنا مش في الـ query string ، الرابط اللي
         * * فيه فترة محفوظة بيفضل شغّال زي ما هو
         */
        $today = Carbon::today()->format('Y-m-d');
        $from = $request->filled('from') ? (string) $request->get('from') : $today;
        $to = $request->filled('to') ? (string) $request->get('to') : $today;

        /**
         * * فترة مقلوبة (البداية بعد النهاية) مش خطأ يستاهل رسالة —
         * * بنقلبها و نكمّل
         */
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filters = [
            'search' => trim((string) $request->get('search', '')),
            'event' => (string) $request->get('event', ''),
            'user' => (string) $request->get('user', ''),
            'from' => $from,
            'to' => $to,
        ];

        /**
         * * الصفحة بقت أقسام تحت بعضها بدل تاب واحدة في المرة : كل موديل
         * * ليه حركة في الفترة دي بيتعرض بجدوله ، و التابات فوق بقت روابط
         * * بتنزّلك على القسم .
         *
         * * القسم اللي مالوش ولا حركة في الفترة مش بيتعرض أصلا — و لا تابه
         */
        $sections = [];

        foreach ($tabs as $key => $tab) {
            $query = $this->activityQuery($company, $tab['classes'], $filters);

            $total = (clone $query)->count();

            if ($total === 0) {
                continue;
            }

            $sections[] = [
                'key' => $key,
                'label' => $tab['label'],
                'total' => $total,
                /**
                 * * سقف على مستوى الـ SQL : الصفحة ممكن تعرض عشر أقسام مع
                 * * بعض ، فتحميل كل صفوف كل قسم كان هيبقى أبطأ كل يوم
                 */
                'shown' => min($total, self::ROWS_PER_SECTION),
                'hasMore' => $total > self::ROWS_PER_SECTION,
                'entries' => $query->limit(self::ROWS_PER_SECTION)->get()
                    ->map(fn (RecordActivity $activity) => array_merge(
                        $activity->toTimelineArray(),
                        ['record' => ActivityRegistry::labelFor($activity->subject_type).' #'.$activity->subject_id]
                    ))->values(),
            ];
        }

        return Inertia::render('DailyLogs/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'sections' => $sections,
            'filters' => $filters,
            'events' => [
                RecordActivity::EVENT_CREATED => __('Created'),
                RecordActivity::EVENT_UPDATED => __('Updated'),
                RecordActivity::EVENT_DELETED => __('Deleted'),
            ],
            'users' => $this->usersWithActivity($company),
            'rowsPerSection' => self::ROWS_PER_SECTION,
            'indexUrl' => route('daily-logs.index', ['company' => $company->id]),
        ]);
    }

    /**
     * * الأشخاص اللي ليهم حركة مسجّلة في الشركة دي — عشان الفلتر يبقى
     * * قايمة اختيار مش خانة كتابة يتهجّى فيها الاسم
     *
     * * بيتقرا من جدول الحركات نفسه ، فمش بيعرض مستخدمين عمرهم ما عملوا
     * * حاجة
     *
     * @return list<array{id: int|string, name: string}>
     */
    private function usersWithActivity(Company $company): array
    {
        $ids = RecordActivity::query()
            ->where(fn ($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return User::whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }

    /**
     * * التابات اللي اليوزر ده مسموحله يشوفها
     *
     * * صلاحية daily_log.view بتفتح الصفحة ، لكن كل تاب بتفضل متقيّدة
     * * بصلاحية الموديل بتاعها — فاللي مش بيشوف الماني بايمنت أصلا ما
     * * يشوفش تاريخه من هنا
     *
     * * أكتر من موديل ممكن يشتركوا في نفس التاب (الشيك بيتسجّل تحت
     * * الماني ريسيد مثلا) ، فالتاب بتلمّ كل الكلاسات بتاعتها
     *
     * @return array<string, array{label: string, classes: array<int, string>}>
     */
    private function availableTabs(?User $user): array
    {
        $tabs = [];

        foreach (ActivityRegistry::models() as $class) {
            $module = ActivityRegistry::moduleFor($class);

            if ($module === null || ! PermissionResolver::allows($user, "{$module}.view")) {
                continue;
            }

            $tabs[$module] ??= [
                'label' => ActivityRegistry::labelFor($class),
                'classes' => [],
            ];

            $tabs[$module]['classes'][] = $class;
        }

        /**
         * * التاب اللي فيها أكتر من موديل بتاخد اسم الموديول بدل اسم أول
         * * موديل فيها ، عشان "الماني ريسيد" ما تبقاش اسمها "شيك"
         *
         * * الاسم بييجي من سجل الصلاحيات — أسماء مكتوبة بعناية و مترجمة
         * * أصلا . قبل كده كان بيتشتق من مفتاح الموديول نفسه
         * * (lc_issuance ← "Lc Issuance") ، و ده لا اسم صح و لا مفتاح
         * * ترجمة موجود ، فكان بيطلع إنجليزي مشوّه
         */
        $moduleLabels = collect(PermissionRegistry::all())
            ->mapWithKeys(fn (array $entry) => [$entry['module'] => $entry['module_label']]);

        foreach ($tabs as $module => $tab) {
            if (count($tab['classes']) > 1) {
                $tabs[$module]['label'] = __($moduleLabels[$module] ?? ucwords(str_replace('_', ' ', $module)));
            }
        }

        return $tabs;
    }

    /**
     * * الاستعلام الأساسي — كل الفلاتر بتتطبّق هنا في قاعدة البيانات
     */
    private function activityQuery(Company $company, array $classes, array $filters)
    {
        return RecordActivity::query()
            ->with('user:id,name')
            ->whereIn('subject_type', $classes)
            /**
             * * صفوف من غير شركة (زي تعديلات المستخدمين) بتخص كل الشركات ،
             * * فبتظهر مع أي شركة بدل ما تختفي خالص
             */
            ->where(fn ($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->when($filters['event'] !== '', fn ($q) => $q->where('event', $filters['event']))
            ->whereDate('created_at', '>=', $filters['from'])
            ->whereDate('created_at', '<=', $filters['to'])
            /**
             * * فلتر الشخص : "وريني عمل إيه النهاردة" . بيقارن بالـ id مش
             * * بالاسم ، فالمستخدم اللي اتغيّر اسمه بيفضل شغله كله تحته
             */
            ->when($filters['user'] !== '', fn ($q) => $q->where('user_id', $filters['user']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = $filters['search'];

                $q->where(function ($inner) use ($term) {
                    $inner->where('user_name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('subject_id', $term)
                        ->orWhereIn('user_id', User::where('name', 'like', "%{$term}%")->pluck('id'));
                });
            })
            ->orderByDesc('id');
    }
}
