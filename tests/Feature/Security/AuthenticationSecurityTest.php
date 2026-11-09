<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\UsesScratchAccounts;
use Tests\TestCase;

/**
 * SECURITY AUDIT — signing in, signing out, password reset.
 *
 * Plays the attacker at the login door: guessing passwords, probing which
 * e-mail addresses exist, abusing "forgot password", reusing a session after
 * logout. Uses only throw-away accounts in a fake company, made by the test
 * and rolled back afterwards (see UsesScratchAccounts).
 */
#[Group('security')]
class AuthenticationSecurityTest extends TestCase
{
    use UsesScratchAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootScratch(false);
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_the_login_page(): void
    {
        $response = $this->get(route('profile.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_wrong_password_does_not_sign_anyone_in(): void
    {
        $user = $this->scratchUser([$this->companyA->id]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'definitely-wrong'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_the_correct_password_signs_in_so_the_denial_tests_mean_something(): void
    {
        $user = $this->scratchUser([$this->companyA->id]);
        $user->forceFill(['password' => bcrypt('Known-Pass-123!')])->save();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Known-Pass-123!']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_repeated_wrong_passwords_lock_the_account_out(): void
    {
        $user = $this->scratchUser([$this->companyA->id]);
        $last = null;

        for ($i = 0; $i < 12; $i++) {
            $last = $this->postJson(route('login'), ['email' => $user->email, 'password' => 'guess-'.$i]);
        }

        $this->assertSame(429, $last->getStatusCode(), Finding::message(
            'SEC-AUTH-LOCKOUT', 'HIGH',
            'Unlimited password guessing is possible',
            ['12 wrong passwords in a row were still answered with HTTP '.$last->getStatusCode().' instead of 429 (Too Many Attempts)'],
            'An attacker can try thousands of passwords per minute against a known e-mail address.',
            'Keep the ThrottlesLogins trait active in LoginController (Laravel default: 5 tries per minute).'
        ));

        // Even the right password must be refused while locked out.
        $this->postJson(route('login'), ['email' => $user->email, 'password' => 'irrelevant'])->assertStatus(429);
    }

    public function test_login_error_is_identical_for_existing_and_unknown_e_mails(): void
    {
        $user = $this->scratchUser([$this->companyA->id]);

        $known = $this->postJson(route('login'), ['email' => $user->email, 'password' => 'nope-1']);
        $unknown = $this->postJson(route('login'), ['email' => 'nobody-'.bin2hex(random_bytes(4)).'@example.test', 'password' => 'nope-1']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(
            $known->json('errors.email'),
            $unknown->json('errors.email'),
            'The login page words its error differently for real and unknown e-mails, which tells an attacker which addresses have accounts.'
        );
    }

    /**
     * SEC-18 — "Forgot password" must not reveal whether an e-mail is registered.
     */
    public function test_forgot_password_does_not_reveal_which_e_mails_have_accounts(): void
    {
        Notification::fake();
        $user = $this->scratchUser([$this->companyA->id]);

        $known = $this->postJson(route('password.email'), ['email' => $user->email]);
        $unknown = $this->postJson(route('password.email'), ['email' => 'nobody-'.bin2hex(random_bytes(4)).'@example.test']);

        $this->assertSame(
            [$known->getStatusCode(), $known->json('errors.email')],
            [$unknown->getStatusCode(), $unknown->json('errors.email')],
            Finding::message(
                'SEC-18', 'LOW',
                'Password reset reveals which e-mail addresses are registered',
                ['known e-mail: HTTP '.$known->getStatusCode().' '.json_encode($known->json('errors.email')),
                    'unknown e-mail: HTTP '.$unknown->getStatusCode().' '.json_encode($unknown->json('errors.email'))],
                'An outsider can test a list of e-mail addresses and learn exactly who uses CashVero, then target those people with phishing.',
                'Answer with the same neutral message ("If that address exists, we sent a link") in both cases. This is a small change in the ForgotPasswordController response.'
            )
        );
    }

    public function test_a_made_up_reset_token_cannot_set_a_new_password(): void
    {
        $user = $this->scratchUser([$this->companyA->id]);

        $this->postJson(route('password.update'), [
            'token' => str_repeat('a', 64),
            'email' => $user->email,
            'password' => 'Hacked-Pass-123!',
            'password_confirmation' => 'Hacked-Pass-123!',
        ])->assertStatus(422);

        $this->assertFalse(\Hash::check('Hacked-Pass-123!', $this->reload($user)->password));
    }

    public function test_after_logout_the_old_session_no_longer_works(): void
    {
        $user = $this->scratchUser([$this->companyA->id]);

        $this->actingAs($user)->post(route('logout'));
        $this->assertGuest();

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_a_self_registered_account_gets_no_access_to_any_company(): void
    {
        if (! \Route::has('register')) {
            $this->addToAssertionCount(1); // registration is closed: nothing to attack

            return;
        }

        $email = 'sec-test-'.bin2hex(random_bytes(6)).'@example.test';
        $response = $this->post(route('register'), [
            'name' => 'Intruder', 'email' => $email,
            'password' => 'Intruder-Pass-123!', 'password_confirmation' => 'Intruder-Pass-123!',
        ]);

        $created = User::where('email', $email)->first();

        if (! $created) {
            $this->assertContains($response->getStatusCode(), [302, 403, 404, 405, 422, 429]);

            return;
        }

        $this->assertSame(
            [0, 0, 0],
            [$created->companies()->count(), $created->roles()->count(), $created->permissions()->count()],
            'A self-registered stranger received companies, roles or permissions.'
        );
    }
}
