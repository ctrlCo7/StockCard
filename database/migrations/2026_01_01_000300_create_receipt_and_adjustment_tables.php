<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->date('date_received');
            $table->string('supplier', 150);
            $table->string('reference_type', 30);
            $table->string('reference_no', 80);
            $table->string('received_by_name', 150);
            $table->text('remarks')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index('date_received');
            $table->index('reference_no');
        });

        Schema::create('stock_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->unsignedInteger('quantity');
            $table->string('unit', 30);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 30)->unique();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->date('adjustment_date');
            $table->string('direction', 10); // increase | decrease
            $table->unsignedInteger('quantity');
            $table->string('reason', 30);
            $table->string('reference_no', 80);
            $table->text('remarks');
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamps();

            $table->index('adjustment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_receipt_items');
        Schema::dropIfExists('stock_receipts');
    }
};
