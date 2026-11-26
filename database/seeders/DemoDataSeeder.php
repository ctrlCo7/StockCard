<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Office;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\RequisitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEMONSTRATION DATA ONLY — not official or actual office records.
 *
 * Every stock movement is posted through InventoryService / RequisitionService, so the
 * demo Stock Cards are produced by exactly the same rules as real postings. The clock
 * is moved day by day so timestamps line up with transaction dates.
 */
class DemoDataSeeder extends Seeder
{
    private InventoryService $inventory;

    private RequisitionService $ris;

    private ?Carbon $base = null;

    public function run(InventoryService $inventory, RequisitionService $ris): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be seeded in production.');
        }
        if (User::where('email', 'admin@demo.local')->exists()) {
            $this->command?->warn('Demo data already present; skipping. Run migrate:fresh --seed to rebuild it.');

            return;
        }
        $this->inventory = $inventory;
        $this->ris = $ris;

        $password = config('app.demo_user_password') ?: Str::password(14, symbols: false);

        // ---- Offices & categories -------------------------------------------------
        $division = 'Large Taxpayers Division – Cebu';
        $supply = Office::create(['code' => 'LTD-SUP', 'name' => 'Supply and Property Unit', 'division' => $division, 'responsibility_center_code' => 'DEMO-RC-100', 'location' => 'Ground Floor']);
        $assess = Office::create(['code' => 'LTD-AS', 'name' => 'Assessment Section', 'division' => $division, 'responsibility_center_code' => 'DEMO-RC-110', 'location' => '2nd Floor']);
        $admin = Office::create(['code' => 'LTD-ADM', 'name' => 'Administrative Section', 'division' => $division, 'responsibility_center_code' => 'DEMO-RC-120', 'location' => '3rd Floor']);
        Office::create(['code' => 'LTD-COL', 'name' => 'Collection Section', 'division' => $division, 'responsibility_center_code' => 'DEMO-RC-130', 'location' => '2nd Floor']);

        $office = InventoryCategory::create(['name' => 'Office Supplies', 'description' => 'Paper, fasteners and general office consumables']);
        $janitorial = InventoryCategory::create(['name' => 'Janitorial Supplies', 'description' => 'Cleaning and sanitation consumables']);
        $it = InventoryCategory::create(['name' => 'IT Consumables', 'description' => 'Toner, ink and computer accessories']);

        // ---- Users ----------------------------------------------------------------
        $roles = Role::pluck('id', 'name');
        $mk = function (string $name, string $email, string $designation, Office $office, array $roleNames) use ($password, $roles) {
            $user = User::create(compact('name', 'email', 'designation', 'password') + ['office_id' => $office->id, 'is_active' => true]);
            $user->roles()->sync(collect($roleNames)->map(fn (RoleEnum $r) => $roles[$r->value]));

            return $user;
        };
        // Both supply-office accounts hold the single Administrator / Inventory Officer role;
        // the demo history simply shows one approving and the other processing.
        $approver = $mk('Administrator', 'admin@demo.local', 'Chief, Administrative Section', $admin, [RoleEnum::Admin]);
        $officer = $mk('Supply Officer', 'officer@demo.local', 'Supply Officer II', $supply, [RoleEnum::Admin]);
        $requester = $mk('Requesting Personnel', 'requester@demo.local', 'Revenue Officer I', $assess, [RoleEnum::Requester]);
        $requester2 = $mk('Administrative Staff', 'staff@demo.local', 'Administrative Aide IV', $admin, [RoleEnum::Requester]);

        // ---- Items with opening balances -----------------------------------------
        $this->at(-60);
        $item = fn (array $a, int $opening) => $this->inventory->createItemWithOpeningBalance(
            $a + ['office_id' => $supply->id, 'location' => 'Supply Room'], $opening, today(), $officer, 'Beginning Inventory (Demo)');

        $freshener = $item(['stock_number' => 'DEMO-JS-001', 'name' => 'Air Freshener', 'description' => 'Aerosol, 280 ml', 'category_id' => $janitorial->id, 'unit' => 'Can', 'reorder_point' => 10], 28);
        $paper = $item(['stock_number' => 'DEMO-OS-001', 'name' => 'A4 Bond Paper', 'description' => 'Multi-purpose, 70 gsm, 500 sheets', 'category_id' => $office->id, 'unit' => 'Ream', 'reorder_point' => 20], 40);
        $toner = $item(['stock_number' => 'DEMO-IT-001', 'name' => 'Canon 337 Toner', 'description' => 'Black toner cartridge for Canon MF series', 'category_id' => $it->id, 'unit' => 'Cartridge', 'reorder_point' => 5], 4);
        $clips = $item(['stock_number' => 'DEMO-OS-002', 'name' => 'Binder Clips', 'description' => '32 mm, 12 pcs per box', 'category_id' => $office->id, 'unit' => 'Box', 'reorder_point' => 5], 12);
        $pens = $item(['stock_number' => 'DEMO-OS-003', 'name' => 'Ballpoint Pen, Black', 'description' => '0.5 mm, 12 pcs per box', 'category_id' => $office->id, 'unit' => 'Box', 'reorder_point' => 4], 10);
        $alcohol = $item(['stock_number' => 'DEMO-JS-002', 'name' => 'Isopropyl Alcohol 70%', 'description' => '500 ml bottle', 'category_id' => $janitorial->id, 'unit' => 'Bottle', 'reorder_point' => 6], 6);
        $folders = $item(['stock_number' => 'DEMO-OS-004', 'name' => 'Folder, Long', 'description' => 'Pressboard, 100 pcs per pack', 'category_id' => $office->id, 'unit' => 'Pack', 'reorder_point' => 2], 0);

        // ---- Stock receipt --------------------------------------------------------
        $this->at(-45);
        $this->inventory->receive([
            'idempotency_key' => (string) Str::uuid(), 'date_received' => today(), 'supplier' => 'Demo Supplier Trading (sample)',
            'reference_type' => 'DR', 'reference_no' => 'DEMO-DR-0101', 'received_by_name' => $officer->name, 'remarks' => 'Demonstration delivery',
        ], [
            ['item_id' => $paper->id, 'quantity' => 50],
            ['item_id' => $clips->id, 'quantity' => 10],
            ['item_id' => $toner->id, 'quantity' => 3],
        ], $officer);

        // ---- Completed requisitions (reproduce the paper Stock Card example: 28 → 24 → 19 → 17 → +15 = 32)
        $this->completedRis(-40, $requester, $assess, 'Demo: supplies for the Assessment Section', [[$freshener, 4], [$paper, 10]], $officer, $approver);
        $this->completedRis(-30, $requester2, $admin, 'Demo: restroom and filing supplies', [[$freshener, 5], [$clips, 3], [$pens, 2]], $officer, $approver);
        $this->completedRis(-20, $requester, $assess, 'Demo: printing of assessment notices', [[$freshener, 2], [$toner, 2], [$paper, 15]], $officer, $approver);

        $this->at(-15);
        $this->inventory->receive([
            'idempotency_key' => (string) Str::uuid(), 'date_received' => today(), 'supplier' => 'Demo Supplier Trading (sample)',
            'reference_type' => 'PO', 'reference_no' => 'DEMO-PO-2026-014', 'received_by_name' => $officer->name, 'remarks' => 'Demonstration replenishment',
        ], [['item_id' => $freshener->id, 'quantity' => 15], ['item_id' => $alcohol->id, 'quantity' => 6]], $officer);

        // ---- A demo adjustment ----------------------------------------------------
        $this->at(-10);
        $this->inventory->adjust([
            'idempotency_key' => (string) Str::uuid(), 'item_id' => $alcohol->id, 'adjustment_date' => today(), 'direction' => 'decrease',
            'quantity' => 1, 'reason' => 'damaged', 'reference_no' => 'DEMO-IR-003', 'remarks' => 'Demo: bottle found leaking during inspection.',
        ], $officer);

        // ---- Partially issued multi-item RIS ---------------------------------------
        $this->at(-5);
        $partial = $this->ris->createDraft($this->header($assess, 'Demo: year-end report preparation'), $this->lines([[$paper, 20], [$toner, 4], [$clips, 2]]), $requester);
        $this->ris->submit($partial, $requester);
        $this->ris->forward($partial->fresh(), $officer, [], 'Toner stock is limited.');
        $this->ris->approve($partial->fresh(), $approver, [], 'Approved as requested.');
        $this->at(-4);
        $lines = $partial->items()->get()->keyBy('item_id');
        $this->ris->issue($partial->fresh(), $officer, [$lines[$paper->id]->id => 20, $lines[$toner->id]->id => 1, $lines[$clips->id]->id => 2], [
            'idempotency_key' => (string) Str::uuid(), 'issue_date' => today(), 'received_by_user_id' => $requester->id,
            'remarks' => 'Partial: remaining toner to follow upon delivery.',
        ]);

        // ---- Pending items for each role's queue -----------------------------------
        $this->at(-2);
        $pending = $this->ris->createDraft($this->header($admin, 'Demo: office supplies for new personnel'), $this->lines([[$pens, 3], [$folders, 1], [$freshener, 2]]), $requester2);
        $this->ris->submit($pending, $requester2);

        $this->at(-1);
        $forApproval = $this->ris->createDraft($this->header($assess, 'Demo: field audit kit'), $this->lines([[$alcohol, 2], [$pens, 1]]), $requester);
        $this->ris->submit($forApproval, $requester);
        $this->ris->forward($forApproval->fresh(), $officer);

        $this->at(0);
        $this->ris->createDraft($this->header($assess, 'Demo: draft request not yet submitted'), $this->lines([[$paper, 5]]), $requester);

        Carbon::setTestNow();

        $this->command?->info('Demo data seeded (clearly labelled DEMO). Accounts: admin@demo.local, officer@demo.local (administrators); requester@demo.local, staff@demo.local (requesters)');
        $this->command?->warn(config('app.demo_user_password')
            ? 'Password: the value of DEMO_USER_PASSWORD in your .env file.'
            : "Generated password for all demo accounts: {$password}  (set DEMO_USER_PASSWORD in .env to choose your own)");
    }

    private function completedRis(int $day, User $requester, Office $office, string $purpose, array $lines, User $officer, User $approver): Requisition
    {
        $this->at($day);
        $r = $this->ris->createDraft($this->header($office, $purpose), $this->lines($lines), $requester);
        $this->ris->submit($r, $requester);
        $this->ris->forward($r->fresh(), $officer);
        $this->ris->approve($r->fresh(), $approver);

        $this->at($day + 1);
        $quantities = $r->items()->get()->mapWithKeys(fn ($l) => [$l->id => $l->quantity_approved])->all();
        $this->ris->issue($r->fresh(), $officer, $quantities, [
            'idempotency_key' => (string) Str::uuid(), 'issue_date' => today(), 'received_by_user_id' => $requester->id,
        ]);
        $this->ris->acknowledgeReceipt($r->fresh(), $requester, 'Received complete and in good condition.');

        return $r;
    }

    private function header(Office $office, string $purpose): array
    {
        return ['office_id' => $office->id, 'requisition_date' => today(), 'purpose' => $purpose, 'fund_cluster' => '01'];
    }

    /** @param  array<int, array{0: InventoryItem, 1: int}>  $lines */
    private function lines(array $lines): array
    {
        return array_map(fn ($l) => ['item_id' => $l[0]->id, 'quantity' => $l[1]], $lines);
    }

    private function at(int $dayOffset): void
    {
        $this->base ??= Carbon::today();
        Carbon::setTestNow($this->base->copy()->addDays($dayOffset)->setTime(9, 0)->addMinutes(random_int(0, 360)));
    }
}
