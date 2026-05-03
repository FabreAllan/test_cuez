<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episode_duplications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('source_episode_id')->constrained('episodes');
            $table->foreignId('target_episode_id')->nullable()->constrained('episodes');

            $table->string('status')->default('pending');
            $table->unsignedInteger('progress')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_duplications');
    }
};
