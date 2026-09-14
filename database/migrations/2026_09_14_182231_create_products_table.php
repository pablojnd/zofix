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
        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->foreignUlid('warehouse_id')->constrained('warehouses');
            $table->foreignUlid('brand_id')->constrained('brands');
            $table->foreignUlid('provider_id')->nullable()->constrained('providers');
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('sku_provider')->nullable();
            $table->string('barcode')->nullable();
            $table->integer('price')->default(0)->comment('Price in CLP');
            $table->decimal('packing', 12, 2)->default(0);
            $table->decimal('weight', 12, 2)->default(0)->comment('Weight in KG');
            $table->decimal('height', 12, 2)->default(0)->comment('Height in CM');
            $table->decimal('width', 12, 2)->default(0)->comment('Width in CM');
            $table->decimal('length', 12, 2)->default(0)->comment('Length in CM');
            $table->string('sale_unit')->default('unidad')->comment('unidad|m2|m3|pie|ml');
            $table->string('status')->default('inactive')->comment('active|inactive|discontinued');
            $table->string('images')->nullable();
            $table->foreignUlid('sve_unit_of_measurement_id')->nullable()->constrained('sve_unit_of_measurements')->nullOnDelete()->comment('Parent unit of measurement');
            $table->foreignUlid('sve_tariff_code_id')->nullable()->constrained('sve_tariff_code')->nullOnDelete()->comment('Tariff code for the product');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'warehouse_id'], 'products_company_warehouse_index');
        });

        Schema::create('product_category', function (Blueprint $table): void {
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'warehouse_id', 'product_id', 'category_id']);
            $table->index(['company_id', 'warehouse_id', 'category_id']);
        });

        Schema::create('attribute_product', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignUlid('attribute_value_id')->constrained('attribute_values')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
