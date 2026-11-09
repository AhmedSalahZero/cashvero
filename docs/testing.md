# Testing

The test suite runs on its **own databases, built from the project files, with data each test
makes itself**. No test reads, needs or changes your real database.

## Run it

```bash
php artisan test        # PHP: unit + feature tests
npm test                # frontend: the screens, composables and shared answer sheets (Vitest)
```

The first `php artisan test` after a new migration rebuilds the test databases (about a minute);
every run after that starts in about a second.

One file or one test:

```bash
php artisan test tests/Feature/Reports/BankChargesAnalysisReportTest.php
php artisan test --filter=test_a_bank_of_another_company_is_refused
```

## How it is kept apart from real data

| Wall | What it does |
|---|---|
| **Separate databases** | `cashvero_test` (every table, trigger and procedure, plus the fixed reference lists), `cashvero_test_source` (same tables, empty — for the company-import tests) and `cashvero_test_scratch` (for the few tests that build their own tables). Built by `php artisan cashvero:prepare-test-db` from `database/migrations`, `app/Triggers/Cashvero` and `database/seeders/Testing` — **nothing is copied from another database**. |
| **A MySQL account that cannot see anything else** | Tests connect as `cashvero_tester`, which may read and write the three test databases and nothing more. A test that names `cashvero.companies` is refused by MySQL itself. It cannot create or drop tables in `cashvero_test` either. |
| **A rolled-back transaction around every test** | `tests/TestCase.php` wraps each test in a transaction that is rolled back, so no test sees what another one wrote and the order does not matter. If a run ever leaves a company or a user behind, the next run rebuilds the database clean. |
| **No outside calls** | Odoo is reached only through `App\Services\Api\OdooClientFactory`; tests replace it with `Tests\Fakes\FakeOdooClientFactory` (`$this->odoo()`), so no test can reach a real Odoo. Mail, queues and broadcasting are in-memory. |
| **A guard test** | `tests/Feature/Isolation/TestIsolationTest.php` checks all of the above, and fails if any test file points at a real database, a real company or record number, or a file outside the project. |

`cashvero:prepare-test-db` uses the MySQL account in your `.env` (it has to create databases and
triggers) and refuses to run in production or against a MySQL server that is not on this
machine. It creates `cashvero_tester` the first time.

## Writing a test

Every test gets these from `TestCase` (`tests/Concerns/CreatesCompanies.php`):

```php
$company = $this->makeCompany();                          // subscribed to CashVero, EGP
$other   = $this->makeCompany();                          // "another company" for isolation tests
$user    = $this->makeUser(['report_bank_statement.view'], $company);
$this->signInWith(['cash_expense.view'], $company);
$admin   = $this->makeSuperAdmin($company);
```

More helpers, used with `use \Tests\Concerns\...;`:

| Concern | Gives |
|---|---|
| `MakesBanks` | `bank()`, `currentAccount()`, `rate()`, `accountTypeId()` |
| `MakesFacilities` | `cleanOverdraft()`, `overdraftAgainstAssignmentOfContract()`, `mediumTermLoan()`, `loanSchedule()` |
| `MakesInvoices` | `customer()`, `supplier()`, `contract()`, `invoice()`, `supplierInvoice()`, `received()`, `payment()`, `settle()`, `deduct()` |
| `MakesOdooSettings` | a complete `odoo_settings` row with fake account ids |

Factories live in `database/factories` (use `XFactory::new()`). Rows are saved through the models
where possible, so the MySQL triggers work out balances, statuses and running totals exactly as
they do for real.

Rules of thumb:

- Never look up a record that "should already be there" — make it.
- Fix "today" with `Carbon::setTestNow('2026-09-17 10:00:00')` when a result depends on the date.
- Hand-worked expectations go in `tests/Fixtures/*.json` (read with `Tests\Fixtures\Fixtures::load()`):
  work the numbers out by hand from the business rule, never by running the code. Sheets that the
  screen also follows are read by the Vitest specs too, so both sides stay in step.

## Kinds of tests

| Folder / file | What it checks |
|---|---|
| `tests/Unit/*SheetTest.php` | Pure rules against hand-worked sheets (loan split, exchange rates, number formats, dates) plus "always true" sweeps |
| `tests/Unit/StylesheetAndTranslationsTest.php` | Theme tokens, `!important`, duplicate/empty translations, placeholders in both languages |
| `tests/Feature/Permissions/*Sheet*` | What each role may do and which sidebar entries it sees |
| `tests/Feature/Security/*` | Access control, cross-company access, input handling, configuration, source scans |
| `tests/Feature/Smoke/EveryPageOpensTest.php` | Every sidebar page opens for a new, empty company; language and direction follow the address |
| `tests/Feature/Wiring/RoutesAndWiringTest.php` | Every route reaches a real controller method; every sidebar link and icon exists |
| `tests/Feature/Schema/DatabaseStructureTest.php` | Every model has its table, company tables are indexed, the reference data seeds twice cleanly |
| `tests/Feature/Exports/ExcelExportSafetyTest.php` | Exports are real workbooks with real totals; typed text never becomes a formula |
| `tests/Feature/Console/*` | Destructive commands ask first; deleting a company removes all its rows and no one else's; checks change nothing |
| `tests/js/*.spec.js` | Screens (what they send), components, composables, and source scans (every page and import exists) |

## Checking the real data

The tests prove the rules on fake data. To check the data you actually have against the same
rules, run (read-only, against the database in `.env`):

```bash
php artisan cashvero:audit-data
php artisan cashvero:audit-data --company=148
```
