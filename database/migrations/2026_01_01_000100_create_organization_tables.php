<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles, offices, categories, application settings and document numbering.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('label', 100);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('division', 150)->nullable();
            $table->string('responsibility_center_code', 50)->nullable();
            $table->string('location', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('designation', 150)->nullable()->after('email');
            $table->foreignId('office_id')->nullable()->after('designation')->constrained('offices')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('office_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Gap-free, lock-protected counters used for RIS, receipt, issuance and adjustment numbers.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('application_settings');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('role_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn(['designation', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('offices');
        Schema::dropIfExists('roles');
    }
};
