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
        Schema::create('dataset_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name');
            $table->string('label');
            $table->string('type', 20);
            $table->unsignedBigInteger('null_count')->nullable();
            $table->unsignedBigInteger('unique_count')->nullable();
            $table->string('min_value')->nullable();
            $table->string('max_value')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_columns');
    }
};
