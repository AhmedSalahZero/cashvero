<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * عدّادات الأرقام المسلسلة للحركات المالية (MR/CIS/2026/10/00001) .
 *
 * * صف لكل (شركة + بريفكس + سنة) فيه آخر رقم اتصرف — شوف
 * * App\Support\Serials\SerialAssigner::next() .
 *
 * * id عادي و unique منفصل (مش primary مركّب) : CompanyImportService
 * * بيشيل أول عمود في الـ primary key وقت النقل ، فلو company_id جواه
 * * كان هيضيع .
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('serial_sequences')) {
            return;
        }

        Schema::create('serial_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('prefix', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'prefix', 'year'], 'serial_sequences_company_prefix_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_sequences');
    }
};
