<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\UsesScratchAccounts;
use Tests\TestCase;

/**
 * SECURITY AUDIT — hostile input: injected SQL and disguised file uploads.
 *
 * Sends the kind of strings a scanner or an attacker types into search boxes
 * and upload buttons, and checks the application neither breaks, leaks, nor
 * stores a dangerous file. Throw-away users in a fake company only, rolled
 * back afterwards; uploads go to a fake disk, so no file is left behind.
 */
#[Group('security')]
class InputHandlingSecurityTest extends TestCase
{
    use UsesScratchAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootScratch(false);

        // Pictures that ARE accepted must not land in the real storage folder.
        Storage::fake(config('media-library.disk_name', 'public'));
    }

    private function viewer(): User
    {
        $actor = $this->scratchUser([$this->companyA->id], ['user.view'], 'user');

        $this->assertNotSame(403, $this->actingAs($actor)->get(route('user.index'))->getStatusCode(),
            'Test set-up problem: a user of company '.$this->companyA->id.' holding user.view cannot open the Users screen, so the attacks below would prove nothing.');

        return $actor;
    }

    /** @return array<string, array{0:string}> */
    public static function injectionPayloads(): array
    {
        return [
            'single quote' => ["'"],
            'classic OR 1=1' => ["' OR '1'='1"],
            'comment out the rest' => ["x' -- "],
            'UNION select' => ["x' UNION SELECT 1,2,3,4,5,6,7,8 -- "],
            'stacked query' => ["x'; DROP TABLE users; -- "],
            'LIKE wildcard flood' => [str_repeat('%', 200)],
            'backslash escape' => ['\\'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('injectionPayloads')]
    public function test_the_user_search_box_survives_sql_injection_strings(string $payload): void
    {
        $actor = $this->viewer();

        $response = $this->actingAs($actor)->get(route('user.index', ['search' => $payload]));

        $this->assertLessThan(500, $response->getStatusCode(), Finding::message(
            'SEC-SQL-SEARCH', 'HIGH',
            'A crafted search string makes the Users page crash',
            ['payload: '.$payload, 'HTTP '.$response->getStatusCode()],
            'A crash on quotes is the classic sign that input reaches the database as SQL text.',
            'Pass search terms as bound parameters (where(col, "like", $term)) never concatenated.'
        ));

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_an_always_true_injection_does_not_widen_the_results(): void
    {
        $actor = $this->viewer();

        $unique = 'NoSuchPerson-'.bin2hex(random_bytes(4));
        $normal = $this->actingAs($actor)->get(route('user.index', ['search' => $unique]));
        $attack = $this->actingAs($actor)->get(route('user.index', ['search' => $unique."' OR '1'='1"]));

        $normalTotal = $this->inertiaProps($normal)['paginator']['total'] ?? null;
        $attackTotal = $this->inertiaProps($attack)['paginator']['total'] ?? null;

        $this->assertIsInt($normalTotal, 'Test set-up problem: the Users page did not send its list (paginator.total), so the comparison below would prove nothing.');

        $this->assertSame(
            $normalTotal,
            $attackTotal,
            'The tautology string returned a different number of users than a plain no-match search: the SQL was altered.'
        );
    }

    public function test_a_time_delay_injection_does_not_slow_the_server(): void
    {
        $actor = $this->viewer();

        $start = microtime(true);
        $this->actingAs($actor)->get(route('user.index', ['search' => "x' OR SLEEP(4) -- "]));
        $took = microtime(true) - $start;

        $this->assertLessThan(3.0, $took, 'Search took '.round($took, 1).'s with a SLEEP(4) payload: the database executed the attacker\'s SQL (blind SQL injection).');
    }

    public function test_a_php_file_disguised_as_a_picture_is_rejected_as_an_avatar(): void
    {
        $actor = $this->viewer();

        $evil = UploadedFile::fake()->createWithContent('avatar.php', "<?php echo 'owned'; ?>");

        $response = $this->actingAs($actor)->put(route('profile.update'), [
            'name' => $actor->name, 'email' => $actor->email, 'avatar' => $evil,
        ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertCount(0, $this->reload($actor)->getMedia(), 'A .php file was stored as the user\'s picture.');
    }

    public function test_a_script_disguised_with_an_image_name_is_rejected(): void
    {
        $actor = $this->viewer();

        // A real temporary file: Laravel's fake() files claim a type from the file NAME, which would hide the problem.
        $path = tempnam(sys_get_temp_dir(), 'sec');
        file_put_contents($path, "<?php system(\$_GET['c']); ?>");
        $evil = new UploadedFile($path, 'photo.jpg', null, null, true);

        $response = $this->actingAs($actor)->put(route('profile.update'), [
            'name' => $actor->name, 'email' => $actor->email, 'avatar' => $evil,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_a_real_picture_is_accepted_so_the_rejections_above_are_meaningful(): void
    {
        $actor = $this->viewer();

        $this->actingAs($actor)->put(route('profile.update'), [
            'name' => $actor->name, 'email' => $actor->email, 'avatar' => UploadedFile::fake()->image('me.png', 40, 40),
        ])->assertSessionHasNoErrors();
    }

    public function test_a_name_containing_a_script_tag_is_not_echoed_back_as_live_html(): void
    {
        $actor = $this->viewer();
        $actor->forceFill(['name' => '<script>alert(1)</script>'])->save();

        $html = (string) $this->actingAs($actor)->get(route('profile.edit'))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'A user name containing a script tag appears unescaped in the page source (stored XSS).');
    }
}
