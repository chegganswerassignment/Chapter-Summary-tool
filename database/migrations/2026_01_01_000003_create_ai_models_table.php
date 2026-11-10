<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('api_model_code');
            $table->unsignedInteger('context_window')->default(8192);
            $table->string('task_specialty');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['task_specialty', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
