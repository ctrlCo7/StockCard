<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Inventory Officer and Approving Officer roles are merged into the Administrator
 * role. Users holding either role are given the admin role, then the old roles are removed.
 */
return new class extends Migration
{
    private const MERGED = ['inventory_officer', 'approver'];

    public function up(): void
    {
        $oldRoleIds = DB::table('roles')->whereIn('name', self::MERGED)->pluck('id');
        if ($oldRoleIds->isEmpty()) {
            return;
        }

        $adminId = DB::table('roles')->where('name', 'admin')->value('id')
            ?? DB::table('roles')->insertGetId([
                'name' => 'admin', 'label' => 'Administrator / Inventory Officer',
                'created_at' => now(), 'updated_at' => now(),
            ]);

        $userIds = DB::table('role_user')->whereIn('role_id', $oldRoleIds)->distinct()->pluck('user_id');
        foreach ($userIds as $userId) {
            DB::table('role_user')->insertOrIgnore(['role_id' => $adminId, 'user_id' => $userId]);
        }

        DB::table('role_user')->whereIn('role_id', $oldRoleIds)->delete();
        DB::table('roles')->whereIn('id', $oldRoleIds)->delete();
        DB::table('roles')->where('id', $adminId)->update(['label' => 'Administrator / Inventory Officer']);
    }

    public function down(): void
    {
        // Role assignments cannot be split back automatically; the old roles are not restored.
    }
};
