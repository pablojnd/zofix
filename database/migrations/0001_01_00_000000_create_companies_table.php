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
        Schema::create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();
            $table->string('email_contact')->nullable();
            $table->string('phone_contact')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sve_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies');
            $table->string('username')->unique();
            $table->text('password')->nullable();
            $table->text('password_qa')->nullable();
            $table->string('company_rut')->nullable();
            $table->string('company_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webpay_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->string('environment', 20);
            $table->text('commerce_code');
            $table->text('api_key_secret');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('sve_credentials');
        Schema::dropIfExists('webpay_credentials');
    }
};
