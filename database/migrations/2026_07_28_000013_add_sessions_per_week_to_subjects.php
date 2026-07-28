<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('subjects', fn (Blueprint $table) => $table->unsignedTinyInteger('sessions_per_week')->default(1)->after('teacher_id')); }
    public function down(): void { Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn('sessions_per_week')); }
};
