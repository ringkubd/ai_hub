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
        Schema::create('api_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->integer('rate_limit_per_day')->nullable();
            $table->integer('rate_limit_per_month')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->json('features')->nullable(); // Array of enabled features/capabilities
            $table->json('allowed_endpoints')->nullable(); // Specific endpoints allowed
            $table->boolean('is_active')->default(true);
            $table->integer('max_api_keys')->default(1); // How many keys can be created
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_packages');
    }
};
