<?php

namespace Tests;

use App\Enums\Role as RoleEnum;
use App\Models\InventoryItem;
use App\Models\Office;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RequisitionService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedReferenceData = true;

    protected Office $office;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedReferenceData && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(ReferenceDataSeeder::class);
            $this->office = Office::create(['code' => 'T-OFF', 'name' => 'Test Office', 'division' => 'Test Division']);
        }
    }

    protected function userWithRole(RoleEnum ...$roles): User
    {
        $user = User::factory()->create(['office_id' => $this->office->id, 'designation' => 'Tester']);
        $user->roles()->sync(Role::whereIn('name', array_map(fn ($r) => $r->value, $roles))->pluck('id'));

        return $user->load('roles');
    }

    protected function admin(): User
    {
        return $this->userWithRole(RoleEnum::Admin);
    }

    protected function requester(): User
    {
        return $this->userWithRole(RoleEnum::Requester);
    }

    protected function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    protected function ris(): RequisitionService
    {
        return app(RequisitionService::class);
    }

    protected function makeItem(int $opening = 0, array $attributes = [], ?User $by = null, $date = null): InventoryItem
    {
        static $n = 0;
        $n++;

        return $this->inventory()->createItemWithOpeningBalance(
            $attributes + ['stock_number' => 'T-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'-'.Str::random(4), 'name' => "Test Item {$n}", 'unit' => 'Piece', 'reorder_point' => 5],
            $opening, $date ?? today()->subDays(10), $by ?? $this->admin(),
        );
    }

    protected function receive(InventoryItem $item, int $qty, ?User $by = null, $date = null): void
    {
        $this->inventory()->receive([
            'idempotency_key' => (string) Str::uuid(), 'date_received' => $date ?? today(), 'supplier' => 'Test Supplier',
            'reference_type' => 'DR', 'reference_no' => 'DR-'.Str::random(5), 'received_by_name' => 'Tester',
        ], [['item_id' => $item->id, 'quantity' => $qty]], $by ?? $this->admin());
    }

    /**
     * Create a requisition and move it to Approved.
     *
     * @param  array<int, array{0: InventoryItem, 1: int}>  $lines
     */
    protected function approvedRequisition(array $lines, ?User $requester = null): Requisition
    {
        $requester ??= $this->requester();
        $r = $this->ris()->createDraft(
            ['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing purposes'],
            array_map(fn ($l) => ['item_id' => $l[0]->id, 'quantity' => $l[1]], $lines),
            $requester,
        );
        $this->ris()->submit($r, $requester);
        $this->ris()->forward($r->fresh(), $this->admin());
        $this->ris()->approve($r->fresh(), $this->admin());

        return $r->fresh('items');
    }

    protected function issueMeta(array $extra = []): array
    {
        return $extra + ['idempotency_key' => (string) Str::uuid(), 'issue_date' => today()];
    }
}
