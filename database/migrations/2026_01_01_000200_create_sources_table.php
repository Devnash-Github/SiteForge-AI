<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // text | pdf | website | social | google_business | image | search_result
            $table->string('type', 30);
            $table->string('label', 255)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('file_path', 1024)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->longText('extracted_content')->nullable();
            // pending | processing | completed | failed | blocked
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            // null (not applicable) | matched | uncertain | confirmed | rejected
            $table->string('identity_status', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
