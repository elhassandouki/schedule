<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('users', fn (Blueprint $table) => $table->unsignedSmallInteger('max_weekly_hours')->nullable()->after('program_id')); } public function down(): void { Schema::table('users', fn (Blueprint $table) => $table->dropColumn('max_weekly_hours')); } };
