<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_period')->default('monthly'); // monthly, yearly, lifetime
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_branches')->default(1);
            $table->json('features')->nullable(); // capability keys enabled by default
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('status')->default('active'); // active, expired, cancelled, trial
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // Per-store capability overrides set by the super admin.
        Schema::create('store_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('capability');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['store_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_capabilities');
        Schema::dropIfExists('store_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
