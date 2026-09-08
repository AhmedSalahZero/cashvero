<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\RecordActivity;
use App\Models\User;
use App\Support\Activity\ActivityRegistry;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
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
 * التقسيم الصفحي بيتعمل في قاعدة البيانات (paginate) مش في الميموري :
 * جدول record_activities بيكبر مع الوقت ، و تحميله كله عشان نعرض ٢٥ صف
 * كان هيبقى أبطأ كل يوم عن اللي قبله
 */
class DailyLogController extends Controller
{
    /** كام صف في الصفحة الواحدة */
    private const PER_PAGE = 25;

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

        $activeTab = $request->get('tab');

        if (! isset($tabs[$activeTab])) {
            $activeTab = array_key_first($tabs);
        }

        /**
         * * رقم الصفحة بيتبعت صراحةً بدل ما نسيب الـ paginator ياخده من
         * * الـ request العام — كده الصفحة بتشتغل صح مهما كان اللي نادى
         * * الكونترولر
         */
        $paginator = $this->activityQuery($company, $tabs[$activeTab]['classes'], $request)
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, (int) $request->get('page', 1)))
            ->withQueryString();

        return Inertia::render('DailyLogs/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'tabs' => collect($tabs)->map(fn (array $tab, string $key) => [
                'key' => $key,
                'label' => $tab['label'],
            ])->values(),
            'activeTab' => $activeTab,
            'filters' => [
                'search' => (string) $request->get('search', ''),
                'event' => (string) $request->get('event', ''),
                'from' => (string) $request->get('from', ''),
                'to' => (string) $request->get('to', ''),
            ],
            'events' => [
                RecordActivity::EVENT_CREATED => __('Created'),
                RecordActivity::EVENT_UPDATED => __('Updated'),
                RecordActivity::EVENT_DELETED => __('Deleted'),
            ],
            'entries' => collect($paginator->items())->map(fn (RecordActivity $activity) => array_merge(
                $activity->toTimelineArray(),
                ['record' => ActivityRegistry::labelFor($activity->subject_type).' #'.$activity->subject_id]
            ))->values(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'indexUrl' => route('daily-logs.index', ['company' => $company->id]),
        ]);
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
    private function activityQuery(Company $company, array $classes, Request $request)
    {
        return RecordActivity::query()
            ->with('user:id,name')
            ->whereIn('subject_type', $classes)
            /**
             * * صفوف من غير شركة (زي تعديلات المستخدمين) بتخص كل الشركات ،
             * * فبتظهر مع أي شركة بدل ما تختفي خالص
             */
            ->where(fn ($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->get('event')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->get('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->get('to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->get('search'));

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
