<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMultipleCashExpenseRequest;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\CashExpenseCategory;
use App\Models\CashExpenseCategoryName;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\MultipleCashExpense;
use App\Models\MultipleCashExpenseAllocation;
use App\Models\MultipleCashExpenseItem;
use App\Models\Partner;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * MultipleCashExpenseController
 * ------------------------------------------------------------------
 * المصروفات النقدية المتعددة : حركة صرف واحدة فيها أكتر من بند مصروف
 *
 * نسخة مستقلة تمامًا عن CashExpenseController — ما بتلمسش أي حاجة من
 * المصروفات الحالية . و ملهاش تكامل مع أودو حاليًا ، فالشاشة كلها
 * بتختفي لو الشركة عندها بيانات أودو (شوف ensureAvailable)
 *
 * ⚠️ أهم حاجة هنا : كل بند بينزل **سطر مستقل** في كشف الحساب بمبلغه ،
 * مش سطر واحد بالإجمالي — عشان كده البند (item) هو اللي بيكتب سطر
 * الكشف ، مش الحركة الأم
 */
class MultipleCashExpenseController extends Controller
{
    /**
     * * الشاشة دي مالهاش تكامل مع أودو ، فما تظهرش أصلا للشركة اللي
     * * شغالة على أودو — و إلا هتبقى حركات مالية مش موجودة عندهم هناك
     *
     * * البيانات القديمة بتفضل زي ما هي لو الشركة فعّلت أودو بعدين :
     * * الشاشة بتختفي بس ، و أسطر الكشوف ما بتتلمسش
     */
    private function ensureAvailable(Company $company): void
    {
        abort_if($company->hasOdooCredentials(), 404);
    }

    public function index(Company $company, Request $request)
    {
        $this->ensureAvailable($company);

        $query = MultipleCashExpense::where('company_id', $company->id)
            ->with(['items.cashExpenseCategoryName'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->get('search'));
                $q->where(fn ($inner) => $inner->where('user_comment', 'like', "%{$term}%")
                    ->orWhere('receipt_number', 'like', "%{$term}%")
                    ->orWhere('id', $term));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->get('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->get('to')))
            ->orderByDesc('id');

        $paginator = $query->paginate(25, ['*'], 'page', max(1, (int) $request->get('page', 1)))->withQueryString();

        $canUpdate = PermissionResolver::allows($request->user(), 'multiple_cash_expense.update');
        $canDelete = PermissionResolver::allows($request->user(), 'multiple_cash_expense.delete');
        $canCreate = PermissionResolver::allows($request->user(), 'multiple_cash_expense.create');

        return Inertia::render('MultipleCashExpense/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'canCreate' => $canCreate,
            'canUpdate' => $canUpdate,
            'canDelete' => $canDelete,
            'filters' => [
                'search' => (string) $request->get('search', ''),
                'from' => (string) $request->get('from', ''),
                'to' => (string) $request->get('to', ''),
            ],
            'rows' => collect($paginator->items())->map(fn (MultipleCashExpense $expense) => [
                'id' => $expense->id,
                'payment_date' => $expense->getPaymentDateFormatted(),
                'type' => $expense->getTypeFormatted(),
                'currency' => $expense->getCurrency(),
                'total' => $expense->getAmountFormatted(),
                'items_count' => $expense->items->count(),
                'user_comment' => $expense->user_comment,
                /**
                 * * توزيع البنود بيتبعت مع الصف نفسه : عددها قليل و
                 * * محمّلة مسبقًا ، فمفيش داعي لنداء تاني عشان بوب اب
                 */
                'items' => $expense->items->map(fn (MultipleCashExpenseItem $item) => [
                    'name' => $item->getExpenseName(),
                    'category' => $item->getCategoryName(),
                    'amount' => $item->getAmountFormatted(),
                ])->values(),
                'edit_url' => $canUpdate ? route('multiple-cash-expenses.edit', ['company' => $company->id, 'multiple_cash_expense' => $expense->id]) : null,
                'delete_url' => $canDelete ? route('multiple-cash-expenses.destroy', ['company' => $company->id, 'multiple_cash_expense' => $expense->id]) : null,
            ])->values(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'createUrl' => route('multiple-cash-expenses.create', ['company' => $company->id]),
            'indexUrl' => route('multiple-cash-expenses.index', ['company' => $company->id]),
        ]);
    }

    public function create(Company $company)
    {
        $this->ensureAvailable($company);

        return Inertia::render('MultipleCashExpense/Form', $this->buildFormProps($company, null));
    }

    public function edit(Company $company, MultipleCashExpense $multipleCashExpense)
    {
        $this->ensureAvailable($company);
        abort_unless($multipleCashExpense->company_id === $company->id, 404);

        return Inertia::render('MultipleCashExpense/Form', $this->buildFormProps($company, $multipleCashExpense));
    }

    public function store(Company $company, StoreMultipleCashExpenseRequest $request)
    {
        $this->ensureAvailable($company);

        DB::transaction(function () use ($company, $request) {
            $this->persist($company, $request, null);
        });

        return redirect()
            ->route('multiple-cash-expenses.index', ['company' => $company->id])
            ->with('success', __('Data Stored Successfully'));
    }

    public function update(Company $company, StoreMultipleCashExpenseRequest $request, MultipleCashExpense $multipleCashExpense)
    {
        $this->ensureAvailable($company);
        abort_unless($multipleCashExpense->company_id === $company->id, 404);

        DB::transaction(function () use ($company, $request, $multipleCashExpense) {
            $this->persist($company, $request, $multipleCashExpense);
        });

        return redirect()
            ->route('multiple-cash-expenses.index', ['company' => $company->id])
            ->with('success', __('Data Updated Successfully'));
    }

    public function destroy(Company $company, MultipleCashExpense $multipleCashExpense)
    {
        $this->ensureAvailable($company);
        abort_unless($multipleCashExpense->company_id === $company->id, 404);

        DB::transaction(fn () => $multipleCashExpense->delete());

        return redirect()
            ->route('multiple-cash-expenses.index', ['company' => $company->id])
            ->with('success', __('Data Deleted Successfully'));
    }

    /**
     * * الحفظ و التعديل بيمشوا بنفس الطريقة : التعديل بيشيل البنود
     * * القديمة و أسطر كشوفها الأول ، فالكشف ما يبقاش فيه أثر لبند
     * * اتشال أو اتغيّر مبلغه
     */
    private function persist(Company $company, StoreMultipleCashExpenseRequest $request, ?MultipleCashExpense $existing): MultipleCashExpense
    {
        $type = $request->input('type');
        $currency = $request->input('currency');

        $header = [
            'type' => $type,
            'payment_date' => $request->input('payment_date'),
            'currency' => $currency,
            'exchange_rate' => $request->input('exchange_rate') ?: 1,
            'user_comment' => $request->input('user_comment'),
            'delivery_bank_id' => $request->input('delivery_bank_id'),
            'account_type' => $request->input('account_type'),
            'account_number' => $request->input('account_number'),
            'delivery_branch_id' => $request->input('delivery_branch_id'),
            'receipt_number' => $request->input('receipt_number'),
            'cheque_number' => $request->input('cheque_number'),
            'due_date' => $request->input('due_date'),
            'company_id' => $company->id,
            'user_id' => $request->user()?->id,
        ];

        if ($existing) {
            /**
             * * البنود القديمة بتتشال بأسطر كشوفها — و إلا الرصيد يفضل
             * * متخصوم منه مبلغ اتغيّر
             */
            foreach ($existing->items as $item) {
                $item->deleteStatements();
                $item->delete();
            }

            $existing->update($header);
            $expense = $existing->refresh();
        } else {
            $expense = MultipleCashExpense::create($header);
        }

        $accountType = $request->filled('account_type') ? AccountType::find($request->input('account_type')) : null;
        $statementDate = $expense->getStatementDate();

        $createdItems = [];

        foreach ((array) $request->input('items', []) as $itemInput) {
            $amount = (float) $itemInput['paid_amount'];

            $item = MultipleCashExpenseItem::create([
                'multiple_cash_expense_id' => $expense->id,
                'cash_expense_category_name_id' => $itemInput['cash_expense_category_name_id'],
                'paid_amount' => $amount,
                'company_id' => $company->id,
            ]);

            $comment = $item->getExpenseName();

            /**
             * * كل بند بينزل سطر مستقل بمبلغه — ده جوهر الشاشة دي
             */
            $item->handleCreditStatement(
                $company->id,
                $request->input('delivery_bank_id'),
                $accountType,
                $request->input('account_number'),
                $type,
                $statementDate,
                $amount,
                $request->input('delivery_branch_id'),
                $currency,
                $comment,
                $comment
            );

            $createdItems[] = $item;
        }

        /**
         * * الإجمالي بيتحسب من البنود مش من إدخال منفصل ، فما ينفعش
         * * يختلف عن مجموع اللي نزل في الكشف
         */
        $expense->update(['paid_amount' => $expense->refresh()->calculateTotalFromItems()]);

        $this->saveAllocations($company, $createdItems, (array) $request->input('allocations', []));

        return $expense;
    }

    /**
     * * التوزيع مربوط ببند بعينه : الفورمة بتبعت ترتيب البند في الـ
     * * repeater ، و إحنا بنترجمه لهوية البند بعد ما يتحفظ
     *
     * @param  array<int, MultipleCashExpenseItem>  $items
     */
    private function saveAllocations(Company $company, array $items, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $contractId = $allocation['contract_id'] ?? null;
            $amount = (float) ($allocation['amount'] ?? 0);
            $itemIndex = $allocation['item_index'] ?? null;

            if (! $contractId || $amount <= 0 || ! isset($items[$itemIndex])) {
                continue;
            }

            MultipleCashExpenseAllocation::create([
                'multiple_cash_expense_item_id' => $items[$itemIndex]->id,
                'contract_id' => $contractId,
                'amount' => $amount,
                'company_id' => $company->id,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormProps(Company $company, ?MultipleCashExpense $model): array
    {
        $categories = CashExpenseCategory::where('company_id', $company->id)->orderBy('name')->get();
        $categoryNames = CashExpenseCategoryName::whereIn('cash_expense_category_id', $categories->pluck('id'))->get();

        $model?->load(['items.allocations.contract', 'items.cashExpenseCategoryName']);

        return [
            'company' => ['id' => $company->id, 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'mode' => $model ? 'edit' : 'create',
            'locale' => app()->getLocale(),
            'types' => [
                MultipleCashExpense::CASH_PAYMENT => __('Cash Payment'),
                MultipleCashExpense::PAYABLE_CHEQUE => __('Payable Cheque'),
                MultipleCashExpense::OUTGOING_TRANSFER => __('Outgoing Transfer'),
            ],
            'currencies' => getCurrencies(),
            'categories' => $categories->map(fn ($c) => ['id' => $c->getId(), 'name' => $c->getName()])->values(),
            'categoryNames' => $categoryNames->map(fn ($n) => [
                'id' => $n->id,
                'name' => $n->getName(),
                'category_id' => $n->cash_expense_category_id,
            ])->values(),
            'branches' => collect(Branch::getBranchesForCurrentCompany($company->id))
                ->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get()
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
            'accountTypes' => AccountType::onlyCashAccounts()->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'type' => $model->getType(),
                'payment_date' => $model->getPaymentDate(),
                'currency' => $model->getCurrency(),
                'exchange_rate' => $model->exchange_rate,
                'user_comment' => $model->user_comment,
                'delivery_bank_id' => $model->delivery_bank_id,
                'account_type' => $model->account_type,
                'account_number' => $model->account_number,
                'delivery_branch_id' => $model->delivery_branch_id,
                'receipt_number' => $model->receipt_number,
                'cheque_number' => $model->cheque_number,
                'due_date' => $model->due_date,
                'items' => $model->items->map(fn (MultipleCashExpenseItem $item) => [
                    'cash_expense_category_name_id' => $item->cash_expense_category_name_id,
                    'category_id' => $item->cashExpenseCategoryName?->cash_expense_category_id,
                    'paid_amount' => (float) $item->paid_amount,
                ])->values(),
                /**
                 * * الصف بيرجع بكل اللي الشاشة محتاجاه عشان يتعرض زي ما
                 * * اتحفظ : العميل و العقد و كوده و قيمته — من غيرهم
                 * * القوائم بتفتح فاضية عند التعديل
                 */
                'allocations' => $model->items->values()->flatMap(
                    fn (MultipleCashExpenseItem $item, int $index) => $item->allocations->map(fn ($a) => [
                        'item_index' => $index,
                        'partner_id' => $a->contract?->client?->id,
                        'contract_id' => $a->contract_id,
                        'contract_code' => $a->contract?->getCode(),
                        'contract_amount' => $a->contract?->getAmount(),
                        'contract_currency' => $a->contract?->getCurrency(),
                        'amount' => (float) $a->amount,
                    ])
                )->values(),
            ] : null,
            /**
             * * التوزيع على العقود هو هو اللي في شاشة المصروف العادي :
             * * تختار عميل ، عقوده بتتحمّل من نفس الـ endpoint ، و كود
             * * العقد و قيمته بيتملوا لوحدهم — فبيحتاج نفس المدخلات
             */
            'clientsWithContracts' => Partner::onlyCompany($company->id)
                ->onlyCustomers()->onlyThatHaveCustomerContracts()->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->getName()])->values(),
            'getContractsForCustomerUrl' => route('get.contracts.for.customer.or.supplier', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('multiple-cash-expenses.update', ['company' => $company->id, 'multiple_cash_expense' => $model->id])
                : route('multiple-cash-expenses.store', ['company' => $company->id]),
            'backUrl' => route('multiple-cash-expenses.index', ['company' => $company->id]),
        ];
    }
}
