<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('include_tables')->nullable()->after('qdrant_collection');
            $table->json('exclude_tables')->nullable()->after('include_tables');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['include_tables', 'exclude_tables']);
        });
    }
};
