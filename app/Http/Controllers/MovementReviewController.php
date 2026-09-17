<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\Permissions\PermissionResolver;
use App\Traits\Models\IsReviewable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * MovementReviewController
 * ------------------------------------------------------------------
 * تعليم حركة مالية كـ "مراجَعة" أو فكّ المراجعة عنها .
 *
 * * كونترولر واحد لكل الحركات مش واحد لكل نوع : العملية نفسها واحدة
 * * بالظبط في الـ ١١ شاشة ، و نسخها ١١ مرة معناها ١١ مكان تتصلّح فيه
 * * أي مشكلة .
 *
 * * النوع بيتبعت في الرابط ، فبيتشاف في قايمة مقفولة قبل ما يتحوّل
 * * لكلاس — من غير كده الرابط بيبقى وسيلة لتحميل أي كلاس في التطبيق .
 */
class MovementReviewController extends Controller
{
    /**
     * الحركات اللي تتراجع : المفتاح في الرابط => الموديل .
     *
     * * مقفولة عن قصد . إضافة نوع جديد سطر هنا ، و اختبار
     * * MovementReviewTest بيتأكد إن كل واحد فيهم فعلا reviewable .
     */
    public const MOVEMENTS = [
        'money-received' => [\App\Models\MoneyReceived::class, 'money_received'],
        'money-payment' => [\App\Models\MoneyPayment::class, 'money_payment'],
        'factoring-with-recourse' => [\App\Models\FactoringTransaction::class, 'factoring_with_recourse'],
        'factoring-without-recourse' => [\App\Models\FactoringTransaction::class, 'factoring_without_recourse'],
        'lc-settlement-transfer' => [\App\Models\LcSettlementInternalMoneyTransfer::class, 'lc_settlement_transfer'],
        'cash-expense' => [\App\Models\CashExpense::class, 'cash_expense'],
        'multiple-cash-expense' => [\App\Models\MultipleCashExpense::class, 'multiple_cash_expense'],
        'internal-money-transfer' => [\App\Models\InternalMoneyTransfer::class, 'internal_money_transfer'],
        'buy-or-sell-currency' => [\App\Models\BuyOrSellCurrency::class, 'buy_or_sell_currency'],
        'foreign-exchange-rate' => [\App\Models\ForeignExchangeRate::class, 'foreign_exchange_rate'],
        'letter-of-guarantee-issuance' => [\App\Models\LetterOfGuaranteeIssuance::class, 'lg_issuance'],
        'letter-of-credit-issuance' => [\App\Models\LetterOfCreditIssuance::class, 'lc_issuance'],
    ];

    public function update(Request $request, Company $company, string $movement, int $id)
    {
        $definition = self::MOVEMENTS[$movement] ?? null;

        abort_if($definition === null, 404);

        [$model, $module] = $definition;

        /**
         * * الصلاحية بتاعة نوع الحركة نفسه ، مش صلاحية عامة : اللي
         * * بيراجع المصروفات مش بالضرورة يراجع خطابات الضمان .
         *
         * * متشيكة هنا مع إنها في RoutePermissionMap كمان — الميدلوير
         * * بيحمي الراوت ، و ده بيحمي الميثود لو حد نداها من مكان تاني ،
         * * و هو كمان اللي بيعرف أي موديول حسب النوع في الرابط
         */
        abort_unless(
            PermissionResolver::allows($request->user(), $module.'.review'),
            403,
            __('You do not have permission to perform this action.')
        );

        $validated = $request->validate([
            'reviewed' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        /**
         * * مربوط بالشركة اللي في الرابط : من غير كده أي مستخدم يقدر
         * * يراجع حركة في شركة تانية بتغيير الرقم بس
         */
        $record = $model::where('company_id', $company->id)->find($id);

        abort_if($record === null, 404);

        if (! in_array(IsReviewable::class, class_uses_recursive($record), true)) {
            abort(404);
        }

        $comment = $validated['comment'] ?? null;

        $changed = $validated['reviewed']
            ? $record->markReviewed($request->user(), $comment)
            : $record->markUnreviewed($request->user(), $comment);

        if (! $changed) {
            /**
             * * حد تاني سبقه . مش خطأ في المدخلات ، بس المستخدم لازم
             * * يعرف إن ضغطته ما عملتش حاجة
             */
            throw ValidationException::withMessages([
                'reviewed' => __('This movement is already in that state.'),
            ]);
        }

        return redirect()->back()->with('success', $validated['reviewed']
            ? __('Movement marked as reviewed.')
            : __('Review removed from the movement.'));
    }
}
