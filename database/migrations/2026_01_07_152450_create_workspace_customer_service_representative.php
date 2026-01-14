<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_customer_service_representative', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('customer_service_representative_id');

            $table->primary([
                'page_id',
                'customer_service_representative_id',
            ]);

            $table->foreign('page_id', 'p_csr_workspace_fk')
                ->references('id')
                ->on('pages')
                ->onDelete('cascade');

            $table->foreign('customer_service_representative_id', 'p_csr_csr_fk')
                ->references('id')
                ->on('customer_service_representatives')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_customer_service_representative');
    }
};
