<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Pengaturan tampilan (jenis chart + opsi) yang dipilih saat menyusun
     * Filter Data, dipakai untuk pratinjau dan saat ditampilkan di dashboard.
     */
    public function up(): void
    {
        Schema::table('saved_queries', function (Blueprint $table) {
            $table->json('visualization')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saved_queries', function (Blueprint $table) {
            $table->dropColumn('visualization');
        });
    }
};
