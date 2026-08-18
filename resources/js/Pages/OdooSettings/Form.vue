<script setup>
import { ref, reactive } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';

const props = defineProps({
    company: Object,
    model: Object, // null if no settings saved yet
    financialInstitutionBanks: Array, // [{id, name}]
    interestRevenueAccounts: Array,   // [{financial_institution_id, odoo_code}]
    submitUrl: String,
    navUrls: Object,
});

const page = usePage();

/*
 * Every field below is a plain "Chart Of Account Number" text input —
 * grouped into the same 6 sections as the original form. Confirmed
 * omission preserved: the model also has insurance_to_account_code,
 * but the original form never exposes it — not added here either.
 */
const sections = [
    {
        title: 'Liquidity / Treasury Accounts',
        fields: [
            ['liquidity_transfer_account_code', 'Liquidity / Treasury Clearance Account'],
            ['custody_account_code', 'Custody Account'],
            ['employee_loans_account_code', 'Advanced / Employee Loans Account'],
            ['cheques_receivable_code', 'Notes/Cheques Receivables'],
            ['cheques_payable_code', 'Notes/Cheques Payables'],
            ['shareholder_account_code', 'Shareholders Account'],
            ['dividend_payable_account_code', 'Dividend Payable Account'],
            ['insurance_from_account_code', 'Insurance From Account'],
            ['advances_to_suppliers_code', 'Advances To Suppliers'],
            ['advances_from_customers_code', 'Advances From Customers'],
            ['investment_in_subsidiary_company_code', 'Investment In Subsidiary Company'],
        ],
    },
    {
        title: 'LG & LC Cash Cover Accounts',
        fields: [
            ['bid_lg_cash_cover_code', 'Bid LG Cash Cover'],
            ['final_lg_cash_cover_code', 'Final LG Cash Cover'],
            ['advanced_lg_cash_cover_code', 'Advanced LG Cash Cover'],
            ['performance_lg_cash_cover_code', 'Performance LG Cash Cover'],
            ['sight_lc_cash_cover_code', 'Sight Lc Cash Cover'],
            ['deferred_lc_cash_cover_code', 'Deferred Lc Cash Cover'],
        ],
    },
    {
        title: 'Taxes & Social Insurance',
        fields: [
            ['vat_taxes_code', 'VAT Taxes'],
            ['credit_withhold_taxes_code', 'Credit Withhold Taxes'],
            ['salary_taxes_code', 'Salary Taxes'],
            ['social_insurance_code', 'Social Insurance'],
            ['income_taxes_code', 'Income Taxes'],
            ['takaful_code', 'Takaful Contribution Tax'],
            ['tax_for_victims_code', 'Tax for the Support of Victims Fund'],
            ['real_estate_taxes_code', 'Real Estate Taxes'],
            ['stamp_duty_taxes_code', 'Stamp Duty Taxes'],
            ['other_taxes_code', 'Other Taxes'],
        ],
    },
    {
        title: 'Bank Charges & Fees',
        fields: [
            ['letter_of_guarantee_commission_fees_code', 'Letter Of Guarantee Commission Fees'],
            ['letter_of_guarantee_issuance_fees_code', 'Letter Of Guarantee Issuance Fees'],
            ['letter_of_credit_commission_fees_code', 'Letter Of Credit Commission Fees'],
            ['letter_of_credit_other_fees_code', 'Letter Of Credit Other Fees'],
        ],
    },
    {
        title: 'Bank Facilities Interest Expense',
        fields: [
            ['fully_secured_overdraft_interest_expense_code', 'Fully Secured Overdraft Interest Expense'],
            ['clean_overdraft_interest_expense_code', 'Clean Overdraft Interest Expense'],
            ['overdraft_against_commercial_paper_interest_expense_code', 'Overdraft Against Commercial Paper Interest Expense'],
            ['overdraft_against_contract_assignment_interest_expense_code', 'Overdraft Against Contract Assignment Interest Expense'],
            ['medium_term_loan_interest_expense_code', 'Medium Term Loan Interest Expense'],
        ],
    },
];

const form = reactive(
    Object.fromEntries(
        sections.flatMap(s => s.fields).map(([name]) => [name, props.model?.[name] ?? ''])
    )
);

/* ── Interest Revenues Accounts repeater — one GL code per bank
   (or "All"), matching the original exactly. ─────────────────────── */
let revenueIdCounter = 0;
function newRevenueRow(financialInstitutionId = 'all', odooCode = '') {
    revenueIdCounter += 1;
    return { key: revenueIdCounter, financial_institution_id: financialInstitutionId, odoo_code: odooCode };
}
const revenues = ref(
    props.interestRevenueAccounts.length
        ? props.interestRevenueAccounts.map(a => newRevenueRow(a.financial_institution_id ?? 'all', a.odoo_code))
        : [newRevenueRow()]
);
function addRevenueRow() {
    revenues.value.push(newRevenueRow());
}
function removeRevenueRow(index) {
    if (revenues.value.length <= 1) return;
    if (!confirm('Are you sure you want to delete this element?')) return;
    revenues.value.splice(index, 1);
}

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        ...form,
        revenues: revenues.value.map(r => ({
            bank: r.financial_institution_id,
            odoo_code: r.odoo_code,
        })),
    };
    router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Other Integration Settings</h1>
            <p class="text-sm cvr-text-muted mb-6">Please insert Odoo Chart Of Account Number for each field below.</p>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <div v-for="section in sections" :key="section.title" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ section.title }}</h2>
                    <div class="cvr-form-grid-4">
                        <div v-for="[name, label] in section.fields" :key="name">
                            <label class="cvr-form-label">{{ label }}</label>
                            <input v-model="form[name]" type="text" :placeholder="label" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor(name)" class="text-xs mt-1 cvr-num-red">{{ errorFor(name) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Interest Revenues Accounts -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Interest Revenues Accounts</h2>
                    <div v-for="(row, index) in revenues" :key="row.key" class="flex items-end gap-3 mb-3">
                        <div class="w-56">
                            <label class="cvr-form-label">Bank *</label>
                            <select v-model="row.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="all">All</option>
                                <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div class="w-56">
                            <label class="cvr-form-label">Chart Of Account Number *</label>
                            <input v-model="row.odoo_code" type="text" placeholder="Chart Of Account Number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <button
                            v-if="revenues.length > 1"
                            type="button"
                            @click="removeRevenueRow(index)"
                            class="cvr-btn-danger px-3 py-2 rounded border text-xs"
                        >
                            Delete
                        </button>
                    </div>
                    <button type="button" @click="addRevenueRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        + Repeat
                    </button>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
