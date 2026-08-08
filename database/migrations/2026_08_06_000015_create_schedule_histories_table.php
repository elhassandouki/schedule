<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedule_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->enum('status', ['draft', 'generated', 'failed'])->default('draft');
            $table->unsignedInteger('generated_sessions_count')->default(0);
            $table->unsignedInteger('skipped_sessions_count')->default(0);
            $table->timestamps();
            
            $table->index(['semester_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_histories');
    }
};
