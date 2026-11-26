<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('city', 120);
            $table->string('category', 120)->nullable();
            $table->text('description')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('linkedin_url', 2048)->nullable();
            $table->string('google_business_url', 2048)->nullable();
            $table->json('other_urls')->nullable();
            // draft | researching | ready
            $table->string('status', 20)->default('draft');
            // idle | pending | processing | completed | failed | unavailable
            $table->string('research_status', 20)->default('idle');
            $table->text('research_error')->nullable();
            $table->timestamp('researched_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
