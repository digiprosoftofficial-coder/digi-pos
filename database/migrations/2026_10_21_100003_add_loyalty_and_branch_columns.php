<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('loyalty_points')->default(0)->after('due_balance');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->integer('points_earned')->default(0)->after('due');
            $table->integer('points_redeemed')->default(0)->after('points_earned');
            $table->decimal('returned_total', 12, 2)->default(0)->after('points_redeemed');
        });

        // Branch-aware stock ledger.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('store_id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['points_earned', 'points_redeemed', 'returned_total']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('loyalty_points');
        });
    }
};
