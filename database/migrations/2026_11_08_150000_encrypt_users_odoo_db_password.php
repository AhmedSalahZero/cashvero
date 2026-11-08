<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-time conversion (Security SEC-02): users.odoo_db_password was stored as readable text.
 * Encrypts every value that is not already encrypted. Safe to run twice: values that already
 * decrypt are skipped. Needs the same APP_KEY the application uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('odoo_db_password')
            ->where('odoo_db_password', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                try {
                    Crypt::decryptString($row->odoo_db_password);

                    return; // already encrypted
                } catch (DecryptException) {
                    DB::table('users')->where('id', $row->id)->update([
                        'odoo_db_password' => Crypt::encryptString($row->odoo_db_password),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally empty: decrypting everything again would re-expose the passwords.
    }
};
