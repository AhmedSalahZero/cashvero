<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Support\Instructions\PageInstructions;
use Inertia\Inertia;

/**
 * InstructionsController
 * ------------------------------------------------------------------
 * Serves the written guide behind each screen's "Instructions" button.
 *
 * One controller and one Vue page for every guide: the content comes
 * from PageInstructions, so adding a guide for another screen is a new
 * array entry there and a button on that screen — no new route, no new
 * component.
 *
 * Read-only. It shows no company data at all, only text, which is why
 * it needs no permission of its own beyond being signed in.
 */
class InstructionsController
{
    public function show(Company $company, string $page)
    {
        abort_unless(PageInstructions::has($page), 404);

        $content = PageInstructions::get($page);

        return Inertia::render('Instructions/Show', [
            'company' => ['id' => $company->id],
            'pageKey' => $page,
            'title' => $content['title'],
            'summary' => $content['summary'],
            'sections' => $content['sections'],
            /**
             * Where "Back" goes. Sent from the server so the guide can
             * be opened from anywhere and still return to the right
             * screen, rather than relying on browser history.
             */
            'backUrl' => $this->backUrlFor(
                $company,
                $page,
                request()->query('financialInstitution'),
                request()->query('modelType'),
            ),
        ]);
    }

    /**
     * Where "Back" goes.
     *
     * Account-type screens live under one bank, so their route needs a
     * financial institution. The opening screen passes its own id along;
     * it is looked up SCOPED TO THIS COMPANY before being used, so a
     * hand-edited id cannot send the reader into another company's data.
     * With no usable id the reader lands on the institutions list, which
     * is always valid.
     */
    private function backUrlFor(
        Company $company,
        string $page,
        ?string $institutionId = null,
        ?string $modelType = null,
    ): string {
        $institution = $institutionId
            ? FinancialInstitution::where('company_id', $company->id)->find($institutionId)
            : null;

        $underInstitution = function (string $routeName) use ($company, $institution): string {
            if (! $institution) {
                return route('view.financial.institutions', ['company' => $company->id]);
            }

            return route($routeName, [
                'company' => $company->id,
                'financialInstitution' => $institution->id,
            ]);
        };

        // Balances drill-downs return to the side they came from.
        $balancesList = route('view.balances', [
            'company' => $company->id,
            'modelType' => $modelType === 'SupplierInvoice' ? 'SupplierInvoice' : 'CustomerInvoice',
        ]);

        return match ($page) {
            PageInstructions::MONEY_RECEIVED_FORM,
            PageInstructions::MONEY_RECEIVED_DOWN_PAYMENT,
            PageInstructions::MONEY_RECEIVED_INDEX => route('view.money.receive', ['company' => $company->id]),
            PageInstructions::MONEY_PAYMENT => route('view.money.payment', ['company' => $company->id]),
            PageInstructions::CASH_EXPENSE => route('view.cash.expense', ['company' => $company->id]),
            PageInstructions::INTERNAL_TRANSFER => route('internal-money-transfers.index', ['company' => $company->id]),
            PageInstructions::CURRENCY_EXCHANGE => route('buy-or-sell-currencies.index', ['company' => $company->id]),
            PageInstructions::LG_ISSUANCE => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            PageInstructions::LC_ISSUANCE => route('view.letter.of.credit.issuance', ['company' => $company->id]),
            PageInstructions::LC_SETTLEMENT => route('lc-settlement-internal-money-transfers.index', ['company' => $company->id]),
            PageInstructions::CUSTOMER_BALANCES => route('view.balances', ['company' => $company->id, 'modelType' => 'CustomerInvoice']),
            PageInstructions::SUPPLIER_BALANCES => route('view.balances', ['company' => $company->id, 'modelType' => 'SupplierInvoice']),
            PageInstructions::FINANCIAL_INSTITUTIONS => route('view.financial.institutions', ['company' => $company->id]),
            PageInstructions::CURRENT_ACCOUNT => $underInstitution('view.all.bank.accounts'),
            PageInstructions::TIME_OF_DEPOSIT => $underInstitution('view.time.of.deposit'),
            PageInstructions::CERTIFICATE_OF_DEPOSIT => $underInstitution('view.certificates.of.deposit'),
            PageInstructions::FULLY_SECURED_OVERDRAFT => $underInstitution('view.fully.secured.overdraft'),
            PageInstructions::CLEAN_OVERDRAFT => $underInstitution('view.clean.overdraft'),
            PageInstructions::OVERDRAFT_COMMERCIAL_PAPER => $underInstitution('view.overdraft.against.commercial.paper'),
            PageInstructions::OVERDRAFT_ASSIGNMENT_OF_CONTRACTS => $underInstitution('view.overdraft.against.assignment.of.contract'),
            PageInstructions::LG_FACILITY => $underInstitution('view.letter.of.guarantee.facility'),
            PageInstructions::LC_FACILITY => $underInstitution('view.letter.of.credit.facility'),
            PageInstructions::MEDIUM_TERM_LOAN => $underInstitution('loans.index'),
            PageInstructions::INVOICE_REPORT,
            PageInstructions::INVOICE_STATEMENT,
            PageInstructions::NET_BALANCE_DETAILS,
            PageInstructions::DOWN_PAYMENT_SETTLEMENT,
            PageInstructions::ADJUST_DUE_DATE => $balancesList,
            PageInstructions::TD_PERIOD_INTEREST,
            PageInstructions::TD_RENEWAL_HISTORY => $underInstitution('view.time.of.deposit'),
            PageInstructions::CD_PERIOD_INTEREST => $underInstitution('view.certificates.of.deposit'),
            PageInstructions::MTL_STATEMENT => $underInstitution('loans.index'),
            PageInstructions::LG_RENEWAL_HISTORY => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            PageInstructions::MONEY_PAYMENT_FORM,
            PageInstructions::MONEY_PAYMENT_DOWN_PAYMENT => route('view.money.payment', ['company' => $company->id]),
            PageInstructions::CASH_EXPENSE_FORM => route('view.cash.expense', ['company' => $company->id]),
            PageInstructions::INTERNAL_TRANSFER_FORM => route('internal-money-transfers.index', ['company' => $company->id]),
            PageInstructions::CURRENCY_EXCHANGE_FORM => route('buy-or-sell-currencies.index', ['company' => $company->id]),
            PageInstructions::LC_SETTLEMENT_FORM => route('lc-settlement-internal-money-transfers.index', ['company' => $company->id]),
            PageInstructions::LG_ISSUANCE_FORM => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            PageInstructions::LC_ISSUANCE_FORM => route('view.letter.of.credit.issuance', ['company' => $company->id]),
            PageInstructions::FINANCIAL_INSTITUTION_FORM => route('view.financial.institutions', ['company' => $company->id]),
            PageInstructions::CURRENT_ACCOUNT_FORM => $underInstitution('view.all.bank.accounts'),
            PageInstructions::TIME_OF_DEPOSIT_FORM => $underInstitution('view.time.of.deposit'),
            PageInstructions::CERTIFICATE_OF_DEPOSIT_FORM => $underInstitution('view.certificates.of.deposit'),
            PageInstructions::CLEAN_OVERDRAFT_FORM => $underInstitution('view.clean.overdraft'),
            PageInstructions::FULLY_SECURED_OVERDRAFT_FORM => $underInstitution('view.fully.secured.overdraft'),
            PageInstructions::OVERDRAFT_COMMERCIAL_PAPER_FORM => $underInstitution('view.overdraft.against.commercial.paper'),
            PageInstructions::OVERDRAFT_ASSIGNMENT_FORM => $underInstitution('view.overdraft.against.assignment.of.contract'),
            PageInstructions::LG_FACILITY_FORM => $underInstitution('view.letter.of.guarantee.facility'),
            PageInstructions::LC_FACILITY_FORM => $underInstitution('view.letter.of.credit.facility'),
            PageInstructions::MEDIUM_TERM_LOAN_FORM => $underInstitution('loans.index'),
            PageInstructions::FACTORING,
            PageInstructions::FACTORING_FORM => route('factoring.with-recourse.index', ['company' => $company->id]),
            /*
             * The factoring contract screens hang off a factoring company,
             * so their own route needs one. Without an id to scope, the
             * financial-institutions list is the honest landing place — it
             * is where the factoring companies are.
             */
            PageInstructions::FACTORING_CONTRACTS,
            PageInstructions::FACTORING_CONTRACT_FORM => route('view.financial.institutions', ['company' => $company->id]),
            default => route('home', ['company' => $company->id]),
        };
    }
}
