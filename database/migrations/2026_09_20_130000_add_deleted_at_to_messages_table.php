<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the sender delete their own message. Uses a plain nullable
 * `deleted_at` column rather than Eloquent's SoftDeletes trait on
 * purpose: SoftDeletes hides the row from every query automatically,
 * which would make the message vanish from the conversation entirely.
 * Chat apps instead leave a "This message was deleted" placeholder in
 * its place, so the conversation still reads sensibly — that needs the
 * row to still be fetched, just with its content withheld.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
