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
        Schema::create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique indexes so soft-deleted rows do not block re-registration (Postgres/SQLite).
        DB::statement('create unique index companies_slug_unique on companies (slug) where deleted_at is null');

        Schema::create('warehouses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies');
            $table->string('name');
            $table->string('slug');
            $table->string('code');
            $table->string('email_contact')->nullable();
            $table->string('phone_contact')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tenant-scoped uniqueness; warehouse identifiers only need to be unique within a company.
        DB::statement('create unique index warehouses_company_slug_unique on warehouses (company_id, slug) where deleted_at is null');
        DB::statement('create unique index warehouses_company_code_unique on warehouses (company_id, code) where deleted_at is null');

        Schema::create('sve_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies');
            $table->string('username');
            $table->text('password')->nullable();
            $table->text('password_qa')->nullable();
            $table->string('company_rut')->nullable();
            $table->string('company_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('create unique index sve_credentials_username_unique on sve_credentials (username) where deleted_at is null');

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
        Schema::dropIfExists('webpay_credentials');
        Schema::dropIfExists('sve_credentials');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('companies');
    }
};
