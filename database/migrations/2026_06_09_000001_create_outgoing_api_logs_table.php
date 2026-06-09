<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 64);
            $table->string('action', 128);
            $table->string('http_method', 10);
            $table->text('url');
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['service', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_api_logs');
    }
};
