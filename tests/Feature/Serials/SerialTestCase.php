<?php

namespace Tests\Feature\Serials;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * الاختبارات دي بتشتغل على داتابيز التطوير (SMOKE_DB) زي باقي اختبارات
 * * الحركات ، و كل حاجة بتتكتب بترجع بـ rollback . السنة 2099 عشان
 * * العدّادات ما تتلخبطش مع الداتا الحقيقية .
 */
abstract class SerialTestCase extends TestCase
{
    protected const COMPANY = 148;

    protected const YEAR = 2099;

    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        if (! Company::find(self::COMPANY)) {
            $this->markTestSkipped('Company '.self::COMPANY.' is not on file.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /** @param  callable():void  $callback */
    protected function inRolledBackTransaction(callable $callback): void
    {
        DB::beginTransaction();

        try {
            $callback();
        } finally {
            DB::rollBack();
        }
    }
}
