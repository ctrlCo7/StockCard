<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportSqliteDataTest extends TestCase
{
    use RefreshDatabase;

    private string $sourcePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourcePath = tempnam(sys_get_temp_dir(), 'stockcard-import-').'.sqlite';
        touch($this->sourcePath);
        config(['database.connections.import_source' => ['driver' => 'sqlite', 'database' => $this->sourcePath, 'prefix' => '', 'foreign_key_constraints' => true]]);
        Artisan::call('migrate', ['--database' => 'import_source', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('import_source');
        DB::purge('sqlite_import');
        @unlink($this->sourcePath);
        parent::tearDown();
    }

    private function seedSource(): void
    {
        $source = DB::connection('import_source');
        $source->table('roles')->insert(['id' => 7, 'name' => 'admin', 'label' => 'Administrator / Inventory Officer']);
        $source->table('users')->insert(['id' => 42, 'name' => 'Imported Admin', 'email' => 'imported@example.com', 'password' => bcrypt('secret-pass-1'), 'is_active' => 1]);
        $source->table('role_user')->insert(['role_id' => 7, 'user_id' => 42]);
        $source->table('inventory_items')->insert(['id' => 5, 'stock_number' => 'IMP-001', 'name' => 'Imported Paper', 'unit' => 'Ream', 'reorder_point' => 3, 'initial_quantity' => 9, 'current_balance' => 9, 'status' => 'active']);
        $source->table('sessions')->insert(['id' => 'stale-session', 'payload' => 'x', 'last_activity' => 1]);
    }

    public function test_records_are_copied_with_their_ids_and_relationships(): void
    {
        $this->seedSource();
        DB::table('users')->delete();

        $this->artisan('app:import-sqlite', ['path' => $this->sourcePath])->assertSuccessful();

        $user = User::with('roles')->findOrFail(42);
        $this->assertSame('Imported Admin', $user->name);
        $this->assertTrue($user->isAdmin());
        $this->assertSame(9, InventoryItem::where('stock_number', 'IMP-001')->value('current_balance'));
        $this->assertSame(['admin'], DB::table('roles')->pluck('name')->all(), 'target roles are replaced by the source roles');
        $this->assertDatabaseMissing('sessions', ['id' => 'stale-session']);
    }

    public function test_import_refuses_to_overwrite_a_database_that_already_has_users(): void
    {
        $this->seedSource();
        $existing = $this->admin();

        $this->artisan('app:import-sqlite', ['path' => $this->sourcePath])
            ->expectsOutputToContain('already has users')
            ->assertFailed();

        $this->assertModelExists($existing);
        $this->assertDatabaseMissing('users', ['email' => 'imported@example.com']);
    }
}
