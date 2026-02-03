<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // 'created', 'updated', 'deleted', 'viewed', 'exported'
            $table->nullableMorphs('subject'); // Polymorphic relation to the affected model
            $table->json('properties')->nullable(); // Additional data about the activity
            $table->timestamps();

            $table->index('action');  // "Show me all deletions"
            $table->index('created_at');// "Activity in the last 7 days"
            $table->index(['user_id', 'created_at']);  // "This user's recent activity"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

