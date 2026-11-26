<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issued supplies go to a requesting-personnel account; the name is still kept as a
     * snapshot for printing. Older issuances keep only the typed name.
     */
    public function up(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->foreignId('received_by_user_id')->nullable()->after('received_by_name')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_user_id');
        });
    }
};
