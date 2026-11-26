<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Electronic signatures: each user keeps one signature image on file, and every RIS
     * keeps its own copy of the image applied at each signatory step, so later changes
     * to a user's signature never alter documents already signed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('designation');
            $table->timestamp('signature_updated_at')->nullable()->after('signature_path');
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('requested_signature_path')->nullable()->after('requested_by_designation');
            $table->string('approved_signature_path')->nullable()->after('approved_by_designation');
            $table->string('issued_signature_path')->nullable()->after('issued_by_designation');
            $table->string('received_signature_path')->nullable()->after('received_by_designation');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['requested_signature_path', 'approved_signature_path', 'issued_signature_path', 'received_signature_path']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'signature_updated_at']);
        });
    }
};
