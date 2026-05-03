<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_key');
            $table->enum('source_type', ['github', 'stripe', 'monitoring', 'custom'])->default('custom');
            $table->string('name');
            $table->string('signing_secret')->nullable();
            $table->json('event_mappings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'source_key']);
            $table->index(['project_id', 'source_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_sources');
    }
};
