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
        Schema::create('providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->foreignUlid('warehouse_id')->constrained('warehouses');
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->foreignUlid('warehouse_id')->constrained('warehouses');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->foreignUlid('warehouse_id')->constrained('warehouses');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->foreignUlid('warehouse_id')->constrained('warehouses');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->foreignUlid('warehouse_id')->constrained('warehouses');
            $table->foreignUlid('attribute_id')->constrained();
            $table->string('value');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->unique(['attribute_id', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
