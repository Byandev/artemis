<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_billing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            // The "bill to" party for this workspace. Nullable because a
            // workspace exists long before anyone fills its billing details in.
            $table->string('billing_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_billing_details');
    }
};
