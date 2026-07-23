<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user (per-intern) monthly income statement. Revenue is the intern's
        // delivered gencys orders (broken down by product); expenses are the
        // finance transactions charged to that intern's user. Explicit short
        // index/FK names — the long table name would overflow MySQL's 64-char limit.
        Schema::create('finance_user_income_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // The parent monthly workspace income statement (same workspace + month).
            $table->unsignedBigInteger('income_statement_id')->nullable();
            $table->foreign('income_statement_id', 'fuis_parent_fk')
                ->references('id')->on('finance_income_statements')->nullOnDelete();

            // The intern this statement is for; user_id + name are snapshotted so the
            // statement stays readable/attributable even if the intern link changes.
            $table->unsignedBigInteger('gencys_intern_id');
            $table->foreign('gencys_intern_id', 'fuis_intern_fk')
                ->references('id')->on('gencys_interns')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('intern_name');

            $table->date('period_month');

            // Snapshot totals, frozen at generate time.
            $table->decimal('total_delivered', 15, 2)->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->decimal('total_cogs', 15, 2)->default(0);
            $table->decimal('total_shipping', 15, 2)->default(0);
            $table->decimal('total_cod', 15, 2)->default(0);
            $table->decimal('total_vat', 15, 2)->default(0);
            $table->decimal('total_tagged', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('total_opex', 15, 2)->default(0);
            $table->decimal('advisory_rate', 6, 4)->default(0.30);
            $table->decimal('advisory_share', 15, 2)->default(0);
            $table->decimal('cod_fee_rate', 6, 4)->default(0.02);
            $table->decimal('vat_rate', 6, 4)->default(0.12);
            $table->decimal('net_profit', 15, 2)->default(0);

            $table->string('status')->default('final');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'gencys_intern_id', 'period_month'], 'fuis_ws_intern_month_uq');
            $table->index('income_statement_id', 'fuis_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_user_income_statements');
    }
};
