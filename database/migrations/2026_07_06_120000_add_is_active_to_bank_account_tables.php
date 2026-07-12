<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'clean_overdrafts',
        'fully_secured_overdrafts',
        'overdraft_against_commercial_papers',
        'overdraft_against_assignment_of_contracts',
        'certificates_of_deposits',
        'time_of_deposits',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'is_active')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->unsignedTinyInteger('is_active')->default(1)->after('company_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'is_active')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('is_active');
                });
            }
        }
    }
};
