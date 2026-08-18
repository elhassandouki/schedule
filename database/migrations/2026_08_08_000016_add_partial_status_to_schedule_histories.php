<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Convertir la colonne enum MySQL en varchar : les enum sont fragiles
        // (valeur 'partial' refusée avec 'Data truncated' quand le driver PDO
        // émule les prepares, et la colonne enum empêche la création de
        // l'histoire de génération dans les tests RefreshDatabase).
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE schedule_histories CHANGE status status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE schedule_histories CHANGE status status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        }
    }
};
