<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Gencys' own row id — the upsert key for the n8n sync.
            $table->unsignedBigInteger('page_id')->nullable();

            $table->dateTime('date_created')->nullable();
            $table->string('name')->nullable();
            $table->string('owner')->nullable();
            $table->string('intern_and_brand')->nullable();

            // Best-effort link to the intern named in `intern_and_brand`. Stays
            // null when the ERP text matches no known intern.
            $table->foreignId('gencys_intern_id')->nullable()
                ->constrained('gencys_interns')->nullOnDelete();
            $table->string('status')->nullable();
            $table->string('platform')->nullable();

            $table->timestamps();

            $table->unique(['workspace_id', 'page_id']);
            $table->index(['workspace_id', 'date_created']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_pages');
    }
};
