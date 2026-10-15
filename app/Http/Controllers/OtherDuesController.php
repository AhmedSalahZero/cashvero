<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OtherDue;
use App\Models\Partner;
use App\Support\Instructions\PageInstructions;
use App\Support\OtherDues\OtherDueStatements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OtherDuesController
 * ------------------------------------------------------------------
 * "Other Dues" — amounts owed either way with a partner that are not
 * invoices: a deposit held at a customer, a retention, a balance carried
 * in from before the company started using CashVero.
 *
 * Shaped like the opening balance screens it sits beside: one repeater,
 * saved whole, dated on the company's opening balance date.
 *
 * Two deliberate behaviours worth knowing:
 *   - the same partner may appear on several rows and they are NOT
 *     summed — each due is its own item with its own comment;
 *   - only partner types that keep a ledger are offered, so every due
 *     written is one somebody can read back on a statement. See
 *     App\Support\OtherDues\OtherDueStatements.
 */
class OtherDuesController
{
    /**
     * The partner types a due can be recorded against — every one of them
     * keeps a ledger, so every due written can be read back.
     *
     * Customers and suppliers are excluded deliberately. They keep no
     * partner ledger; their statement is derived from invoices, and an
     * amount that is not an invoice has no honest place in it. Their
     * outstanding balances belong in the opening invoices repeater, where
     * they can be settled and aged like anything else.
     */
    public const PARTNER_TYPES = [
        'is_subsidiary_company' => 'Subsidiary Company',
        'is_shareholder' => 'Shareholder',
        'is_employee' => 'Employee',
        'is_other_partner' => 'Other Partner',
        'is_tax' => 'Taxes & Social Insurance',
    ];

    public function index(Company $company)
    {
        $dues = OtherDue::where('company_id', $company->id)
            ->orderBy('id')
            ->get()
            ->map(fn (OtherDue $due) => $this->rowFor($due))
            ->values();

        return \Inertia\Inertia::render('OtherDues/Form', [
            'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::OTHER_DUES]),
            'company' => ['id' => $company->id],
            'openingBalanceDate' => OtherDueStatements::dateFor($company),
            'rows' => $dues,
            'directions' => collect(OtherDue::directions())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'partnerTypes' => collect(self::PARTNER_TYPES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => __($label)])->values(),
            'currencies' => collect(getCurrencies())
                ->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values(),
            'mainCurrency' => $company->getMainFunctionalCurrency(),
            'partnersUrl' => route('other-dues.partners', ['company' => $company->id]),
            'submitUrl' => route('other-dues.store', ['company' => $company->id]),
            'backUrl' => route('suppliers-opening-balance.index', ['company' => $company->id]),
        ]);
    }

    /**
     * Partners of one type, sorted by name — the select on each row is
     * searchable, so it is fed the whole list rather than paginated.
     */
    public function partners(Company $company, Request $request)
    {
        $type = $request->get('partner_type');

        if (! array_key_exists($type, self::PARTNER_TYPES)) {
            return response()->json([]);
        }

        return response()->json(
            Partner::where('company_id', $company->id)
                ->where($type, 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])
                ->values()
        );
    }

    /**
     * Saves the repeater whole: rows not sent back are gone, which is how
     * the opening balance screens beside this one already behave.
     */
    public function store(Company $company, Request $request)
    {
        $validated = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.direction' => ['required', 'in:'.OtherDue::DUE_FROM.','.OtherDue::DUE_TO],
            'rows.*.partner_type' => ['required', 'in:'.implode(',', array_keys(self::PARTNER_TYPES))],
            'rows.*.partner_id' => ['required', 'integer'],
            'rows.*.amount' => ['required', 'numeric', 'gt:0'],
            'rows.*.currency' => ['required', 'string', 'max:16'],
            'rows.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'rows.*.comment' => ['nullable', 'string'],
        ], [
            'rows.*.amount.gt' => __('The amount must be greater than zero.'),
            'rows.*.exchange_rate.gt' => __('The exchange rate must be greater than zero.'),
        ]);

        $mainCurrency = $company->getMainFunctionalCurrency();

        // A partner must actually be of the type the row claims, or the
        // due would land in a statement the partner does not belong to.
        foreach ($validated['rows'] as $i => $row) {
            $belongs = Partner::where('company_id', $company->id)
                ->where('id', $row['partner_id'])
                ->where($row['partner_type'], 1)
                ->exists();

            if (! $belongs) {
                return back()->withErrors([
                    "rows.{$i}.partner_id" => __('This partner is not of the selected type.'),
                ]);
            }

            if ($row['currency'] !== $mainCurrency && empty($row['exchange_rate'])) {
                return back()->withErrors([
                    "rows.{$i}.exchange_rate" => __('An exchange rate is required when the currency is not the main currency.'),
                ]);
            }
        }

        DB::transaction(function () use ($company, $validated, $mainCurrency) {
            // Clearing the ledger rows through the model (not a mass
            // delete) so each statement recalculates the balances that
            // follow it, exactly as it does on any other deletion.
            foreach (OtherDue::where('company_id', $company->id)->get() as $existing) {
                OtherDueStatements::remove($existing);
                $existing->delete();
            }

            foreach ($validated['rows'] as $row) {
                $due = OtherDue::create([
                    'company_id' => $company->id,
                    'partner_id' => $row['partner_id'],
                    'partner_type' => $row['partner_type'],
                    'direction' => $row['direction'],
                    'amount' => $row['amount'],
                    'currency' => $row['currency'],
                    'exchange_rate' => $row['currency'] === $mainCurrency ? null : $row['exchange_rate'],
                    'comment' => $row['comment'] ?? null,
                ]);

                OtherDueStatements::sync($due, $company);
            }
        });

        return redirect()
            ->route('other-dues.index', ['company' => $company->id])
            ->with('success', __('Saved successfully'));
    }

    private function rowFor(OtherDue $due): array
    {
        return [
            'id' => $due->id,
            'direction' => $due->direction,
            'partner_type' => $due->partner_type,
            'partner_id' => $due->partner_id,
            'partner_name' => $due->partner?->getName(),
            'amount' => (float) $due->amount,
            'currency' => $due->currency,
            'exchange_rate' => $due->exchange_rate !== null ? (float) $due->exchange_rate : null,
            'comment' => $due->comment,
        ];
    }
}
