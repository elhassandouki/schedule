<?php

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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Valeurs par défaut
        $defaults = [
            'establishment_name' => 'Université',
            'establishment_address' => '',
            'establishment_phone' => '',
            'establishment_email' => '',
            'logo_path' => '',
        ];

        foreach ($defaults as $key => $value) {
            \DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
