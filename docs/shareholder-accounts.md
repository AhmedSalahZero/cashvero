# Shareholder / Owner Personal Accounts — Locked Spec (v1)

Source roadmap: `CashVero_Shareholder_Accounts_Roadmap.md` (Aug 13, 2026) — Option B
(ownership flag on existing records, same company tenant, no new subscription).

This file records the decisions taken on **2026-08-19** that closed the roadmap's
Section 6 "Open Decisions" plus the extra scoping questions raised during code review.
Where this file and the roadmap disagree, **this file wins**.

---

## 1. Data model — the flag

Two columns on each flagged table:

| Column | Type |
|---|---|
| `is_shareholder_account` | `boolean` default `0` |
| `shareholder_partner_id` | `unsignedBigInteger` nullable → `partners.id` (where `is_shareholder = 1`) |

Applied to exactly 4 tables (roadmap §3):

- `financial_institution_accounts` (Current Account)
- `time_of_deposits` (TD)
- `certificates_of_deposits` (CD)
- `medium_term_loans` (MTL)

One shareholder per record. `shareholder_partner_id` is required when
`is_shareholder_account = 1`, and forced to `null` otherwise.

**Untouched:** all ODA types, LG, LC, Leasing, Factoring, `companies`,
`company_systems`, Cash in Safe / branches. `FullySecuredOverdraft` inherits the flag
for free through its existing `cd_or_td_account_id` / `cd_or_td_account_type_id`
collateral pointer — verification step only, no code change.

---

## 2. Decisions taken (closing roadmap §6)

### D1 — MTL in company reporting  (roadmap Open Decision #1)
**The filter means ownership and nothing else, applied identically to every
instrument.** A shareholder-flagged MTL is filtered exactly like that owner's current
account or their TD: it appears under *All accounts* and *Shareholders accounts*, and
not under *Company accounts*. There is no MTL special case anywhere.

So each filter answers one clean question:

| Filter | Shows |
|---|---|
| Company accounts (default) | everything the **company** owns — current, CD, TD, overdrafts, leasing, MTL, cash in safe |
| All accounts | company **+** shareholder |
| Shareholders accounts | shareholder-owned only |

Instruments a bank does not issue to an individual (all overdraft types, leasing) and
the cash safes are company-only by nature, so they are simply absent from the
Shareholders view rather than shown as if an owner held them.

**Reversed on 2026-08-19, deliberately.** The first reading of the owner's answer
("does the personal 500,000 enter the Company Forecast? yes") was to force an
owner-flagged loan into every company figure. Reviewing it surfaced the flaw: when the
owner injects the loan proceeds into the company, the company's obligation is **to the
owner**, not to the bank — and that obligation already has a home, the
`shareholder_statements` ledger. Forcing the bank loan into the company view as well
would represent the same 500,000 twice.

**Known consequence, accepted:** on the Company view the injected cash shows with no
matching obligation unless someone records the "due to shareholder" entry by hand
(D4 leaves that ledger manual). Worth watching during review.

Consequence for the code: the shared cash-flow engine needs **no MTL special-casing at
all** — it applies the one ownership filter and stops.

### D2 — Dashboard filter default  (roadmap Open Decision #2)
Default = **Company accounts**. Filter has three states:

    All accounts | Company accounts (default) | Shareholders accounts

When **Shareholders accounts** is picked, a **second select** appears:
`All shareholders | <specific shareholder>`.

### D3 — Per-owner filtering  (roadmap Open Decision #3)
Required **from day one** (not deferred to Phase 4) — see the second select in D2.

### D4 — Internal transfer vs. shareholder-loan ledger  (roadmap Open Decision #4)
**No automatic ledger entry in v1.** An Internal Money Transfer between a company
account and a shareholder account is a **pure cash movement** between two accounts of
the same company. The existing `shareholder_statements` ledger stays exactly as it is
and is still driven manually from Money Payment / Money Received. This avoids
double-counting against the existing manual flows and honours roadmap §8.

### D5 — Where shareholder accounts are selectable
**Everywhere an account can be picked** — full parity with company accounts:
Money Received, Money Payment, Cash Expense, Buy/Sell Currency, Internal Money
Transfer, Bank Statement. Rationale: the finance department operates both sets of
accounts, and the company legitimately spends owner money (see D1).

Follows from D4: paying a company supplier invoice out of a shareholder account
produces **no** automatic due-from-shareholder entry — the user books that by hand if
they want it.

### D6 — Permissions
**One new permission in v1**, added to `App\Support\Permissions\PermissionRegistry`.
Without it, the user sees neither shareholder-flagged records nor the dashboard
filter. The roadmap's §5 record-level (per-account) permission model is **deferred**.

### D7 — Account-number dropdown label
Wherever an account number is listed for selection and that account is
shareholder-flagged, the **label** shows the shareholder name beside the number:

    12345 — Ahmed

The **stored value is unchanged** — still the raw `account_number` string. This matters
because most tables persist `account_number` as a string (plus
`financial_institution_id` + `account_type_id`), not a foreign key; changing the value
would break every existing row. Display-only change, applied in the
`getAllAccountNumberForCurrency()` family.

### D8 — Forecast dashboard & cash-flow reports: DEFERRED
Not in v1. The Forecast dashboard is **not independent** — it calls
`CashFlowReportController::result()` and `ContractCashFlowReportController::result()`
internally, so adding a filter there means editing the shared engine
(`CashFlowPeriodBatchLoader`, `CashFlowCompanyPeriodBatchLoader`,
`ConsolidatedCashFlowService`) — which v1 explicitly does not touch. Forecast keeps
computing over all accounts as it does today. Same for Company / Contract /
Consolidated Cash Flow reports.

### D9 — LG & LC dashboard
**Never modified.** Not in v1, not in any later phase of this feature.

---

## 3. v1 scope

**In:**
1. Migrations + model support for the flag on the 4 tables.
2. Create/edit forms: `FinancialInstitutions/AddAccount.vue` + `EditAccount.vue`,
   `TimeOfDeposits/*`, `CertificatesOfDeposits/*`, `MediumTermLoan/*` — an
   "Account Owner: Company / Shareholder" control that reveals a shareholder select.
3. Shareholder name in every account-number dropdown label (D7).
4. Cash Status dashboard: the 3-way filter + the shareholder sub-select (D2/D3),
   defaulting to Company accounts.
5. Bank Statement + the other account-picking screens accept shareholder accounts (D5).
6. Internal Money Transfer both directions (D4 — cash only).
7. The new permission (D6).
8. PHPUnit feature tests under `tests/Feature/`.

**Out:** Forecast dashboard, all cash-flow reports, LG&LC dashboard, record-level
permissions, shareholder safes, any ODA/LG/LC/Leasing/Factoring change.

---

## 4. Test coverage required (point 7)

`tests/Feature/` cases:
- Creating/editing an account as shareholder-owned persists both columns; unflagging
  nulls `shareholder_partner_id`.
- Validation: `shareholder_partner_id` required when flagged, and must be a partner of
  the same company with `is_shareholder = 1`.
- Cash Status with no filter = Company accounts only (default, D2).
- Cash Status "All accounts" = company + shareholder totals.
- Cash Status "Shareholders accounts" + specific shareholder = that owner only.
- A shareholder-flagged MTL follows ownership like every other instrument:
  excluded from **Company accounts**, included under **All accounts** and
  **Shareholders accounts** (D1).
- Internal transfer company → shareholder and shareholder → company both move cash and
  write **no** `shareholder_statements` row (D4).
- A user without the new permission sees neither the filter nor the flagged records (D6).
- Account dropdown label carries the shareholder name while the value stays the raw
  account number (D7).

---

## 5. What was built (2026-08-19)

### Backend
- `2026_08_19_120000_add_shareholder_ownership_to_accounts` — the two columns on
  the 4 tables (idempotent; skips a table/column that already has them).
- `App\Traits\Models\HasShareholderOwnership` — relation, `isShareholderAccount()`,
  `getShareholderName()`, the `onlyCompanyOwned` / `onlyShareholderOwned` scopes,
  and the dropdown-label decorator. Used by all 4 models.
- `App\Support\ShareholderAccounts\AccountOwnerFilter` — the All/Company/Shareholders
  selection. Defaults to Company; applies to both Eloquent and raw query builders.
- `App\Support\ShareholderAccounts\ShareholderAccountAccess` — the permission gate,
  the shareholder list, the form props, and ownership normalisation.
- `App\Http\Requests\Concerns\ValidatesShareholderOwnership` — shared rules, wired into
  StoreCurrentAccount / UpdateCurrentAccount / StoreTimeOfDeposit /
  StoreCertificateOfDeposit / StoreMediumTermLoan (the Update* TD/CD requests inherit).
- Cash Status: `CashDashboardService` reads the filter and passes it to
  `LatestStatementQuery::latestCurrentAccountBalances()` and both
  `DepositCashDashboardHelper` queries. Cash in safe and all overdraft facilities are
  company-only instruments, so they drop out of the Shareholders view rather than being
  shown as if they belonged to an owner. Medium Term Loans are filtered by ownership
  like everything else (D1).
- `shareholder_account.view` in `PermissionRegistry`, deliberately with **no legacy
  alias** (nobody holds it until granted) and deliberately **outside the `manager` and
  `user` role templates** via `OWNER_PRIVATE_MODULES`, so it is granted per person.
  `tests/Feature/Permissions/PermissionRegistryTest` carries a documented exemption for
  the missing alias.

### Frontend
- `Components/ShareholderOwnershipFields.vue` — the shared "Account Owner" control,
  used by AddAccount, EditAccount, TimeOfDeposits/Form, CertificatesOfDeposits/Form and
  MediumTermLoan/Form. Renders nothing without the permission.
- `Dashboard/CashStatus.vue` — the 3-way filter plus the shareholder sub-select, and a
  single `applyFilters()` so changing the date never drops the owner selection.
- Owner column on `BankAccounts/Index.vue` and
  `FinancialInstitutionFacilities/BankAccounts.vue`.

### The account-number dropdown refactor (required by D7)
Every account-number dropdown used to do `Object.values(response.data)` and use one
string as both the option's value and its text — safe only while the two were
identical. They no longer are, so a shareholder account would have written
`"12345 — Ahmed"` into `account_number` columns.

`resources/js/composables/useAccountNumberOptions.js` now converts the response into
`{value, label}` pairs, and all 15 consuming pages were converted with it: BuyOrSell,
CashExpense, InternalMoneyTransfer, Factoring (+ With/WithoutRecourse), both
LcSettlement pages, MoneyPayment (Form + DownPayment), MoneyReceived (Form + DownPayment
+ Index), Statements/BankStatement, and both settlement pages
(LoanScheduleSettlement / LeasingContractSettlement, which are fed server-side and now
receive `{value,label}` from their controllers).

### Tests
`tests/Feature/ShareholderAccounts/` — 20 tests, all passing:
`AccountOwnerFilterTest` (6, no database) and `ShareholderAccountsTest` (14, against the
development database inside a rolled-back transaction, skipped when unreachable).
Two of them pin D1 from both sides: an owner's loan is absent from the Company view and
present under All/Shareholders, and a company loan is the mirror image.

Two suite failures are pre-existing and unrelated: `CompanyImportServiceTest`
(account_types/banks diverge between the source and target databases) and
`BalanceSheetTest` (missing `NonBankingService\Study` class).
