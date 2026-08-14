<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre de semaines d'enseignement du semestre (utilisé par le
     * générateur pour calculer le volume horaire total : weekly_hours x semaines).
     * NULL = valeur par défaut du système (15 semaines).
     */
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->unsignedSmallInteger('weeks_count')->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('weeks_count');
        });
    }
};
