# CashVero — Cross-Functional Quality Audit

**Application:** CashVero (`cashvero.evoqas.com`) — Laravel 12.52 / PHP 8.4 / Inertia + Vue 3 / MySQL
**Audit date:** 16 September 2026
**Fixed during the audit:** F-1 (permission bypass on 14 report routes), F-10 (partner delete guard)
**Scope:** Security, financial-data integrity, correctness, performance, maintainability, test quality

---

## 0. Method, and what "every file" honestly means

This codebase is **1,161 source files / ~230,000 lines / 10.2 MB**:

| Type | Files | Lines |
|---|---:|---:|
| PHP | 979 | 179,376 |
| Vue | 179 | 48,832 |
| Blade | 3 | 365 |
| JS | 9 | 382 |
| CSS | 1 | 1,166 |

**Every one of those files was machine-read in full** by purpose-written analyzers (the reader confirmed 10,238,340 bytes ingested). Those analyzers parsed each file for specific defect classes — raw SQL taint, mass assignment, XSS sinks, missing transactions, money typing, authorization coverage, translation coverage, loop-nested queries.

**I did not read all 230,000 lines by eye, and this report does not pretend otherwise.** Manual deep reading was applied to the files the analyzers flagged, plus the highest-risk financial paths. Every finding below carries a file, a line, and the code — none is a generic checklist item. Where a machine signal was too noisy to confirm, it is labelled an *indicator*, not a finding.

---

## 1. Executive summary

CashVero is, for its size, a **well-engineered application**. Authorization is centralized and near-complete, there is no SQL injection, no XSS, no hardcoded secrets, and the test suite is unusually strong for a business application (94 test files, 1,241 tests, with tests that assert *intent* rather than implementation).

Three issues are serious enough to act on before the next release. The rest are hygiene.

| # | Finding | Severity |
|---|---|---|
| F-1 | 14 report routes bypass permission checks (fail-open policy) | **High** |
| F-2 | Deduction update is not transactional — can silently reduce a receivable | **High** |
| F-3 | 108 money columns stored as `double` / `varchar` instead of `DECIMAL` | **High** |
| F-4 | 22 further multi-write actions without a transaction | Medium |
| F-5 | 436 foreign-key columns without an index | Medium |
| F-6 | Verified N+1 in the balances screen (nested-loop `Partner::find`) | Medium |
| F-7 | Mass assignment from `$request->all()` | Low |
| F-8 | 13 untranslated UI keys | Low |
| F-9 | `dump()` left in a test; 12 error-suppression operators | Low |
| F-10 | Deleting a partner strands its internal-settlement rows | **High** |

### What is genuinely good

- **No SQL injection.** 100 raw-SQL sites were extracted and every one traced; the only two touching request-derived variables pass them as bound parameters. The raw fragments are static strings.
- **No XSS.** All 19 `v-html` uses render Laravel's own pagination labels. The single unescaped Blade (`{!! $content !!}`) is a server-generated exception email.
- **No hardcoded secrets.** Zero across all 1,161 files.
- **Authorization is centralized**, enforced by global middleware, with a build-breaking coverage test — an architecture most applications this size do not have.
- **No duplicated files**, and a deliberate, documented comment culture that explains *why*, including past bugs.

---

## 2. High-severity findings

### F-1 — Report routes bypass permission checks

**Where:** `config/permissions.php:34`, `app/Http/Middleware/EnforcePermission.php:80-90`, `app/Support/Permissions/RoutePermissionMap.php`

`EnforcePermission` runs globally (`app/Http/Kernel.php:44`) and aborts 403 for any route whose name maps to a permission the user lacks. For an **unmapped** route it calls `handleUnmapped()`, which logs and then **lets the request through**:

```php
if (config('permissions.unmapped', 'allow') === 'deny' && config('permissions.enforce', true)) {
    abort(403, ...);
}
return $next($request);          // fail-open
```

Runtime config confirms `enforce=true`, `unmapped='allow'`.

14 routes were unmapped — every print/export route added in the last two development sessions. Any authenticated CashVero user could print or export Safe, Bank, Partners, Factoring, LG/LC statements and the full invoice report **without holding the corresponding `.view` / `.export` permission**, simply by requesting the URL.

The project's own guard caught this: `tests/Feature/Permissions/RouteCoverageTest.php` was failing on exactly these 14 routes.

**Status: FIXED during this audit.** All 14 mapped to the same permission as their sibling export/view route; all 15 referenced permission keys verified to exist in `PermissionRegistry`; `tests/Feature/Permissions/` now passes (43 tests).

**Still recommended:** flip `PERMISSIONS_UNMAPPED=deny`. The config comments say this was always the intent once the log was quiet. Fail-open on a financial application means the *next* forgotten route is also a silent leak, and the log is not a control.

---

### F-2 — Deduction update is not transactional

**Where:** `app/Http/Controllers/InvoiceDeductionsController.php:39-69`

```php
$invoice->net_balance = $currentBalance - $totalDeductions;   // ① dirtied in memory
$invoice->deductions()->detach();                             // ② all deductions deleted
$invoice->update(['total_deductions' => 0]);                  // ③ ALSO persists net_balance
foreach ($request->get('deductions', []) as $deductionArr) {
    ... Deduction::calculateAmountInMainCurrency(...)          // ④ FX lookup — can fail
    InvoiceDeduction::create($deductionArr);
}
$invoice->update(['total_deductions' => $totalDeductions]);   // ⑤
```

No transaction wraps this. Note that step ③ is not innocent: Eloquent's `update()` persists *all* dirty attributes, so the **reduced `net_balance` is committed at step ③**, before any replacement deduction exists.

**Failure scenario:** a user edits deductions on an invoice with a net balance of 12.85 and saved deductions of 2,900. Step ③ commits the new lower balance and wipes the deduction rows. Step ④ throws — an exchange rate missing for the deduction's date, a deadlock, a timeout, a validation error inside `calculateAmountInMainCurrency`. The request 500s. The invoice is now left with a **reduced receivable and no deductions backing it**. Nothing in the UI shows the money is gone; the audit trail shows a deletion and no creation.

**Fix:** wrap lines 52–67 in `DB::transaction()`. Roughly four lines.

---

### F-3 — Money stored as `double` / `varchar`

**108 money columns** across the schema are not stored as exact decimals:

| Storage | Columns | Consequence |
|---|---:|---|
| `varchar` | 65 | Money as text — arithmetic and comparison depend on implicit casting; ordering is lexical |
| `double` | 41 | Binary floating point — error accumulates across repeated add/subtract |
| `float` | 2 | As above, worse precision |

The core tables are affected:

```
customer_invoices.net_balance                       varchar
customer_invoices.net_invoice_amount                varchar
customer_invoices.vat_amount                        varchar
customer_invoices.net_balance_in_main_currency      double
supplier_invoices.invoice_amount                    varchar
settlements.settlement_amount                       varchar
payment_settlements.settlement_amount               varchar
money_received.total_withhold_amount                double
```

**This is not theoretical — it has already produced a production defect.** `net_balance` is computed by repeated addition and subtraction, and real rows carry values such as `-0.00000000011641532182693481` and `110579.89000000013`. Because a settled invoice was being detected with `net_balance > 0`, a fully-settled invoice read as still owing money. The current mitigation is defensive rounding in `IsInvoice::getNetBalance()` — correct as a patch, but it treats the symptom.

**Recommendation:** migrate money columns to `DECIMAL(18,4)` (or `DECIMAL(18,2)` where the currency has two places). This is a substantial migration across 236 existing migrations and must be staged: add new columns, backfill with rounding, switch reads, drop old. Until then, the rounding guard in `getNetBalance()` must not be removed, and any new money comparison must round first.

### F-10 — Deleting a partner strands its internal-settlement rows

**Where:** `app/Support/Deletion/ReferencedRecordGuard.php:73`

`ReferencedRecordGuard` is the application's referential-integrity net: before a record is deleted it refuses if anything still points at it. The `partners` entry lists 26 dependent tables — but `internal_settlements.partner_id`, added with the Internal Settlements feature, was never registered.

**Failure scenario:** a partner who is both customer and supplier has an internal settlement offsetting the two sides. Someone deletes the partner. The guard sees no obstacle and allows it. The `internal_settlements` row survives, pointing at a partner id that no longer exists — and the balances screen reads settlements by partner and calls `->getName()` on the result.

The project's own guard test caught this (`DeletionDependentsTest::test_every_referencing_column_in_the_schema_is_accounted_for`), and had been failing.

**Status: FIXED during this audit.** Registered as a dependent with the label *Internal Settlements*; `tests/Feature/Deletion/` now passes (44 tests).

This is the second finding in this report that the test suite had already caught and that went unactioned because the suite runs red (see §5).

---

## 3. Medium-severity findings

### F-4 — 22 further multi-write actions without a transaction

Same class as F-2. Each writes three or more times with no transaction, so a mid-way failure leaves related records disagreeing:

| File | Line | Action | Writes |
|---|---:|---|---:|
| `OpeningBalancesController.php` | 454 | `update()` | 20 |
| `OpeningBalancesController.php` | 303 | `store()` | 10 |
| `CustomerOpeningBalancesController.php` | 194 | `update()` | 9 |
| `SupplierOpeningBalancesController.php` | 196 | `update()` | 9 |
| `SalesGatheringTestController.php` | 1191 | `update()` | 6 |
| `OdooSettingController.php` | 127 | `store()` | 5 |

…plus 16 more with 3–4 writes (`PartnersController`, `LetterOfCreditFacilityController`, `LetterOfGuaranteeFacilityController`, `AdjustedDueDateHistoriesController`, `DownPaymentContractsController`, `TimeOfDepositRenewalDateController`, and others).

Opening balances are the highest risk: a partially-written opening position is the starting point every later statement is built on.

---

### F-5 — 436 foreign-key columns without an index

Columns ending in `_id` with no index — every join and every `where(... _id, ...)` on them is a full scan. Examples: `active_jobs.company_id`, `branch.company_id`, `branch.journal_id`.

`company_id` appearing in this list is the most costly case: it is the tenancy filter present in nearly every query in the application.

---

### F-6 — Verified N+1 in the balances screen

**Where:** `app/Http/Controllers/BalancesController.php:708`

```php
foreach ($hasInvoiceBalances ? $invoicesBalances : [null] as $invoiceBalanceStdClass) {
    foreach ($downPayments as $downPaymentStdClass) {
        $invoicePartnerName = optional(Partner::find($invoicePartnerId))->getName();
```

A `Partner::find()` inside a **nested** loop: one query per invoice-balance × down-payment pair. The partner names are already available and should be pre-loaded into a keyed map — the same file already does this at line 787 (`$partnerNames[$partnerId] ?? ...`), so the fix is to use that map here too.

A broader scan flagged **264 query-inside-loop sites** across the controllers. That number is an *indicator*, not a confirmed count — the detector's loop tracking is crude and many will be false positives. It is offered as a prioritized review list, not a defect tally.

---

## 4. Low-severity findings

### F-7 — Mass assignment from the request
`app/Http/Controllers/ExportTable.php:234`
```php
CustomizedFieldsExportation::create($request->all());
```
Every other write path in the application uses explicit `$request->only([...])` allow-lists. This one does not. Low impact today (the model is a UI preference), but it is the one place a future column becomes remotely settable.

### F-8 — 13 untranslated UI keys
1,776 of 1,789 `$t()` keys are translated — **99.3 %**, which is excellent. The 13 gaps are mostly runtime-composed keys (`account_type.cash-in-bank`, `account_number.payable_cheque`) that will render as raw identifiers under Arabic, plus `branch-manager`, `subsidiary-companies`, `leasing_company_id`, `leasing_contract_id`, and a stray `"s"`.

### F-9 — Debug and suppression leftovers
- `tests/Feature/Deposits/DepositSettlementAccountTest.php:310` — a live `dump()`.
- 12 `@` error-suppression operators, which hide the failure they suppress.

---

## 5. Test quality

| Metric | Value |
|---|---|
| Test files | 94 |
| Tests | 1,248 |
| Assertions | 5,752 |
| Errors | 35 |
| Failures | 35 |
| Skipped | 82 |

The 70 errors/failures break down into three kinds, and the distinction matters:

| Kind | Example | Real defect? |
|---|---|---|
| **Environmental** | `SQLSTATE[HY000] [1049] Unknown database 'cash-vero'` | No — the test points at a database name absent on this machine |
| **Data-dependent** | `Undefined array key "2026-01-01"`, `Attempt to read property "debit" on null` | No — assumes dev-database state that has since changed |
| **Genuine defects** | `DeletionDependentsTest` (F-10) | **Yes** |

Concentration: `ShareholderAccountsTest` (25), `DepositSettlementAccountTest` (14), `LgContractRequirementTest` (12), `LgRenewalTermsTest` (10) — 61 of 70 in four files, all tied to features under active development.

The suite is a genuine strength. Tests assert behaviour and intent — that a settlement puts both sides back exactly, that a percentage is not computed across two currencies, that a printout covers the whole range rather than one page. Several encode a past bug so it cannot return.

**Two observations:**

1. **A red suite has stopped working as a signal.** This is the single most consequential observation in the audit. **Two of the three High findings in this report — F-1 and F-10 — were already being reported by the project's own tests, by name, and had not been acted on**, because red is the normal state. The tests were right; nobody could hear them. Recommendation: fix the environmental failures (a database name), quarantine the data-dependent ones explicitly, and treat any new red as a stop-the-line event.

2. **Static assertions can pass while the feature is broken.** Observed directly during this engagement: a refactor left five controllers referencing variables that no longer existed in scope. `php -l` passed, and 71 structural tests stayed green, because they inspected source text rather than executing the methods. Recommendation: pair structural tests with at least one execution test per path. (An undefined-variable detector was added and confirmed to catch exactly that regression.)

---

## 6. Prioritized remediation plan

| Priority | Action | Effort |
|---|---|---|
| 1 | Wrap `InvoiceDeductionsController::update` in a transaction (F-2) | ~1 hour |
| 2 | Set `PERMISSIONS_UNMAPPED=deny` (F-1) | minutes + a regression pass |
| 2 | Verify no orphan `internal_settlements` rows already exist in production (F-10) | ~1 hour |
| 3 | Transactions for opening-balance writes (F-4) | ~1 day |
| 4 | Index `company_id` and hot foreign keys (F-5) | ~half a day |
| 5 | Triage the suite to green, or quarantine explicitly (§5) | ~2 days |
| 6 | Fix the verified N+1, review the flagged list (F-6) | ~1 day |
| 7 | Plan the `DECIMAL` migration (F-3) | Project — stage it |
| 8 | F-7, F-8, F-9 hygiene | ~half a day |

---

## 7. Coverage statement

**Machine-read:** 1,161 files / 10,238,340 bytes — 100 % of PHP, Vue, Blade, JS and CSS outside `vendor/` and `node_modules/`.

**Analyses run across the full corpus:** raw-SQL taint tracing · mass assignment · XSS sinks · hardcoded secrets · debug leftovers · error suppression · route-to-permission coverage (634 routes) · in-controller authorization fallback · money column typing (all tables) · multi-write transaction analysis · nested-query detection · translation coverage (1,789 keys) · duplicate-file detection · complexity ranking · foreign-key index coverage.

**Manually read in depth:** `EnforcePermission`, `CashManagementMiddleware`, `RoutePermissionMap`, `config/permissions.php`, `InvoiceDeductionsController`, `BalancesController` (balances assembly), `IsInvoice`, `ContractsController`, `SalesGatheringController`, `SalesGatheringTestController`, `Statements/Print.vue`, `PrintsReport`, plus every file flagged by the analyzers above.

**Not covered by this audit:** runtime penetration testing, load and concurrency testing, dependency CVE scanning, infrastructure and deployment configuration, the Odoo integration's behaviour against a live Odoo instance, and browser-level accessibility testing.
