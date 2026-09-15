<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_sku_unique');
            $table->unique(['company_id', 'sku'], 'products_company_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $duplicateSku = DB::table('products')
            ->select('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('sku')
            ->value('sku');

        if ($duplicateSku !== null) {
            throw new RuntimeException("Cannot restore global product SKU uniqueness: SKU [{$duplicateSku}] is duplicated across products or companies.");
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_company_sku_unique');
            $table->unique('sku', 'products_sku_unique');
        });
    }
};
