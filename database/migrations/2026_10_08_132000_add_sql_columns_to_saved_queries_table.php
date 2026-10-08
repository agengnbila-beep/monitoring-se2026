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
     * Saved query kini punya dua jenis: `builder` (Filter Data, terikat satu
     * dataset) dan `sql` (SQL bebas yang dibaca lewat koneksi read-only,
     * bisa memakai beberapa dataset, disimpan dalam folder).
     */
    public function up(): void
    {
        Schema::table('saved_queries', function (Blueprint $table) {
            $table->string('type', 20)->default('builder')->after('id');
            $table->text('sql')->nullable();
            $table->foreignId('folder_id')->nullable()->constrained('query_folders')->nullOnDelete();
            $table->foreignId('dataset_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('saved_queries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['type', 'sql']);
        });
    }
};
