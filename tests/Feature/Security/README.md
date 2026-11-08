# CashVero security test suite

Automated "mini penetration test". Every test is an attack or a check an
auditor would perform. **A RED (failing) test is a finding** — it tells you
what to fix and why. GREEN means that attack was blocked.

## How to run

```bash
php artisan test --group=security                  # everything
php artisan test --group=security --filter=SEC-14  # one finding (matches by name/message)
php artisan test tests/Feature/Security/RouteSecurityTest.php   # one file
```

Tests that use the database (Authentication, AccessControl, InputHandling)
use the development database (`SMOKE_DB`, default `cashvero`), create only
throw-away users (`sec-test-…@example.test`) and delete them afterwards.
They **skip** (not fail) if the database is unreachable.
Set `SECURITY_HEADERS_AT_PROXY=true` if your web server (nginx/IIS) sets the
browser security headers instead of the application.

## What is covered

| File | Needs DB | Covers |
|---|---|---|
| SourceCodeSecurityTest | no | dangerous functions, leftover debug output, SQL built from input, mass assignment, unescaped HTML, committed credentials, Odoo password storage, uploads, delete/export allow-lists |
| ConfigurationSecurityTest | no | debug mode, cookies, session lifetime, hashing, CSRF, dev packages, .gitignore, security headers |
| RouteSecurityTest | app boot | every route needs login, permission rules, GET that changes data, CSRF group, rate limits, open registration |
| MiddlewareSecurityTest | app boot | company-membership gate, record-belongs-to-company gate, permission gate (with documented limits) |
| AuthenticationSecurityTest | yes | login lock-out, uniform errors, reset-password leaks, logout, self-registration |
| AccessControlSecurityTest | yes | cross-company and cross-rank editing/deleting, invoice leak, user-list leak |
| InputHandlingSecurityTest | yes | SQL-injection strings, time-delay injection, disguised uploads, stored XSS |
| AllRoutesCrossCompanyTest | yes (cashvero_test only) | EVERY route with a company in its address, attacked by a company-A user holding all permissions: company B's number (SEC-30), company B's records through bound models (SEC-31), generic model/id screens (SEC-32); SEC-33:  a user with no permissions against every route the permission map protects. |
| DependencyAuditTest | no (needs internet + composer) | known security advisories in installed packages (`composer audit`, SEC-40) |
| ForeignRecordsInFormsSecurityTest | yes (cashvero_test only) | SEC-34a: every `exists:` / `Rule::exists` rule on a company table must be limited to the company; SEC-34b: write forms sent with company B's record numbers and `company_id`=B, nothing may be stored for B |
| PermissionGranularitySecurityTest | yes (cashvero_test only) | SEC-35a: route map only asks for real permissions; SEC-35b: no view permission shares a name with a change permission; SEC-35c: a view-only user is refused on every write screen |
| CompanyAdministrationSecurityTest | yes (cashvero_test only) | SEC-36: a user who is not a Super Admin cannot view/edit/delete/remove a company that is not theirs (throw-away company, rolled back) |
| PlainRecordNumberSecurityTest | yes (cashvero_test only) | SEC-37a: screens with a bare `{id}` (model read from the controller source) asked for company B's record; SEC-37b: screens that load a User asked for a user of another company |
| SpreadsheetFormulaSecurityTest | app boot | SEC-38: text typed by a user (`=HYPERLINK(...)`, `=cmd\|...`) must stay plain text in Excel exports |
| OdooAddressSecurityTest | yes (cashvero_test only) | SEC-39: the company form must refuse an Odoo address pointing at localhost / private networks / non-web protocols, and plain http (SSRF) |

## Findings the suite is expected to report on the current code

| ID | Severity | Summary |
|---|---|---|
| SEC-01 | HIGH | Credentials committed in a seeder file — rotate them |
| SEC-02/03 | HIGH | Odoo password stored readable and sent to the browser on the profile page |
| SEC-04 | MEDIUM | Functions that can execute commands/code present |
| SEC-05/05b | MEDIUM | Models written with the whole request (e.g. `max_users`) |
| SEC-06/06b/06c | MEDIUM | Weak/unhashed password paths for users |
| SEC-07 | MEDIUM | Some uploads not validated |
| SEC-08 | HIGH | `delete.model` deletes any model/record by name and id |
| SEC-09 | MEDIUM | Export field picker takes a table name from the request |
| SEC-10/11/12 | varies | Routes changing data by GET, unnamed or unmapped routes, wrong company position in URL |
| SEC-13 | HIGH | Adjust-due-date page loads invoices by bare id (cross-company) |
| SEC-14 | HIGH | User update lacks target check (Super Admin / other company) |
| SEC-15 | HIGH | `remove.user` deletes any id |
| SEC-16 | MEDIUM | No browser security headers |
| SEC-17/18/19 | LOW–MEDIUM | Missing rate limits, reset e-mail enumeration, open registration |
| SEC-20 | MEDIUM | Users list shows other companies when no company is in the URL |

Which of these go red is decided by the code when you run it; the list is
what a read-through of the project predicts.

## Honest limits

* These tests prove what they check — they are not a substitute for an
  external penetration test (network, server, TLS, cloud settings, social
  engineering).
* Tests marked "app boot" or "yes" were written against the real code but
  could not be executed in the authoring sandbox (no Laravel runtime / DB).
  The first `php artisan test --group=security` run may need small
  adjustments (e.g. a route name or role that differs in your database).
  Lint, the static scanner tests and the configuration tests were executed.

## Production checklist (not testable from code)

- `APP_DEBUG=false`, `APP_ENV=production`
- `SESSION_SECURE_COOKIE=true`, HTTPS everywhere, HSTS
- `composer install --no-dev --optimize-autoloader`, run `composer audit`
- Web server must not execute PHP under `storage/` or `public/storage`
- If behind a load balancer: configure `TrustProxies` with its exact addresses
- Database user with minimum rights; backups encrypted
