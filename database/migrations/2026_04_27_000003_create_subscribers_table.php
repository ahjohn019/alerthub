<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->unsignedInteger('notification_count')->default(0);
            $table->timestamp('last_notified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'email']);
            $table->unique(['project_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
