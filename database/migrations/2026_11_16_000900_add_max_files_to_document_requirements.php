<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How many current files a checklist item holds (LJUP 1 = form + Syarat Umum + Syarat Khusus). Default 1. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirements', fn (Blueprint $table) => $table->unsignedSmallInteger('max_files')->default(1));
    }

    public function down(): void
    {
        Schema::table('document_requirements', fn (Blueprint $table) => $table->dropColumn('max_files'));
    }
};
