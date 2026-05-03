<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplication_mappings', function (Blueprint $table) {
            $table->id();

            $table->uuid('duplication_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('old_id');
            $table->unsignedBigInteger('new_id');

            $table->timestamps();

            $table->foreign('duplication_id')
                ->references('id')
                ->on('episode_duplications')
                ->cascadeOnDelete();

            $table->unique(['duplication_id', 'entity_type', 'old_id'], 'duplication_unique_mapping');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplication_mappings');
    }
};
