<?php

namespace Tests\Feature\Uploads;

use App\Enums\LgSources;
use App\Jobs\LgIssuanceProcessImportJob;
use App\Models\LgIssuanceImportRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Uploading a spreadsheet of letters of guarantee: the file is kept, an import run is opened
 * and the import is queued — and anything that is not a spreadsheet, or is too big, is refused
 * before it reaches the disk. The disk is fake, so nothing is written for real.
 *
 * Run: php artisan test --filter=LgImportUploadTest
 */
class LgImportUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class]);
        Storage::fake('local');
        Queue::fake();
    }

    private function upload(UploadedFile $file, string $source = LgSources::LG_FACILITY, array $permissions = ['lg_issuance.import'])
    {
        $company = $this->makeCompany();
        $this->signInWith($permissions, $company);

        return [$company, $this->postJson(route('import.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => $source]), ['file' => $file])];
    }

    public function test_a_spreadsheet_is_kept_and_its_import_is_queued(): void
    {
        [$company, $response] = $this->upload(UploadedFile::fake()->create('lgs.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));

        $response->assertOk()->assertJsonPath('status', true);
        $run = LgIssuanceImportRun::findOrFail($response->json('import_id'));

        $this->assertSame($company->id, (int) $run->company_id);
        $this->assertSame('pending', $run->status);
        $this->assertStringStartsWith('lg-imports/', $run->file_path);
        Storage::disk('local')->assertExists($run->file_path);
        Queue::assertPushed(LgIssuanceProcessImportJob::class);
    }

    /** @return array<string, array{UploadedFile}> */
    public static function refusedFiles(): array
    {
        return [
            'a PHP script' => [UploadedFile::fake()->createWithContent('lgs.php', '<?php echo "hi";')],
            'a program' => [UploadedFile::fake()->create('lgs.exe', 10, 'application/x-msdownload')],
            'a PHP script named like a spreadsheet' => [UploadedFile::fake()->createWithContent('lgs.xlsx.php', '<?php system("id");')],
            'more than 10 MB' => [UploadedFile::fake()->create('lgs.xlsx', 10241, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedFiles')]
    public function test_anything_else_is_refused_and_nothing_is_kept(UploadedFile $file): void
    {
        [, $response] = $this->upload($file);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, LgIssuanceImportRun::count());
        Queue::assertNothingPushed();
    }

    public function test_an_unknown_source_is_not_found(): void
    {
        [, $response] = $this->upload(UploadedFile::fake()->create('lgs.xlsx', 20), 'made-up-source');

        $response->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_someone_without_the_import_permission_cannot_upload(): void
    {
        [, $response] = $this->upload(UploadedFile::fake()->create('lgs.xlsx', 20), LgSources::LG_FACILITY, ['lg_issuance.view']);

        $response->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** The status of an import is visible only inside the company that ran it. */
    public function test_another_companys_import_cannot_be_looked_at(): void
    {
        [, $response] = $this->upload(UploadedFile::fake()->create('lgs.xlsx', 20));
        $theirRun = $response->json('import_id');

        $outsider = $this->makeCompany();
        $this->signInWith(['lg_issuance.import'], $outsider);

        $this->getJson(route('status.letter.of.guarantee.issuance.import', ['company' => $outsider->id, 'importRun' => $theirRun]))
            ->assertNotFound();
    }
}
