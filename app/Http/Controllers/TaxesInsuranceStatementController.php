<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Partner;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TaxesInsuranceStatementController
 * ------------------------------------------------------------------
 * Feature (client requested, 2026-08-15): Taxes & Insurance used to be
 * one of the "Partner Type" options inside the generic Partner Statement
 * report, sharing that page's running-balance ledger layout (Beginning
 * Balance / Debit / Credit / End Balance). But Taxes & Insurance isn't a
 * real two-sided statement the way Employee/Shareholder/etc. are —
 * handlePartnerCreditStatement() (money received) never had an is_tax
 * branch at all, so this was always payments-only. Dressing it up as a
 * balanced ledger was misleading.
 *
 * This gives it its own dedicated page instead, with the simpler shape
 * the client asked for: Date / Currency / Paid To / Amount / Accumulated
 * Amount / Comment. "Accumulated Amount" is a plain running total of
 * payments (per partner + currency, in date order) — not the signed
 * ledger end_balance from tax_statements (which — after the companion
 * debit/credit fix in HasPartnerStatement.php — trends negative as
 * payments accrue, since it's modelling a payable; a business user
 * asking "how much have we paid this partner so far" expects a plain
 * growing total, not a negative payable balance).
 */
class TaxesInsuranceStatementController extends Controller
{
    public function index(Company $company)
    {
        return \Inertia\Inertia::render('Statements/TaxesInsurance/Index', [
            'company' => ['id' => $company->id],
            'currencies' => getCurrency(),
            'partners' => Partner::onlyForCompany($company->id)
                ->where('is_tax', 1)
                ->get()
                ->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->getName()])
                ->values(),
            'urls' => [
                'result' => route('result.taxes.insurance.statement', ['company' => $company->id]),
            ],
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $currency = $request->get('currency');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $partnerIds = array_values(array_filter(
            array_map('intval', (array) $request->get('partner_id', [])),
            fn (int $id) => $id > 0
        ));

        $query = DB::table('tax_statements')
            ->join('partners', 'partners.id', '=', 'tax_statements.partner_id')
            ->where('tax_statements.company_id', $company->id);

        if ($currency) {
            $query->where('tax_statements.currency_name', $currency);
        }
        if ($startDate) {
            $query->where('tax_statements.date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('tax_statements.date', '<=', $endDate);
        }
        if ($partnerIds !== []) {
            $query->whereIn('tax_statements.partner_id', $partnerIds);
        }

        $rows = $query
            ->orderBy('tax_statements.partner_id')
            ->orderBy('tax_statements.currency_name')
            ->orderBy('tax_statements.full_date')
            ->orderBy('tax_statements.id')
            ->get([
                'tax_statements.id',
                'tax_statements.date',
                'tax_statements.currency_name',
                'tax_statements.partner_id',
                'partners.name as partner_name',
                'tax_statements.credit as amount',
                'tax_statements.comment_en',
                'tax_statements.comment_ar',
            ]);

        // Running total per partner + currency, in the order already
        // fetched (chronological within each group).
        $accumulated = [];
        $lang = lang();
        $result = $rows->map(function ($row) use (&$accumulated, $lang, $company) {
            $key = $row->partner_id.'|'.$row->currency_name;
            $accumulated[$key] = ($accumulated[$key] ?? 0) + (float) $row->amount;

            return [
                'id' => $row->id,
                'date' => $row->date,
                'currency' => $row->currency_name,
                'paid_to' => $row->partner_name,
                'amount' => (float) $row->amount,
                'accumulated_amount' => $accumulated[$key],
                'comment' => AccountNumberLabel::decorateText(
                    (int) $company->id,
                    $lang === 'ar' ? ($row->comment_ar ?: $row->comment_en) : ($row->comment_en ?: $row->comment_ar)
                ),
            ];
        })->values();

        return \Inertia\Inertia::render('Statements/TaxesInsurance/Index', [
            'company' => ['id' => $company->id],
            'currencies' => getCurrency(),
            'partners' => Partner::onlyForCompany($company->id)
                ->where('is_tax', 1)
                ->get()
                ->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->getName()])
                ->values(),
            'urls' => [
                'result' => route('result.taxes.insurance.statement', ['company' => $company->id]),
            ],
            'filters' => [
                'currency' => $currency,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'partner_id' => $partnerIds,
            ],
            'rows' => $result,
            'totalAmount' => $rows->sum('amount'),
        ]);
    }
}
