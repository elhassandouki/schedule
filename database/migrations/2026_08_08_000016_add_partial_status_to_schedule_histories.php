<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL: Modify enum to add 'partial'
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schedule_histories MODIFY status ENUM('draft', 'generated', 'failed', 'partial') DEFAULT 'draft'");
        } else {
            // For other databases, use a different approach if needed
            Schema::table('schedule_histories', function (Blueprint $table) {
                // This won't work for all DBs, but MySQL enum is what we're using
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schedule_histories MODIFY status ENUM('draft', 'generated', 'failed') DEFAULT 'draft'");
        }
    }
};
