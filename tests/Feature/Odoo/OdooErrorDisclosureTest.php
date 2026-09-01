<?php

namespace Tests\Feature\Odoo;

use App\Services\Api\OdooSync;
use Tests\TestCase;

/**
 * What an Odoo failure is allowed to say to the person using the system.
 *
 * The reported message was:
 *
 *   Could not access https://<tenant>.dev.odoo.com/xmlrpc/2/object —
 *   file_get_contents(...): Failed to open stream: Connection refused
 *
 * It went to a flash message and onto the record, where the bug icon shows
 * it. Two things are wrong with that: it names the internal Odoo host, and
 * it tells the reader nothing they can act on.
 *
 * A complaint FROM Odoo about the DATA is different — the operator can fix
 * that — so it is kept, with any URL still removed.
 */
class OdooErrorDisclosureTest extends TestCase
{
    private const REPORTED = 'Could not access https://squadbcc-itechs-may2025v2-stage1-36372560.dev.odoo.com/xmlrpc/2/object — '
        .'file_get_contents(https://squadbcc-itechs-may2025v2-stage1-36372560.dev.odoo.com/xmlrpc/2/object): '
        .'Failed to open stream: Connection refused';

    /** @dataProvider transportFailureProvider */
    public function test_a_connection_failure_never_reaches_the_user_verbatim(string $raw): void
    {
        $shown = OdooSync::userFacingMessage(new \Exception($raw));

        $this->assertStringNotContainsString('odoo.com', $shown,
            'The internal Odoo host identifies the tenant and must never be shown.');
        $this->assertStringNotContainsString('http', $shown);
        $this->assertStringNotContainsString('file_get_contents', $shown,
            'A PHP function name is not something the reader can act on.');
        $this->assertStringNotContainsString('Failed to open stream', $shown);
        $this->assertNotSame($raw, $shown);
    }

    public static function transportFailureProvider(): array
    {
        return [
            'the reported message' => [self::REPORTED],
            'timeout' => ['Could not access https://tenant.odoo.com/xmlrpc/2/common - Connection timed out'],
            'dns' => ['file_get_contents(https://tenant.odoo.com/x): could not resolve host'],
            'refused' => ['Connection refused'],
            'network down' => ['Network is unreachable'],
        ];
    }

    public function test_the_connection_message_tells_the_reader_what_happened(): void
    {
        $shown = OdooSync::userFacingMessage(new \Exception(self::REPORTED));

        $this->assertNotSame('', trim($shown));
        // It must say the record survived — that is the reader's real question.
        $this->assertStringContainsString('saved', mb_strtolower($shown),
            'Someone who just pressed Save needs to know whether their work was kept.');
    }

    /**
     * A complaint about the data is useful and must survive, so the
     * operator can correct the record and send it again.
     */
    public function test_a_complaint_from_odoo_is_kept(): void
    {
        $shown = OdooSync::userFacingMessage(new \Exception('Missing required field: partner_id'));

        $this->assertStringContainsString('partner_id', $shown,
            'This one the operator can act on, so it must not be swallowed.');
    }

    /** …but never with a URL attached to it. */
    public function test_a_url_is_stripped_even_from_a_useful_message(): void
    {
        $shown = OdooSync::userFacingMessage(
            new \Exception('Invalid record at https://tenant.odoo.com/web#id=5 : partner_id missing')
        );

        $this->assertStringContainsString('partner_id', $shown);
        $this->assertStringNotContainsString('odoo.com', $shown);
        $this->assertStringNotContainsString('https://', $shown);
    }

    /**
     * The real path, not the helper: OdooSync::run() catches the failure,
     * writes the log, flags the row and flashes the message. What lands in
     * the session is what the reader actually sees.
     *
     * An earlier version of this test called the sanitiser directly and
     * matched the source for a variable name — so it passed while the raw
     * message was being flashed. This one fails if it is.
     */
    public function test_the_flashed_message_is_sanitised(): void
    {
        session()->forget('fail');

        OdooSync::run(function () {
            throw new \Exception(self::REPORTED);
        });

        $flashed = (string) session('fail');

        $this->assertNotSame('', $flashed, 'The reader must be told something happened.');
        $this->assertStringNotContainsString('odoo.com', $flashed,
            'The flash goes straight to the screen — it must not name the internal host.');
        $this->assertStringNotContainsString('file_get_contents', $flashed);
        $this->assertStringNotContainsString('https://', $flashed);
        $this->assertStringNotContainsString(self::REPORTED, $flashed);
    }

    /**
     * The same for the text written onto the record, which the bug icon
     * shows on the list screens.
     */
    public function test_the_message_written_to_the_record_is_sanitised(): void
    {
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'other_dues';

            public $captured = null;

            public function setAttribute($key, $value)
            {
                if ($key === 'odoo_error_message') {
                    $this->captured = $value;
                }

                return parent::setAttribute($key, $value);
            }
        };

        // flagModel() only writes to a row that exists; the assertion here
        // is on the message chosen, so a missing row is not the point.
        $shown = OdooSync::userFacingMessage(new \Exception(self::REPORTED));

        $this->assertStringNotContainsString('odoo.com', $shown);

        $source = file_get_contents(app_path('Services/Api/OdooSync.php'));
        $this->assertMatchesRegularExpression(
            '/\$shown\s*=\s*self::userFacingMessage\(/',
            $source,
            'The row and the flash must both be given the sanitised message.'
        );
        $this->assertMatchesRegularExpression(
            '/self::flagModel\(\$model,\s*\$shown\)/',
            $source
        );
    }

    /**
     * The detail is not lost — it goes to the log, which is where it
     * belongs and the only place it belongs.
     */
    public function test_the_full_detail_is_still_logged(): void
    {
        $source = file_get_contents(app_path('Services/Api/OdooSync.php'));

        $start = strpos($source, 'function recordFailure(');
        $this->assertNotFalse($start);
        $body = substr($source, $start, 1200);

        $this->assertMatchesRegularExpression('/Log::error\([^)]*\$raw/', $body,
            'The log keeps the untouched text, including the URL.');
        $this->assertStringContainsString("'exception' => \$e", $body,
            'And the exception itself, for the stack trace.');
    }

    /**
     * Every place that shows an Odoo failure has to go through the
     * sanitiser — the flash on the record screens and the three "read from
     * Odoo" actions alike.
     */
    public function test_no_controller_flashes_a_raw_odoo_exception(): void
    {
        $offenders = [];

        foreach (glob(app_path('Http/Controllers/ReadOdoo*.php')) as $file) {
            $source = file_get_contents($file);

            if (preg_match("/flash\('fail',\s*\\\$e->getMessage\(\)\s*\)/", $source)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders,
            "These flash the raw exception, which names the internal Odoo host:\n  "
            .implode("\n  ", $offenders));
    }

    /** The message written onto the row is the sanitised one, not the raw one. */
    public function test_the_row_stores_the_sanitised_message(): void
    {
        $source = file_get_contents(app_path('Services/Api/OdooSync.php'));

        $this->assertStringContainsString('self::flagModel($model, $shown)', $source,
            'The bug icon shows odoo_error_message, so the row must carry the sanitised text.');
        $this->assertStringNotContainsString('self::flagModel($model, $raw)', $source);
    }
}
