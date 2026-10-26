<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('currency', 8)->default('BDT');
            $table->string('currency_symbol', 8)->default('৳');
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->string('locale', 5)->default('bn');
            $table->string('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('active'); // active, suspended, trial
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
