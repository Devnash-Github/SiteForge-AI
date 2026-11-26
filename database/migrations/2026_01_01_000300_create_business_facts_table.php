<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            // Denormalised so a fact keeps its provenance even if the source is removed.
            $table->string('source_type', 30)->default('manual');
            $table->string('key', 40);
            $table->text('value');
            $table->string('normalized_value', 512);
            // extracted | needs_review | confirmed | rejected
            $table->string('status', 20)->default('extracted');
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'key', 'status']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_facts');
    }
};
