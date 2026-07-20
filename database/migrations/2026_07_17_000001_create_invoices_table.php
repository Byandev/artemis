<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();

            // Snapshot of who is being billed (frozen at issue time).
            $table->string('bill_to_name');
            $table->string('bill_to_email')->nullable();
            $table->text('bill_to_address')->nullable();

            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('PHP');

            // Array of { description, quantity, unit_price, amount }.
            $table->json('line_items');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0); // percent, e.g. 12.00 for VAT
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->string('status')->default('draft'); // draft | sent | paid
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
