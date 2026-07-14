<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_interns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('full_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('username')->nullable();
            $table->string('contact_number_email')->nullable();

            $table->timestamps();

            // Supports the workspace-scoped listing plus the company filter/sort.
            $table->index(['workspace_id', 'company_name']);
            $table->index(['workspace_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_interns');
    }
};
