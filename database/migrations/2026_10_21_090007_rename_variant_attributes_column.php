<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_variants', 'attributes') && ! Schema::hasColumn('product_variants', 'variant_attributes')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->renameColumn('attributes', 'variant_attributes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'variant_attributes')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->renameColumn('variant_attributes', 'attributes');
            });
        }
    }
};
