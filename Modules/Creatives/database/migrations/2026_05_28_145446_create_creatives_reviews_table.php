<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creatives_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creative_id');
            $table->enum('status', [
                'waiting_for_submission',
                'for_approval',
                'revision',
                'approved',
                'for_reapproval',
            ])->default('waiting_for_submission');
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->foreign('creative_id')->references('id')->on('creatives')->cascadeOnDelete();

            $table->index('creative_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creatives_reviews');
    }
};
