<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Space > Folder > List > Task hierarchy, ported from Matrix.
 *
 * Every table is prefixed `task_` so the generic names Matrix used (folders,
 * labels, comments) stay free for the rest of Artemis. A space belongs to an
 * Artemis workspace; everything below it reaches the workspace through it.
 * Tasks also carry `workspace_id` directly, because ticket identifiers (ART-12)
 * are unique per workspace rather than globally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'position']);
            $table->index('owner_id');
        });

        Schema::create('task_space_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('task_spaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();

            $table->unique(['space_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('task_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained('task_spaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->unsignedInteger('last_ticket_number')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['space_id', 'position']);
            $table->unique(['workspace_id', 'code']);
        });

        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('task_spaces')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('task_folders')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['space_id', 'folder_id', 'position']);
        });

        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('task_spaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 32)->nullable();
            $table->string('type', 32);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->index(['space_id', 'position']);
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('task_spaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->timestamps();

            $table->unique(['space_id', 'name']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->foreignId('task_status_id')->constrained('task_statuses')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('ticket_code', 10)->nullable();
            $table->unsignedInteger('ticket_number')->nullable();
            $table->text('description')->nullable();
            $table->string('priority', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['task_list_id', 'position']);
            $table->index('task_status_id');
            $table->index('due_at');
            $table->unique(['workspace_id', 'ticket_code', 'ticket_number']);
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('task_label_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['label_id', 'task_id']);
            $table->index('task_id');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            // Comments are always read as one task's list, oldest first.
            $table->index(['task_id', 'id']);
        });

        // Who attached a task file. On the shared media table because task
        // attachments are media-library rows; nullable so every other
        // collection is unaffected.
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->after('disk')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
        });

        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_label_task');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_statuses');
        Schema::dropIfExists('task_lists');
        Schema::dropIfExists('task_folders');
        Schema::dropIfExists('task_space_members');
        Schema::dropIfExists('task_spaces');
    }
};
