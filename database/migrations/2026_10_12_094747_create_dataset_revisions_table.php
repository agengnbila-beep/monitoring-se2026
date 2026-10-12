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
        Schema::create('dataset_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_path')->nullable();
            $table->string('file_type', 10);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('sheet_name')->nullable();
            $table->string('status', 20)->default('uploaded')->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->json('columns_added')->nullable();
            $table->json('columns_removed')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_revisions');
    }
};
