<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.osc_meetings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('meeting_type', 30)->default('jawatankuasa_osc');
            $table->string('number', 30);
            $table->timestampTz('scheduled_at');
            $table->string('venue');
            $table->timestampTz('proposal_cutoff_at');
            $table->timestampTz('paper_cutoff_at');
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('agenda_version')->default(0);
            $table->unsignedInteger('row_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['meeting_type', 'number']);
        });

        Schema::create('core.osc_meeting_members', function (Blueprint $table): void {
            $table->id();
            $table->ulid('meeting_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->foreign('meeting_id')->references('id')->on('core.osc_meetings')->cascadeOnDelete();
            $table->unique(['meeting_id', 'user_id', 'role']);
        });
        DB::statement("CREATE UNIQUE INDEX osc_meeting_members_one_chair ON core.osc_meeting_members (meeting_id) WHERE role = 'chair'");

        Schema::create('core.osc_agenda_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('meeting_id');
            $table->ulid('application_id')->nullable();
            $table->string('department_id', 20);
            $table->string('application_type_id', 20)->nullable();
            $table->string('standalone_type', 100)->nullable();
            $table->string('title', 1000);
            $table->string('reference')->nullable();
            $table->string('purpose', 30);
            $table->foreignId('presenter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('proposed');
            $table->unsignedInteger('position')->nullable();
            $table->text('return_reason')->nullable();
            $table->ulid('previous_item_id')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();
            $table->foreign('meeting_id')->references('id')->on('core.osc_meetings')->restrictOnDelete();
            $table->foreign('application_id')->references('id')->on('applications')->restrictOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->foreign('application_type_id')->references('id')->on('application_types')->restrictOnDelete();
        });
        Schema::table('core.osc_agenda_items', function (Blueprint $table): void {
            $table->foreign('previous_item_id')->references('id')->on('core.osc_agenda_items')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE core.osc_agenda_items ADD CONSTRAINT osc_agenda_items_subject_check CHECK (application_id IS NOT NULL OR standalone_type IS NOT NULL)');

        Schema::create('core.osc_agenda_item_papers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('agenda_item_id');
            $table->unsignedInteger('version');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->foreign('agenda_item_id')->references('id')->on('core.osc_agenda_items')->restrictOnDelete();
            $table->unique(['agenda_item_id', 'version']);
        });

        Schema::create('core.osc_agenda_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('meeting_id');
            $table->unsignedInteger('version');
            $table->jsonb('items');
            $table->jsonb('changes');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at');
            $table->timestamps();
            $table->foreign('meeting_id')->references('id')->on('core.osc_meetings')->restrictOnDelete();
            $table->unique(['meeting_id', 'version']);
        });

        Schema::create('core.osc_department_settings', function (Blueprint $table): void {
            $table->string('department_id', 20)->primary();
            $table->boolean('requires_endorsement')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('core.osc_meetings')->exists()) {
            throw new RuntimeException('OSC meetings exist; use a data-preserving forward fix.');
        }
        Schema::dropIfExists('core.osc_department_settings');
        Schema::dropIfExists('core.osc_agenda_versions');
        Schema::dropIfExists('core.osc_agenda_item_papers');
        Schema::dropIfExists('core.osc_agenda_items');
        Schema::dropIfExists('core.osc_meeting_members');
        Schema::dropIfExists('core.osc_meetings');
    }
};
