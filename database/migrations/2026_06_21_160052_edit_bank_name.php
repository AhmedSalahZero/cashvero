<?php

use App\Models\Bank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $bankId = Bank::find(11);
		$nameEn = 'Bank NXT';
		$nameAr = 'بنك نكس';
		$bankId->name_en = $nameEn;
		$bankId->name_ar = $nameAr;
		$bankId->view_name = $nameEn.' '.$nameAr;
		$bankId->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
