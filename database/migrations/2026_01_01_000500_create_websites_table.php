<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 120)->unique();
            $table->json('theme');
            // draft | published | unpublished
            $table->string('status', 20)->default('draft');
            // idle | pending | processing | completed | failed
            $table->string('generation_status', 20)->default('idle');
            $table->text('generation_error')->nullable();
            $table->string('generation_mode', 20)->nullable(); // ai | basic
            $table->json('generation_preferences')->nullable();
            // Snapshot of theme + sections served on the public URL.
            $table->json('published_content')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('content_updated_at')->nullable();
            // Incremented on every content change; compared with published_version.
            $table->unsignedInteger('content_version')->default(0);
            $table->unsignedInteger('published_version')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        Schema::create('website_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->json('content');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['website_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_sections');
        Schema::dropIfExists('websites');
    }
};
