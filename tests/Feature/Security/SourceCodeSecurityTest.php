<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\StaticSecurityTestCase;

/**
 * SECURITY AUDIT — the source code.
 *
 * This is the automated version of what a penetration tester does in the
 * "code review" phase: read the code looking for the handful of patterns that
 * cause most real-world breaches. It reads files only. No database, no web
 * server, no network — so it is fast and it can never be silently skipped.
 *
 * How to read the results
 * -----------------------
 *   GREEN  = that class of problem was looked for and not found (a control
 *            that works). A green test here is worth keeping green.
 *   RED    = a real finding. The message starts with an id such as
 *            "[SEC-05 · MEDIUM]" followed by where, why, and how to fix.
 *
 * Some checks are "ratchets" (marked as such): they record how many
 * instances exist TODAY and fail only if the number goes UP. That lets the
 * team stop new risk entering the code without having to fix 30 years of
 * history on day one.
 *
 * Run only this file:   php artisan test --filter=SourceCodeSecurityTest
 */
#[Group('security')]
class SourceCodeSecurityTest extends StaticSecurityTestCase
{
    /**
     * `extract()` turns array keys into local variables, which is dangerous when the array
     * comes from a visitor. These two calls read the cached JSON report that the application
     * itself stored in its own database (never request data), so they were reviewed and accepted.
     */
    private const REVIEWED_EXTRACT_CALLS = [
        'app/Http/Controllers/CashFlowReportController.php' => 2,
    ];

    /**
     * RATCHET. Raw SQL built from variables, per file, as of the audit. Every one of these was
     * traced when this test was written: the variable is a table/column name from a class
     * constant, a literal chosen by the code, or an integer — never request text, and user-typed
     * values are passed as bound parameters. If a count goes UP, read the new line and confirm
     * the same is true, then raise the number here.
     */
    private const RAW_SQL_BASELINE = [
        // Reviewed: runs the project's own fixed .sql trigger files, console-only, no user input.
        'app/Console/Commands/PrepareTestDatabaseCommand.php' => 4,
        'app/Console/Commands/RepairStatementBalancesCommand.php' => 2,
        'app/Console/Commands/RunSqlOnProduction.php' => 1,
        'app/Http/Controllers/BalancesController.php' => 3,
        'app/Http/Controllers/ChequesReportController.php' => 3,
        'app/Http/Controllers/CustomerInvoiceDashboardController.php' => 1,
        'app/Http/Controllers/DownPaymentContractsController.php' => 1,
        'app/Http/Controllers/DownPaymentReportController.php' => 3,
        'app/Http/Controllers/LGLCSBanktatementController.php' => 1,
        'app/Http/Controllers/PartnersStatementController.php' => 1,
        'app/Http/Controllers/WithdrawalsSettlementReportController.php' => 1,
        'app/Http/Requests/MarkChequeAsPaidRequest.php' => 1,
        'app/Jobs/SyncOdooPaymentMethodIdsJob.php' => 1,
        'app/Models/CashExpense.php' => 1,
        'app/Models/CleanOverdraft.php' => 1,
        'app/Models/CustomerInvoice.php' => 1,
        'app/Models/FullySecuredOverdraft.php' => 1,
        'app/Models/LetterOfCreditIssuance.php' => 1,
        'app/Models/LetterOfGuaranteeIssuance.php' => 5,
        'app/Models/MoneyPayment.php' => 1,
        'app/Models/OverdraftAgainstAssignmentOfContract.php' => 1,
        'app/Models/OverdraftAgainstCommercialPaper.php' => 1,
        'app/Models/TimeOfDeposit.php' => 1,
        'app/Services/Reports/CashFlowCompanyPeriodBatchLoader.php' => 10,
        'app/Services/Reports/CashFlowContractDetailPeriodBatchLoader.php' => 10,
        'app/Services/Reports/CashFlowPeriodBatchLoader.php' => 3,
        'app/Support/CashDashboard/DepositCashDashboardHelper.php' => 2,
        'app/Support/CashDashboard/OverdraftCashDashboardHelper.php' => 1,
        'app/Support/Serials/SerialBackfiller.php' => 1,
        'app/Support/StatementCascade.php' => 1,
        'app/Traits/HasBankStatement.php' => 1,
        'app/Traits/HasLastStatementAmount.php' => 1,
        'app/Traits/IsBankStatement.php' => 1,
        'app/Traits/Models/HasForecastedProjectCollection.php' => 2,
        'app/Traits/PaginatesStatementQueries.php' => 1,
    ];

    /** RATCHET. Model files that declare `$guarded = []` (every column may be mass-assigned). */
    private const MODELS_WITH_OPEN_GUARD_BASELINE = 19;

    /** The only Blade file allowed to print unescaped HTML: an e-mail sent to developers. */
    private const REVIEWED_UNESCAPED_BLADE = [
        'resources/views/mails/exception-mail.blade.php',
    ];

    /* ──────────────────────────────────────────────────────────────
     * Code execution
     * ────────────────────────────────────────────────────────────── */

    public function test_no_code_turns_text_into_commands_or_code(): void
    {
        $evidence = [];
        $extractSeen = [];

        foreach (['app', 'routes', 'config', 'database'] as $dir) {
            foreach ($this->scan->files($dir, 'php') as $file) {
                $relative = $this->scan->relative($file);

                foreach ($this->scan->dangerousCalls($file) as $hit) {
                    if ($hit['call'] === 'extract' && isset(self::REVIEWED_EXTRACT_CALLS[$relative])) {
                        $extractSeen[$relative] = ($extractSeen[$relative] ?? 0) + 1;

                        if ($extractSeen[$relative] <= self::REVIEWED_EXTRACT_CALLS[$relative]) {
                            continue;
                        }
                    }

                    $evidence[] = "{$relative}:{$hit['line']}  {$hit['call']}()";
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-04', 'LOW',
            'Code that executes text as PHP or as operating-system commands',
            $evidence,
            'If any text a visitor can influence ever reaches eval()/exec()/system(), the attacker runs their own commands on your server — full takeover. Today the eval() in HArr::stringArrayToArray has no caller anywhere, so it cannot be reached, but dormant code like this is exactly what an attacker or auditor looks for.',
            'Delete the unused function HArr::stringArrayToArray (nothing calls it). If a similar need appears, use json_decode() instead of eval().'
        ));
    }

    public function test_no_debugging_output_is_left_in_the_code(): void
    {
        $evidence = [];

        foreach (['app', 'routes'] as $dir) {
            foreach ($this->scan->files($dir, 'php') as $file) {
                foreach ($this->scan->debugCalls($file) as $hit) {
                    $evidence[] = $this->scan->relative($file).":{$hit['line']}  {$hit['call']}()";
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-DEBUG', 'LOW',
            'Debugging calls left in the code (dd / dump / var_dump / phpinfo)',
            $evidence,
            'These print internal variables, file paths and sometimes credentials straight into the page a visitor sees.',
            'Remove them.'
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * SQL injection
     * ────────────────────────────────────────────────────────────── */

    public function test_no_raw_sql_is_built_from_request_input(): void
    {
        $evidence = [];

        foreach ($this->scan->files('app', 'php') as $file) {
            foreach ($this->scan->dynamicRawSql($file) as $hit) {
                if ($hit['tainted']) {
                    $evidence[] = $this->scan->relative($file).":{$hit['line']}  {$hit['call']}  {$hit['snippet']}";
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-SQL', 'CRITICAL',
            'Raw SQL contains text taken straight from the request (SQL injection)',
            $evidence,
            'SQL injection lets a visitor read or destroy every table in the database by typing a crafted value into a form or URL.',
            'Pass values as bound parameters (the "?" placeholders), never by joining them into the SQL string. For column or table names chosen by the user, match them against a fixed list first.'
        ));
    }

    /** RATCHET. */
    public function test_the_amount_of_dynamically_built_sql_does_not_grow(): void
    {
        $found = [];

        foreach ($this->scan->files('app', 'php') as $file) {
            $count = count($this->scan->dynamicRawSql($file));

            if ($count > 0) {
                $found[$this->scan->relative($file)] = $count;
            }
        }

        $grew = [];

        foreach ($found as $relative => $count) {
            $allowed = self::RAW_SQL_BASELINE[$relative] ?? 0;

            if ($count > $allowed) {
                $grew[] = "{$relative}: {$count} found, {$allowed} reviewed";
            }
        }

        $this->assertSame([], $grew, Finding::message(
            'SEC-SQL-RATCHET', 'REVIEW',
            'New SQL built from variables since the last review',
            $grew,
            'Every SQL string assembled from variables is a place where a future change could let user input in. The reviewed ones are fixed in this test; new ones need a human look.',
            'Open each line listed. If the variable is a constant, a literal or an integer, raise the number in RAW_SQL_BASELINE. If it can contain user text, switch to bound parameters.'
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * Mass assignment
     * ────────────────────────────────────────────────────────────── */

    public function test_models_are_never_written_with_the_whole_request(): void
    {
        $evidence = [];

        foreach (['app/Http', 'app/Services', 'app/Jobs', 'app/Console', 'app/Imports'] as $dir) {
            foreach ($this->scan->files($dir, 'php') as $file) {
                foreach ($this->scan->massAssignmentFromRequest($file) as $hit) {
                    $evidence[] = $this->scan->relative($file).":{$hit['line']}  {$hit['call']}( {$hit['snippet']} )";
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-05', 'MEDIUM',
            'A whole request is handed to a model (mass assignment)',
            $evidence,
            'Whatever fields a visitor adds to the request are saved to the database — including ones the form never showed, such as max_users, created_by, or a plain-text password on the User model, or any column of Company (which accepts every column).',
            'Replace $request->except(...) / ->all() with $request->only([...the exact fields the form has...]) or $request->validated().'
        ));
    }

    /** RATCHET. */
    public function test_models_that_accept_every_column_do_not_multiply(): void
    {
        $count = 0;
        $list = [];

        foreach ($this->scan->files('app/Models', 'php') as $file) {
            if ($this->scan->hasEmptyGuard($file)) {
                $count++;
                $list[] = $this->scan->relative($file);
            }
        }

        $this->assertLessThanOrEqual(
            self::MODELS_WITH_OPEN_GUARD_BASELINE,
            $count,
            Finding::message(
                'SEC-05-RATCHET', 'REVIEW',
                "More models declare `\$guarded = []` than at the last review ({$count} vs ".self::MODELS_WITH_OPEN_GUARD_BASELINE.')',
                $list,
                'An empty guard means every column can be written from a request. New models should list the columns that may be filled ($fillable) instead.',
                'Give the new model a $fillable list, or raise MODELS_WITH_OPEN_GUARD_BASELINE after review.'
            )
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * Cross-site scripting (XSS)
     * ────────────────────────────────────────────────────────────── */

    public function test_unescaped_html_output_is_limited_to_reviewed_places(): void
    {
        $evidence = [];

        foreach ($this->scan->files('resources/views', 'php') as $file) {
            $relative = $this->scan->relative($file);

            if (in_array($relative, self::REVIEWED_UNESCAPED_BLADE, true)) {
                continue;
            }

            foreach ($this->scan->unescapedBladeOutput($file) as $hit) {
                $evidence[] = "{$relative}:{$hit['line']}  {!! {$hit['expression']} !!}";
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-XSS-BLADE', 'HIGH',
            'Blade prints unescaped HTML',
            $evidence,
            'Anything printed with {!! !!} reaches the browser as live HTML. If it contains text a user typed, that user can run script in other people\'s sessions (stored XSS) and act as them.',
            'Use {{ }}, which escapes automatically.'
        ));
    }

    public function test_browser_code_never_renders_raw_html_except_pagination_labels(): void
    {
        $evidence = [];

        foreach (['vue', 'js', 'ts'] as $extension) {
            foreach ($this->scan->files('resources/js', $extension) as $file) {
                foreach ($this->scan->htmlSinks($file) as $hit) {
                    // Laravel's paginator produces labels such as "&laquo; Previous" on the server.
                    // Those contain no user text, and entities are the reason v-html is used.
                    if ($hit['kind'] === 'v-html' && $hit['expression'] === 'link.label') {
                        continue;
                    }

                    $evidence[] = $this->scan->relative($file).":{$hit['line']}  {$hit['kind']}  ".substr($hit['expression'], 0, 80);
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-XSS-VUE', 'HIGH',
            'Front-end code turns text into live HTML or script',
            $evidence,
            'v-html / innerHTML / eval render their input as markup. If the text includes anything a user typed (a partner name, a comment), it becomes a script that runs in the next person\'s browser.',
            'Use normal {{ }} interpolation, which escapes. If HTML is truly needed, sanitise it first (e.g. DOMPurify).'
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * Secrets
     * ────────────────────────────────────────────────────────────── */

    public function test_no_credentials_are_committed_to_the_repository(): void
    {
        $evidence = [];

        foreach (['app', 'config', 'routes', 'database', 'resources/js'] as $dir) {
            foreach (['php', 'js', 'ts', 'vue', 'json'] as $extension) {
                foreach ($this->scan->files($dir, $extension, ['database/factories/']) as $file) {
                    foreach ($this->scan->secrets($file) as $hit) {
                        $evidence[] = $this->scan->relative($file).":{$hit['line']}  {$hit['kind']}  (value hidden)";
                    }
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-01', 'HIGH',
            'Passwords, login hashes or tokens are stored in the source code',
            $evidence,
            'Anyone who ever receives a copy of the project (a developer, a contractor, a backup, a leaked zip) gets these. The seeder data file holds a real user\'s plain-text Odoo password, their login password hash (which can be cracked offline) and their remember-me token.',
            'Treat the exposed passwords as compromised: change the Odoo password and the CashVero login password, and invalidate the remember-me token. Then remove these columns from database/seeders/data/cash_vero_general.php (or replace with obviously fake values) and keep real data out of the repository.'
        ));
    }

    public function test_odoo_password_is_encrypted_at_rest(): void
    {
        $user = $this->read('app/Models/User.php');

        $this->assertMatchesRegularExpression(
            '/[\'"]odoo_db_password[\'"]\s*=>\s*([\'"](encrypted|hashed)[\'"]|\\\\?(App\\\\Casts\\\\)?EncryptedOrPlain::class)/',
            $user,
            Finding::message(
                'SEC-02', 'MEDIUM',
                'Users\' Odoo passwords are stored in the database as readable text',
                ['app/Models/User.php: no "encrypted" cast for odoo_db_password', 'Nothing in app/ uses Laravel encryption (no encrypted casts, no Crypt::).'],
                'Anyone who obtains a database copy or backup reads every user\'s Odoo password and can sign in to Odoo as them. CashVero needs the password back (to call Odoo), so it must be encrypted, not hashed.',
                "Add 'odoo_db_password' => 'encrypted' to User::\$casts (Laravel then encrypts on write and decrypts on read automatically), and re-save existing rows once."
            )
        );
    }

    public function test_profile_page_does_not_send_the_odoo_password_to_the_browser(): void
    {
        $controller = $this->read('app/Http/Controllers/ProfileController.php');

        $this->assertDoesNotMatchRegularExpression(
            '/[\'"]odoo_db_password[\'"]\s*=>\s*\$user->odoo_db_password/',
            $controller,
            Finding::message(
                'SEC-03', 'MEDIUM',
                'The Profile page sends the Odoo password to the browser',
                ['app/Http/Controllers/ProfileController.php  edit(): \'odoo_db_password\' => $user->odoo_db_password'],
                'The password travels inside the page data on every visit to the profile screen, so it ends up in the browser cache, browser extensions, screen shares and any logging proxy.',
                'Send only whether a password is set (true/false). Let the user type a new one to change it, and treat an empty field as "keep the existing password".'
            )
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * Passwords
     * ────────────────────────────────────────────────────────────── */

    public function test_the_user_model_hashes_passwords_itself(): void
    {
        $user = $this->read('app/Models/User.php');

        $hashes = preg_match('/[\'"]password[\'"]\s*=>\s*[\'"]hashed[\'"]/', $user) === 1
            || preg_match('/function\s+setPasswordAttribute\s*\(/', $user) === 1
            || preg_match('/function\s+password\s*\(\s*\)\s*:\s*Attribute/', $user) === 1;

        $this->assertTrue($hashes, Finding::message(
            'SEC-06', 'LOW',
            'The User model does not hash passwords on its own',
            [
                'app/Models/User.php has no hashed cast or password mutator.',
                'app/Http/Controllers/UserController.php update(): $user->update($request->except(...)) writes whatever "password" arrives, as typed. (The edit form does not send one today, but a hand-made request can.)',
            ],
            'Hashing depends on every caller remembering to do it. One forgotten call leaves a password readable in the database, and that person can no longer sign in because the stored value is not a valid hash.',
            "Add 'password' => 'hashed' to User::\$casts. Laravel then hashes on every assignment and never double-hashes."
        ));
    }

    public function test_new_users_must_choose_a_password_that_meets_a_strength_rule(): void
    {
        $controller = $this->read('app/Http/Controllers/UserController.php');

        $store = $this->methodSource($controller, 'store');

        $this->assertMatchesRegularExpression(
            '/[\'"]password[\'"]\s*=>/',
            $this->validationBlock($store),
            Finding::message(
                'SEC-06b', 'MEDIUM',
                'The "create user" form accepts any password, including a single character',
                ['app/Http/Controllers/UserController.php store(): validates only email and role.'],
                'Weak passwords are the easiest way into any system. The public registration form requires 8 characters; the administrator form requires nothing at all.',
                "Validate 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()] when creating a user."
            )
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * Uploads
     * ────────────────────────────────────────────────────────────── */

    public function test_every_uploaded_file_is_validated_before_it_is_stored(): void
    {
        $evidence = [];

        foreach ($this->scan->files('app/Http', 'php') as $file) {
            $code = (string) file_get_contents($file);

            if (! preg_match_all('/saveIfExist\(\s*[\'"](\w+)[\'"]/', $code, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as [$field, $offset]) {
                $validated = preg_match(
                    '/[\'"]'.preg_quote($field, '/').'[\'"]\s*=>\s*[^\n]*\b(image|mimes|mimetypes|extensions)\b/',
                    $code
                ) === 1;

                if (! $validated) {
                    $line = substr_count(substr($code, 0, $offset), "\n") + 1;
                    $evidence[] = $this->scan->relative($file).":{$line}  upload field \"{$field}\" is saved with no type check";
                }
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-07', 'MEDIUM',
            'Uploaded files are stored without checking what they are',
            $evidence,
            'Files go to the public storage folder under their original name. A script (.php, .html, .svg) uploaded as a "profile picture" can be served from your own domain and attack other users (an HTML/SVG file can act as the administrator who views it); on some server set-ups a .php file would even run. Needs a user who may edit users or companies, so it is an escalation path rather than an open door — it becomes HIGH if your web server executes PHP inside storage/.',
            "Validate every upload, e.g. 'avatar' => ['nullable', 'image', 'max:2048'] (ProfileController already does this correctly), and make sure the web server never executes files in storage/."
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * Choosing a database table from the URL
     * ────────────────────────────────────────────────────────────── */

    public function test_the_bulk_delete_controller_whitelists_which_tables_it_may_touch(): void
    {
        // The control: this one was fixed in the 2026-08 audit and must stay fixed.
        $this->assertStringContainsString(
            'bulk_deletable_models',
            $this->read('app/Http/Controllers/DeletingClass.php'),
            'DeletingClass must keep checking the {model} against config(permissions.bulk_deletable_models).'
        );
    }

    public function test_the_single_record_delete_endpoint_whitelists_which_tables_it_may_touch(): void
    {
        $code = $this->read('app/Http/Controllers/Helpers/DeleteSingleRecordController.php');

        $restricts = preg_match('/bulk_deletable_models|in_array\s*\(|array_key_exists\s*\(|abort_unless|abort_if|Validator|->validate\s*\(/', $code) === 1;

        $this->assertTrue($restricts, Finding::message(
            'SEC-08', 'HIGH',
            'The "delete-model" endpoint deletes any record of any table named in the request',
            [
                'app/Http/Controllers/Helpers/DeleteSingleRecordController.php: (getModelNamespace().$request->get(\'modelName\'))::find($request->get(\'recordId\'))->delete()',
                'Route: DELETE {company}/delete-model (name: delete.model) — needs only an ordinary "delete invoice" style permission.',
            ],
            'Someone allowed to delete one invoice can send modelName=User (or Company, BankStatement, …) and any id and delete that record, in ANY company. It is the same hole as the old /Truncate endpoint, which was fixed; this sibling was not.',
            "Reuse the whitelist DeletingClass uses (config 'permissions.bulk_deletable_models'), and load the record scoped to the caller's company (->where('company_id', \$company->id)->findOrFail(...))."
        ));
    }

    public function test_the_export_field_picker_whitelists_which_tables_it_may_describe(): void
    {
        $code = $this->read('app/Http/Controllers/ExportTable.php');

        $start = strpos($code, 'function customizedTableField');
        $end = $start === false ? false : strpos($code, 'new $model_name', $start);

        $this->assertNotFalse($end, 'ExportTable::customizedTableField changed shape; update this test so it keeps protecting the endpoint.');

        $beforeInstantiation = substr($code, (int) $start, (int) $end - (int) $start);

        $this->assertMatchesRegularExpression(
            '/in_array\s*\(|abort_unless|abort_if|array_key_exists\s*\(/',
            $beforeInstantiation,
            Finding::message(
                'SEC-09', 'LOW',
                'The export field picker builds a model from any name in the URL',
                ['app/Http/Controllers/ExportTable.php customizedTableField(): new (\'App\\Models\\\' . $model)  — no whitelist'],
                'Anyone allowed on this screen can ask for the column list of any model, including User (password, remember_token, odoo_db_password), and save an export configuration for it. It exposes the database layout and invites a later mistake.',
                'Accept only the handful of models that have an export screen (a fixed list), and return 404 for everything else.'
            )
        );
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /** Source text of one method (from its `function name` to the next `public|protected|private function`). */
    private function methodSource(string $code, string $method): string
    {
        $start = strpos($code, 'function '.$method.'(');

        $this->assertNotFalse($start, "Method {$method}() not found; update this test.");

        $next = preg_match('/\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s/', $code, $m, PREG_OFFSET_CAPTURE, (int) $start + 10);

        return $next ? substr($code, (int) $start, $m[0][1] - (int) $start) : substr($code, (int) $start);
    }

    /** The `->validate([ ... ])` argument inside a method body, or '' when there is none. */
    private function validationBlock(string $methodSource): string
    {
        return preg_match('/->validate\s*\(\s*\[(.*?)\]\s*\)/s', $methodSource, $m) ? $m[1] : '';
    }
}
