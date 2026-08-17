<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreExpenseItemRequest;
use App\Models\CashExpenseCategory;
use App\Models\Company;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

/**
 * CashExpenseCategoryController
 * ------------------------------------------------------------------
 * Manages Cash Expense Categories — a two-level structure: a parent
 * Category (e.g. "Utilities") containing one or more Expense Item
 * Names (e.g. "Electricity", "Water"), each of which is what actually
 * gets picked when logging a cash expense elsewhere in the app
 * (App\Models\CashExpense::cash_expense_category_name_id).
 *
 * When the company has Odoo integration configured, each expense item
 * also carries an "Odoo Chart Of Account Number" — this field is
 * hidden entirely for companies without Odoo credentials, exactly as
 * in the original Blade (`@if($company->hasOdooIntegrationCredentials())`).
 * `StoreExpenseItemRequest` only validates that field (a live Odoo
 * lookup via `OdooService::syncChartOfAccountNumbers()`) when Odoo is
 * configured — UNCHANGED, deliberately; this is a real network call
 * against Odoo's chart of accounts, not touched by this migration.
 *
 * `store()`/`update()` still call `storeBasicForm()` (see
 * App\Traits\HasBasicStoreRequest) to save both the parent's `name`
 * and the `cashExpenseCategoryNames` repeater in one call — this is
 * safe here (unlike PartnersController, see its docblock) because
 * none of the fields on this form are boolean, so the JSON-boolean
 * coercion bug that trait has doesn't apply. The repeater matches
 * rows by `id` (0 = new row) exactly as the original Blade did.
 *
 * `updateExpenseCategoryNameBasedOnCategory()` is a separate AJAX
 * endpoint used by other, still-Blade pages (expense entry forms) —
 * UNCHANGED, out of scope for this migration.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/CashExpenses/Index.vue.
 *   ✅ create() / edit() → MIGRATED. Both render
 *      resources/js/Pages/CashExpenses/Form.vue, distinguished by a
 *      `mode: 'create' | 'edit'` prop.
 *   ⚠️ store() / update() → added flash messages
 *      (`->with('success', ...)`) — the original had none, every
 *      other migrated page in this project shows one. Field-saving
 *      logic (storeBasicForm() + the Odoo sync loop) is unchanged.
 *   ⚠️ destroy() → same flash-message addition, otherwise unchanged.
 *   🔲 updateExpenseCategoryNameBasedOnCategory() → NOT migrated,
 *      deliberately (used by other still-Blade pages).
 */
class CashExpenseCategoryController
{
    use GeneralFunctions;

    public function index(Company $company)
    {
        $categories = CashExpenseCategory::where('company_id', $company->id)
            ->with('cashExpenseCategoryNames')
            ->orderBy('name')
            ->get();

        $rows = $categories->map(function (CashExpenseCategory $category) use ($company) {
            return [
                'id' => $category->id,
                'name' => $category->getName(),
                'items' => $category->cashExpenseCategoryNames->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->getName(),
                ])->values(),
                'edit_url' => route('cash.expense.category.edit', ['company' => $company->id, 'cashExpenseCategory' => $category->id]),
                'delete_url' => route('cash.expense.category.destroy', ['company' => $company->id, 'cashExpenseCategory' => $category->id]),
            ];
        })->values();

        return \Inertia\Inertia::render('CashExpenses/Index', [
            'company' => ['id' => $company->id],
            'categories' => $rows,
            'createUrl' => route('cash.expense.category.create', ['company' => $company->id]),
            // Previously ungated in the UI — this list had no permission
            // flags at all (2026-08 audit, F-07).
            'permissions' => [
                'canCreate' => hasAuthFor('cash_expense_category.create'),
                'canUpdate' => hasAuthFor('cash_expense_category.update'),
                'canDelete' => hasAuthFor('cash_expense_category.delete'),
            ],
        ]);
    }

    public function create(Company $company)
    {
        return \Inertia\Inertia::render('CashExpenses/Form', [
            'mode' => 'create',
            'company' => ['id' => $company->id],
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'submitUrl' => route('cash.expense.category.store', ['company' => $company->id]),
            'backUrl' => route('cash.expense.category.index', ['company' => $company->id]),
        ]);
    }

    public function store(StoreExpenseItemRequest $request, Company $company)
    {
        $cashExpenseCategory = new CashExpenseCategory();
        $cashExpenseCategory->storeBasicForm($request);
        if ($company->hasOdooIntegrationCredentials()) {
            foreach ($request->get('cashExpenseCategoryNames', []) as $cashExpenseName) {
                $odooService = new OdooService($company);
                $code = $cashExpenseName['odoo_chart_of_account_number'];
                $odooService->syncChartOfAccountNumbers($code, $company->id);
            }
        }

        return redirect()
            ->route('cash.expense.category.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function edit(Company $company, CashExpenseCategory $cashExpenseCategory)
    {
        return \Inertia\Inertia::render('CashExpenses/Form', [
            'mode' => 'edit',
            'company' => ['id' => $company->id],
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'submitUrl' => route('cash.expense.category.update', ['company' => $company->id, 'cashExpenseCategory' => $cashExpenseCategory->id]),
            'backUrl' => route('cash.expense.category.index', ['company' => $company->id]),
            'category' => [
                'id' => $cashExpenseCategory->id,
                'name' => $cashExpenseCategory->getName(),
                'items' => $cashExpenseCategory->cashExpenseCategoryNames->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->getName(),
                    'odoo_chart_of_account_number' => $item->getOdooChartOfAccountNumber(),
                ])->values(),
            ],
        ]);
    }

    public function update(Company $company, StoreExpenseItemRequest $request, CashExpenseCategory $cashExpenseCategory)
    {
        $cashExpenseCategory->storeBasicForm($request);
        if ($company->hasOdooIntegrationCredentials()) {
            foreach ($request->get('cashExpenseCategoryNames', []) as $cashExpenseName) {
                $odooService = new OdooService($company);
                $code = $cashExpenseName['odoo_chart_of_account_number'];
                $odooService->syncChartOfAccountNumbers($code, $company->id);
            }
        }

        return redirect()
            ->route('cash.expense.category.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, Request $request, CashExpenseCategory $cashExpenseCategory)
    {
        $cashExpenseCategory->delete();

        return redirect()
            ->route('cash.expense.category.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }

    /**
     * UNCHANGED — pure AJAX data endpoint used by other, still-Blade
     * pages (expense entry forms elsewhere) to populate a dependent
     * dropdown. Out of scope for this migration.
     */
    public function updateExpenseCategoryNameBasedOnCategory(Company $company, Request $request)
    {
        $caseExpensesIds = (array) $request->get('expenseCategoryId', []);
        $expenseCategories = CashExpenseCategory::whereIn('id', $caseExpensesIds)->get();
        $result = [];
        foreach ($expenseCategories as $expenseCategory) {
            $subItems = $expenseCategory->cashExpenseCategoryNames->sortBy('name')->pluck('id', 'name')->toArray();
            foreach ($subItems as $name => $id) {
                $result[$name] = $id;
            }
        }

        return response()->json([
            'categoryNames' => $result,
        ]);
    }
}
