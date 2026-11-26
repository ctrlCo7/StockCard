<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('ris_no', 30)->unique();
            $table->string('entity_name', 150);
            $table->string('fund_cluster', 50)->nullable();
            $table->string('division', 150)->nullable();
            $table->foreignId('office_id')->constrained('offices');
            $table->string('responsibility_center_code', 50)->nullable();
            $table->date('requisition_date');
            $table->text('purpose');
            $table->string('status', 30)->default('draft');

            // Signatories. Names/designations are snapshotted so the printed slip stays
            // accurate even if a user's profile changes later.
            $table->foreignId('requested_by')->constrained('users');
            $table->string('requested_by_designation', 150)->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('approved_by_designation', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();

            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->string('issued_by_designation', 150)->nullable();
            $table->timestamp('issued_at')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->string('received_by_designation', 150)->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('requisition_date');
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->nullable();
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->boolean('stock_available')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['requisition_id', 'item_id']);
        });

        // Workflow history: every submission, approval, rejection, issuance, etc.
        Schema::create('requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('issuances', function (Blueprint $table) {
            $table->id();
            $table->string('issuance_no', 30)->unique();
            $table->foreignId('requisition_id')->constrained();
            $table->date('issue_date');
            $table->foreignId('issued_by')->constrained('users');
            $table->string('received_by_name', 150);
            $table->text('remarks')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();

            $table->index('issue_date');
        });

        Schema::create('issuance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_item_id')->constrained();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_items');
        Schema::dropIfExists('issuances');
        Schema::dropIfExists('requisition_approvals');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
    }
};
