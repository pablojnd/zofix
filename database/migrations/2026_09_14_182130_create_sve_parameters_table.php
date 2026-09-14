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
        Schema::create('sve_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies');
            $table->enum('environment', ['qa', 'prod'])->default('qa');
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable()->comment('Refresh token for the Sve max duration of 20 minutes');
            $table->timestamps();
        });

        // Unidades de Medida
        Schema::create('sve_unit_of_measurements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignUlid('sve_unit_of_measurement_id')->nullable()->constrained('sve_unit_of_measurements')->restrictOnDelete()->comment('Parent unit of measurement');
            $table->string('unit_name', 100);
            $table->string('unit_sigla', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Codigo Arancelario
        Schema::create('sve_tariff_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignUlid('sve_unit_of_measurement_id')->nullable()->constrained('sve_unit_of_measurements')->restrictOnDelete();
            $table->string('print_label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sve_tariff_codes');
        Schema::dropIfExists('sve_unit_of_measurements');
        Schema::dropIfExists('sve_tokens');
    }
};
