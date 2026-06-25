<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified, append-only log of every meaningful action in the application —
 * both user activity (audit trail) and automated system events (job/cron/
 * integration health). One table keeps querying and dashboards simple.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // ── Shared schema ────────────────────────────────────────────
            // 'user' | 'system' — drives which optional columns are populated.
            $table->string('log_type', 16);
            // auth | data | security | scheduled_job | integration | system ...
            $table->string('category', 32);
            // The verb: login, logout, create, update, delete, sync, fetch ...
            $table->string('action_type', 64)->nullable();
            // Actor. Null for anonymous / system-originated entries.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Tenant scope (this app is workspace-multi-tenant). Null for global.
            $table->foreignId('workspace_id')->nullable();
            // success | failure | warning | info
            $table->string('status', 16);
            // Human-readable summary line.
            $table->text('message')->nullable();
            // Free-form structured context (changed attributes, ids, payloads…).
            $table->json('metadata')->nullable();

            // ── User-activity context ────────────────────────────────────
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // ── System-log context (LogType::System) ─────────────────────
            // scheduled | event | manual
            $table->string('trigger_type', 16)->nullable();
            // Cron expression when trigger_type = scheduled (e.g. "0 9 * * *").
            $table->string('schedule', 64)->nullable();
            // Command / job class that produced the entry.
            $table->string('job_name')->nullable();
            // Stack trace / exception message for failures.
            $table->longText('error_detail')->nullable();

            // Immutable logs: only a creation timestamp, no updated_at.
            $table->timestamp('created_at')->useCurrent()->index();

            // ── Indexes tuned for monitoring access patterns ─────────────
            // Failure alerts & status-over-time filtering.
            $table->index(['status', 'created_at']);
            // Filtered browsing + daily activity summaries by type/category.
            $table->index(['log_type', 'category', 'created_at']);
            // Per-user audit trails.
            $table->index(['user_id', 'created_at']);
            // Per-workspace audit trails.
            $table->index(['workspace_id', 'created_at']);
            // Fast "all failures for this job" lookups (scheduled-job health).
            $table->index(['job_name', 'status', 'created_at']);
            // Category failure counts / dashboards.
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
