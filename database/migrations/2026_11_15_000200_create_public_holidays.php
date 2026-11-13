<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Public holidays excluded from working-day deadlines (e.g. the AIRS amendment window). Maintained by administrators.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.public_holidays', function (Blueprint $table): void {
            $table->date('date')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.public_holidays');
    }
};
