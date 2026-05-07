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
    Schema::create('blog_posts', function (Blueprint $table) {
        $table->id();
        
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('excerpt');
        $table->longText('content');
        
        $table->enum('status', ['draft', 'published'])->default('draft');
        $table->timestamp('published_at')->nullable();
        
        $table->string('seo_title')->nullable();
        $table->text('seo_description')->nullable();
        
        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
