<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('unit', 30);
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('initial_quantity')->default(0);
            // Cached balance. Written only by InventoryService while the row is locked;
            // the stock_transactions ledger is the authoritative source.
            $table->unsignedInteger('current_balance')->default(0);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('status');
            $table->index('unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
