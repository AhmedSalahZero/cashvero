<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\UsesScratchAccounts;
use Tests\TestCase;

/**
 * SECURITY AUDIT — "can user X do or see something that belongs to someone else?"
 *
 * This is the part of a penetration test that matters most for a multi-company
 * treasury system: company A must never reach company B's people or money, a
 * junior user must never edit an administrator, and nobody may delete the
 * wrong thing by sending a hand-made request instead of clicking the button.
 *
 * Every attack here is paired with a POSITIVE CONTROL (the same action done
 * legitimately must work). Without it a "denied" result could just mean the
 * test set-up was wrong.
 *
 * Both companies, every user and the one invoice are made by the test itself
 * and rolled back afterwards (see UsesScratchAccounts): no real data is read.
 */
#[Group('security')]
class AccessControlSecurityTest extends TestCase
{
    use UsesScratchAccounts;

    private const MANAGER_RIGHTS = ['user.view', 'user.create', 'user.update', 'user.delete'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootScratch(true);
    }

    /** A user manager of company A, after proving the manager can reach the user screens at all. */
    private function userManager(): User
    {
        $actor = $this->scratchUser([$this->companyA->id], self::MANAGER_RIGHTS, 'user');

        $probe = $this->actingAs($actor)->get(route('user.index'));

        $this->assertNotSame(403, $probe->getStatusCode(), 'Test set-up problem: a user manager of company '.$this->companyA->id.' (subscribed to CashVero) cannot open the user screens, so every refusal below would prove nothing.');

        return $actor;
    }

    /** A denial test means nothing when even the legitimate edit is refused: stop it as a set-up failure. */
    private function requireWorkingEdit(User $actor): void
    {
        $colleague = $this->scratchUser([$this->companyA->id], [], 'user');

        $this->assertTrue($this->updateAs($actor, $colleague)->isRedirection(), 'Test set-up problem: the legitimate edit of an ordinary colleague is refused, so a refusal proves nothing. See the first test in this file for the reason.');
    }

    private function updateAs(User $actor, User $target, array $extra = [])
    {
        // Test-only quirk: in production the address is /en/user/{id} and the company gate reads
        // "user" (not a number) in segment 2 and steps aside. The test app has no /en prefix, so the
        // gate would read the USER ID as a company number and answer 403 to everybody. Switch only
        // that gate off here; the permission and role checks under test stay on.
        $this->withoutMiddleware(\App\Http\Middleware\canViewCurrentCompany::class);

        return $this->actingAs($actor)->put(
            route('user.update', ['user' => $target->id]),
            array_merge(['name' => 'HACKED', 'email' => $target->email, 'role' => $target->getRoleName() ?: 'user', 'companies' => $target->companies->pluck('id')->all()], $extra)
        );
    }

    /* ── Editing other users (SEC-14) ──────────────────────────────── */

    public function test_a_user_manager_can_edit_an_ordinary_colleague_in_the_same_company(): void
    {
        $actor = $this->userManager();
        $colleague = $this->scratchUser([$this->companyA->id], [], 'user');

        $response = $this->updateAs($actor, $colleague);

        $this->assertTrue(
            $response->isRedirection(),
            'Positive control failed: a user manager could not edit an ordinary colleague (HTTP '.$response->getStatusCode().': '
            .($response->exception?->getMessage() ?: 'no message').'). The denial tests below would prove nothing.'
        );

        $this->assertSame('HACKED', $this->reload($colleague)->name, 'Positive control failed: the legitimate edit did not work, so the denial tests below prove nothing.');
    }

    public function test_a_user_manager_cannot_edit_a_super_admin(): void
    {
        $actor = $this->userManager();
        $this->requireWorkingEdit($actor);
        $boss = $this->scratchUser([$this->companyA->id], [], 'super-admin');

        $response = $this->updateAs($actor, $boss, ['password' => 'Takeover-123!']);

        $this->assertNotSame('HACKED', $this->reload($boss)->name, Finding::message(
            'SEC-14', 'HIGH',
            'A user manager can rewrite a Super Admin account',
            ['PUT user.update on a Super Admin by a company user manager answered HTTP '.$response->getStatusCode().' and changed the account'],
            'The check only asks "may you assign this ROLE?" and the role being re-sent is the one the target already has, so it always passes. A person with the "update user" right can set a new name, e-mail and password on the owner account and then sign in as the owner.',
            'In UserController::update() also require that the target user is visible to the editor (same company and not higher-ranked): e.g. abort_unless(User::getUsersWithRolesQuery($company)->whereKey($user->id)->exists(), 403).'
        ));
    }

    public function test_a_user_manager_cannot_edit_a_user_of_another_company(): void
    {
        $actor = $this->userManager();
        $this->requireWorkingEdit($actor);
        $stranger = $this->scratchUser([$this->companyB->id], [], 'user');

        $response = $this->updateAs($actor, $stranger);

        $this->assertNotSame('HACKED', $this->reload($stranger)->name, Finding::message(
            'SEC-14', 'HIGH',
            'A manager of company A can edit users of company B',
            ['PUT user.update on a company-B user by a company-A manager answered HTTP '.$response->getStatusCode().' and changed the account'],
            'Tenant isolation is broken for user accounts: a customer\'s manager can change the name, e-mail and password of another customer\'s staff, then log in as them.',
            'Same fix as above: reject targets that do not share a company with the editor.'
        ));
    }

    public function test_editing_a_user_cannot_raise_the_user_limit_or_store_a_plain_text_password(): void
    {
        $actor = $this->userManager();
        $this->requireWorkingEdit($actor);
        $colleague = $this->scratchUser([$this->companyA->id], [], 'user');
        $before = (int) ($colleague->max_users ?? 0);

        $this->updateAs($actor, $colleague, ['name' => 'Colleague', 'max_users' => 99999, 'password' => 'PlainText-123!']);

        $after = $this->reload($colleague);

        $this->assertSame($before, (int) ($after->max_users ?? 0), Finding::message(
            'SEC-05b', 'MEDIUM',
            'The edit-user form accepts "max_users" from the request',
            ['max_users went from '.$before.' to '.(int) $after->max_users],
            'max_users is the paid-seat limit. Anyone allowed to edit a user can lift it, bypassing the subscription cap.',
            'In UserController::update() use $request->only(["name","email","password"]) instead of $request->except([...]).'
        ));

        $this->assertNotSame('PlainText-123!', $after->password, Finding::message(
            'SEC-06c', 'HIGH',
            'Changing a user\'s password through the edit form stores it unscrambled',
            ['The password column contains the exact text that was typed'],
            'Anyone with database or backup access can read that password, and the person can no longer log in because the login compares hashes.',
            'Hash it: Hash::make() in update(), or add the "hashed" cast to the User model.'
        ));
    }

    /* ── Creating users ────────────────────────────────────────────── */

    public function test_creating_a_user_with_a_one_letter_password_is_refused(): void
    {
        $actor = $this->userManager();
        $email = 'sec-test-'.bin2hex(random_bytes(6)).'@example.test';

        $this->actingAs($actor)->post(route('user.store'), [
            'name' => 'Weak Pass', 'email' => $email, 'password' => 'a', 'role' => 'user',
            'companies' => [$this->companyA->id],
        ]);

        $created = User::where('email', $email)->first();

        $this->assertNull($created, Finding::message(
            'SEC-06b', 'MEDIUM',
            'A new user can be created with a one-character password',
            ['password "a" was accepted'],
            'Treasury accounts protected by trivial passwords fall to the first password-guessing attempt.',
            "Add 'password' => ['required', Password::min(10)->mixedCase()->numbers()] to UserController::store() validation."
        ));
    }

    public function test_a_user_manager_cannot_create_a_super_admin(): void
    {
        $actor = $this->userManager();
        $email = 'sec-test-'.bin2hex(random_bytes(6)).'@example.test';

        $response = $this->actingAs($actor)->post(route('user.store'), [
            'name' => 'Sneaky', 'email' => $email, 'password' => 'Strong-Pass-123!', 'password_confirmation' => 'Strong-Pass-123!', 'role' => 'super-admin',
            'companies' => [$this->companyA->id],
        ]);

        $created = User::where('email', $email)->first();

        $this->assertNull($created, 'A non-Super-Admin created a Super Admin (HTTP '.$response->getStatusCode().').');
    }

    public function test_a_user_manager_cannot_attach_a_new_user_to_a_company_they_do_not_belong_to(): void
    {
        $actor = $this->userManager();
        $email = 'sec-test-'.bin2hex(random_bytes(6)).'@example.test';

        $this->actingAs($actor)->post(route('user.store'), [
            'name' => 'Cross', 'email' => $email, 'password' => 'Strong-Pass-123!', 'password_confirmation' => 'Strong-Pass-123!', 'role' => 'user',
            'companies' => [$this->companyB->id],
        ]);

        $created = User::where('email', $email)->first();

        $this->assertFalse(
            $created && $created->companies()->where('companies.id', $this->companyB->id)->exists(),
            'A company-A manager placed a user inside company B.'
        );
    }

    /* ── Deleting users (SEC-15, SEC-08) ───────────────────────────── */

    public function test_a_user_manager_can_remove_an_ordinary_colleague(): void
    {
        $actor = $this->userManager();
        $colleague = $this->scratchUser([$this->companyA->id], [], 'user');

        $this->actingAs($actor)->post(route('remove.user'), ['user_id' => $colleague->id]);

        $this->assertFalse($this->exists($colleague), 'Positive control failed: legitimate removal did not work.');
    }

    public function test_a_user_manager_cannot_remove_a_super_admin_or_a_user_of_another_company(): void
    {
        $actor = $this->userManager();
        $boss = $this->scratchUser([$this->companyA->id], [], 'super-admin');
        $stranger = $this->scratchUser([$this->companyB->id], [], 'user');

        $this->actingAs($actor)->post(route('remove.user'), ['user_id' => $boss->id]);
        $this->actingAs($actor)->post(route('remove.user'), ['user_id' => $stranger->id]);

        $removed = array_filter([
            'a Super Admin' => ! $this->exists($boss),
            'a user of another company' => ! $this->exists($stranger),
        ]);

        $this->assertSame([], array_keys($removed), Finding::message(
            'SEC-15', 'HIGH',
            'remove.user deletes any user id it is given',
            array_map(fn ($k) => 'removed '.$k, array_keys($removed)),
            'RemoveUsercontroller does User::where(id)->firstOrFail()->delete() without checking company or rank. Anyone with the "delete user" right can lock out the owner or another customer\'s staff by posting an id.',
            'Look the user up through the same visibility rules as the Users list, and refuse your own account and any Super Admin.'
        ));
    }

    public function test_a_user_cannot_delete_their_own_account_by_accident(): void
    {
        $actor = $this->userManager();

        $this->actingAs($actor)->post(route('remove.user'), ['user_id' => $actor->id]);

        $this->assertTrue($this->exists($actor), 'A user removed their own account with one request (this also leaves a live session on a deleted user).');
    }

    /**
     * The generic delete endpoint builds a class name from the request. A person who only holds
     * the right to delete invoice rows can ask it to delete a USER instead.
     */
    public function test_the_generic_delete_endpoint_cannot_be_pointed_at_a_user(): void
    {
        $actor = $this->scratchUser([$this->companyA->id], ['customer_invoice_data.delete'], 'user');
        $victim = $this->scratchUser([$this->companyA->id], [], 'user');

        $response = $this->actingAs($actor)->deleteJson(
            route('delete.model', ['company' => $this->companyA->id]),
            ['modelName' => 'User', 'recordId' => $victim->id, 'tableId' => 'x']
        );

        $this->assertTrue($this->exists($victim), Finding::message(
            'SEC-08', 'HIGH',
            'delete.model deletes any model class the caller names',
            ['modelName=User, recordId='.$victim->id.' with only the invoice-delete right answered HTTP '.$response->getStatusCode().' and deleted the user'],
            'DeleteSingleRecordController does (\\App\\Models\\.$modelName)::find($id)->delete() with no allow-list and no company check, so a delete right on one screen becomes a delete right on every table of every company.',
            'Allow only a fixed list of model names, require the delete permission of THAT model, and find the record through the current company (->where("company_id", $company->id)).'
        ));
    }

    /* ── Reading other companies' data (SEC-13, SEC-20) ────────────── */

    public function test_a_company_a_user_cannot_read_a_company_b_invoice_through_the_due_date_page(): void
    {
        // A minimal receivable of company B: all the attack needs is its number.
        $invoiceId = DB::table('customer_invoices')->insertGetId([
            'company_id' => $this->companyB->id, 'customer_id' => 0,
            'customer_name' => 'Security Test Receivable', 'currency' => 'EGP',
        ]);

        $actor = $this->scratchUser([$this->companyA->id], ['adjusted_due_date.view'], 'user');

        $response = $this->actingAs($actor)->get(route('adjust.due.dates', [
            'company' => $this->companyA->id, 'modelId' => $invoiceId, 'modelType' => 'CustomerInvoice',
        ]));

        $this->assertLessThan(500, $response->getStatusCode(), 'The page crashed (HTTP '.$response->getStatusCode().') before it could answer, so the company check cannot be judged. A crash is itself a defect: '
            .($response->exception?->getMessage() ?: 'no message'));

        $this->assertContains($response->getStatusCode(), [403, 404], Finding::message(
            'SEC-13', 'HIGH',
            'Another company\'s invoice can be opened by changing a number in the address',
            ['GET adjust-due-dates/'.$invoiceId.'/CustomerInvoice under company '.$this->companyA->id.' answered HTTP '.$response->getStatusCode()],
            'The controller loads the invoice with Model::find($id). The route-model-binding company check only covers models bound by Laravel, not plain ids, so the invoice of another customer (amounts, client name, due dates) is shown.',
            'Find the invoice through the company: $company->customerInvoices()->findOrFail($id) (and the supplier equivalent), or abort 404 when $invoice->company_id !== $company->id.'
        ));
    }

    public function test_the_user_list_does_not_show_users_of_other_companies(): void
    {
        $actor = $this->userManager();
        $stranger = $this->scratchUser([$this->companyB->id], [], 'user');
        $stranger->forceFill(['name' => 'ZZ-Stranger-'.bin2hex(random_bytes(3))])->save();
        $colleague = $this->scratchUser([$this->companyA->id], [], 'user');
        $colleague->forceFill(['name' => 'ZZ-Colleague-'.bin2hex(random_bytes(3))])->save();

        // Read the names the page actually lists (the page also echoes the search text, so the raw HTML proves nothing).
        $namesFor = function (string $search) use ($actor): array {
            $response = $this->actingAs($actor)->get(route('user.index', ['search' => $search]));
            $response->assertOk();

            return array_column($this->inertiaProps($response)['paginator']['data'] ?? [], 'name');
        };

        $this->assertContains($colleague->name, $namesFor($colleague->name), 'Positive control failed: the list does not show a colleague of the same company (or its data could not be read).');

        $this->assertNotContains($stranger->name, $namesFor($stranger->name), Finding::message(
            'SEC-20', 'MEDIUM',
            'The Users list shows people from other companies',
            ['A company-A manager searching for a company-B user\'s name found them'],
            'When the page is opened without a company number the list query is not limited to the viewer\'s companies. It leaks names, e-mails, roles and company names of other customers.',
            'In UserController::index() always restrict to the viewer\'s own company ids unless the viewer is a Super Admin.'
        ));
    }
}
