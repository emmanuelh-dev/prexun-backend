<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {

        DB::table('students')
            ->whereNotNull('phone')
            ->whereRaw('LENGTH(phone) = 10')
            ->update([
                'phone' => DB::raw("CONCAT('52', phone)")
            ]);

        // Tutores: Lo mismo
        DB::table('students')
            ->whereNotNull('tutor_phone')
            ->whereRaw('LENGTH(tutor_phone) = 10')
            ->update([
                'tutor_phone' => DB::raw("CONCAT('52', tutor_phone)")
            ]);
    }

    public function down()
    {
        DB::table('students')
            ->whereNotNull('phone')
            ->whereRaw('LENGTH(phone) = 12 AND phone LIKE "52%"')
            ->update(['phone' => DB::raw("SUBSTRING(phone, 3)")]);

        DB::table('students')
            ->whereNotNull('tutor_phone')
            ->whereRaw('LENGTH(tutor_phone) = 12 AND tutor_phone LIKE "52%"')
            ->update(['tutor_phone' => DB::raw("SUBSTRING(tutor_phone, 3)")]);
    }
};
