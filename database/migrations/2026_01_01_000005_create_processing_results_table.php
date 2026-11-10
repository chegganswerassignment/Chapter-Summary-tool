<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->string('result_type');
            $table->string('status')->default('pending');
            $table->json('content_json')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'result_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_results');
    }
};
