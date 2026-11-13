<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which officer corrections were already exported to MATRIX as candidate lessons (exports are incremental).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airs.lesson_exports', function (Blueprint $table): void {
            $table->string('source_type', 20);
            $table->ulid('source_id');
            $table->string('file', 255);
            $table->timestampTz('exported_at')->useCurrent();
            $table->primary(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airs.lesson_exports');
    }
};
