<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');           // e.g. role_changed, status_updated
            $table->string('target_type');      // 'user' | 'report'
            $table->unsignedBigInteger('target_id');
            $table->string('description');      // human-readable sentence
            $table->json('meta')->nullable();   // extra context (old/new values)
            $table->timestamps();

            $table->index(['action']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};