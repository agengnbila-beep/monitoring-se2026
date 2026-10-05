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
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('file_path')->nullable();
            $table->string('file_type', 10);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('sheet_name')->nullable();
            $table->string('table_name')->nullable()->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedInteger('column_count')->default(0);
            $table->timestamp('profiled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
