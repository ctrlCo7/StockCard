<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single authoritative inventory ledger. Every Stock Card row is one record here.
     * Rows are append-only: corrections are made with reversal or adjustment rows.
     */
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->string('type', 20); // opening | receipt | issue | adjustment | reversal
            $table->date('transaction_date');
            $table->string('reference_no', 80);
            $table->string('party', 150)->nullable(); // From whom received / to whom issued
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->unsignedInteger('balance_after');

            $table->foreignId('stock_receipt_id')->nullable()->constrained('stock_receipts');
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions');
            $table->foreignId('issuance_item_id')->nullable()->constrained('issuance_items');
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments');
            // A transaction can be reversed at most once.
            $table->foreignId('reverses_transaction_id')->nullable()->unique()->constrained('stock_transactions');

            $table->text('remarks')->nullable();
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['item_id', 'id']);
            $table->index(['item_id', 'transaction_date']);
            $table->index('type');
            $table->index('reference_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
