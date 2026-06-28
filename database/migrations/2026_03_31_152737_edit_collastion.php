<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $statements = DB::select("
            SELECT CONCAT(
                'ALTER TABLE `', TABLE_NAME, '` MODIFY `', COLUMN_NAME, '` ', COLUMN_TYPE,
                ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                IF(IS_NULLABLE='NO', ' NOT NULL', ' NULL'),
                IF(COLUMN_DEFAULT IS NULL, '', CONCAT(' DEFAULT ', QUOTE(COLUMN_DEFAULT))),
                IF(EXTRA='', '', CONCAT(' ', EXTRA)),
                IF(COLUMN_COMMENT='', '', CONCAT(' COMMENT ', QUOTE(COLUMN_COMMENT))),
                ';'
            ) AS alter_stmt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLLATION_NAME IS NOT NULL
              AND COLLATION_NAME <> 'utf8mb4_unicode_ci'
            ORDER BY TABLE_NAME, ORDINAL_POSITION
        ");

        foreach ($statements as $statement) {
            DB::statement($statement->alter_stmt);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
