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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('api_package_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name'); // Friendly name for the key
            $table->string('key')->unique(); // The actual API key (hashed)
            $table->string('prefix', 10); // Visible prefix (e.g., "sk_live_")
            $table->text('description')->nullable();
            $table->json('capabilities')->nullable(); // Specific capabilities/scopes
            $table->json('metadata')->nullable(); // Additional custom data
            $table->integer('rate_limit_override')->nullable(); // Override package rate limit
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->ipAddress('allowed_ips')->nullable(); // IP whitelist
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
