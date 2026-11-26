<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Issuance;
use App\Models\Requisition;
use App\Models\StockAdjustment;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Exercises every screen and export against the full demonstration dataset. */
class ReportsAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.demo_user_password' => 'password']);
        $this->seed(DemoDataSeeder::class);
        $this->admin = User::where('email', 'admin@demo.local')->firstOrFail();
    }

    public function test_every_main_page_renders_for_admin(): void
    {
        $item = InventoryItem::where('stock_number', 'DEMO-JS-001')->firstOrFail();
        $r = Requisition::where('status', 'partially_issued')->firstOrFail();
        $receipt = StockReceipt::firstOrFail();

        $urls = [
            route('dashboard'), route('dashboard.trend'), route('search', ['q' => 'paper']),
            route('items.index'), route('items.create'), route('items.show', $item), route('items.edit', $item), route('items.lookup', ['q' => 'air']),
            route('stock-cards.index'), route('stock-cards.show', $item), route('stock-cards.show', [$item, 'from' => today()->subDays(25)->toDateString()]),
            route('stock-cards.print', $item),
            route('receipts.index'), route('receipts.create'), route('receipts.show', $receipt),
            route('adjustments.index'), route('adjustments.create'), route('adjustments.show', StockAdjustment::firstOrFail()),
            route('requisitions.index'), route('requisitions.index', ['scope' => 'queue']),
            route('requisitions.show', $r), route('requisitions.issue', $r), route('requisitions.print', $r),
            route('issuances.index'), route('issuances.show', Issuance::firstOrFail()),
            route('reports.index'), route('users.index'), route('users.create'), route('users.edit', $this->admin),
            route('audit-logs.index'), route('audit-logs.show', AuditLog::firstOrFail()),
            route('settings.edit'), route('categories.index'), route('offices.index'), route('profile.edit'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_every_report_renders_in_every_format(): void
    {
        $item = InventoryItem::where('stock_number', 'DEMO-JS-001')->firstOrFail();

        foreach (array_keys(ReportService::REPORTS) as $key) {
            $params = [$key, 'item' => $key === 'stock-card' ? $item->id : null];
            $this->actingAs($this->admin)->get(route('reports.show', $params))->assertOk();
            $this->actingAs($this->admin)->get(route('reports.show', $params + ['format' => 'print']))->assertOk();
            $this->actingAs($this->admin)->get(route('reports.show', $params + ['format' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }
        // PDF rendering is slower; check a representative pair.
        $this->actingAs($this->admin)->get(route('reports.show', ['current-inventory', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->admin)->get(route('reports.show', ['ris-register', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_stock_card_print_and_pdf_contain_correct_data(): void
    {
        $item = InventoryItem::where('stock_number', 'DEMO-JS-001')->firstOrFail();

        $html = $this->actingAs($this->admin)->get(route('stock-cards.print', $item))->assertOk()->getContent();
        $firstRis = Requisition::orderBy('id')->value('ris_no');
        foreach (['STOCK CARD', 'Air Freshener', 'DEMO-JS-001', 'Re-order Point', 'From Whom Received', 'Beginning Inventory (Demo)', $firstRis] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // Running balances from the paper example, in order.
        $this->assertMatchesRegularExpression('/>\s*28\s*<.*>\s*24\s*<.*>\s*19\s*<.*>\s*17\s*<.*>\s*32\s*</s', $html);

        $pdf = $this->actingAs($this->admin)->get(route('stock-cards.pdf', $item));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_ris_print_and_pdf_contain_form_sections(): void
    {
        $r = Requisition::where('status', 'completed')->firstOrFail();

        $html = $this->actingAs($this->admin)->get(route('requisitions.print', $r))->assertOk()->getContent();
        foreach (['REQUISITION AND ISSUE SLIP', $r->ris_no, 'Bureau of Internal Revenue', 'Large Taxpayers Division', 'Stock Available?', 'Purpose:', 'Requested by:', 'Approved by:', 'Issued by:', 'Received by:'] as $text) {
            $this->assertStringContainsString(e($text), $html);
        }

        $pdf = $this->actingAs($this->admin)->get(route('requisitions.pdf', $r))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_csv_export_contains_report_rows(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('reports.show', ['low-stock', 'format' => 'csv']))->assertOk()->streamedContent();

        $this->assertStringContainsString('Low-Stock Report', $csv);
        $this->assertStringContainsString('Canon 337 Toner', $csv);
        $this->assertStringNotContainsString('A4 Bond Paper', $csv);
    }

    public function test_dashboard_figures_come_from_the_database(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $active = InventoryItem::active()->count();
        $low = InventoryItem::active()->lowStock()->count();

        $response->assertSeeInOrder([(string) $active, 'Active inventory items'], false);
        $response->assertSeeInOrder([(string) $low, 'Items at / below reorder point'], false);
    }

    public function test_requester_dashboard_hides_stock_ledger(): void
    {
        $requester = User::where('email', 'requester@demo.local')->firstOrFail();
        $this->actingAs($requester)->get(route('dashboard'))->assertOk()->assertDontSee('Recent transactions');
        $this->actingAs($requester)->get(route('dashboard.trend'))->assertForbidden();
        $this->actingAs($requester)->get(route('requisitions.create'))->assertOk()->assertSee('New Requisition and Issue Slip');
    }

    public function test_admin_dashboard_lists_requisitions_awaiting_action(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Requisitions awaiting action')
            ->assertDontSee('My recent requisitions')
            ->assertDontSee(route('requisitions.create'));
    }
}
