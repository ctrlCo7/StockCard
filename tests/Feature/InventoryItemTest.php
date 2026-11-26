<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Exceptions\InventoryException;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'stock_number' => 'OS-0001', 'name' => 'A4 Bond Paper', 'description' => '70 gsm', 'unit' => 'Ream',
            'reorder_point' => 20, 'opening_quantity' => 28, 'opening_date' => today()->toDateString(),
        ];
    }

    public function test_item_is_created_with_unique_stock_number_and_opening_balance(): void
    {
        $this->actingAs($this->admin())->post(route('items.store'), $this->payload())->assertRedirect();

        $item = InventoryItem::where('stock_number', 'OS-0001')->firstOrFail();
        $this->assertSame(28, $item->current_balance);
        $this->assertSame(28, $item->initial_quantity);

        $opening = $item->transactions()->sole();
        $this->assertSame(TransactionType::Opening, $opening->type);
        $this->assertSame(28, $opening->quantity_received);
        $this->assertSame(28, $opening->balance_after);
    }

    public function test_duplicate_stock_number_is_rejected(): void
    {
        $officer = $this->admin();
        $this->actingAs($officer)->post(route('items.store'), $this->payload());
        $this->actingAs($officer)->post(route('items.store'), $this->payload(['name' => 'Another']))
            ->assertSessionHasErrors('stock_number');

        $this->assertSame(1, InventoryItem::where('stock_number', 'OS-0001')->count());
    }

    public function test_balance_cannot_be_edited_through_item_update(): void
    {
        $item = $this->makeItem(10);

        $this->actingAs($this->admin())->put(route('items.update', $item), [
            'stock_number' => $item->stock_number, 'name' => 'Renamed', 'unit' => 'Piece', 'reorder_point' => 3,
            'current_balance' => 999,
        ])->assertRedirect();
        $this->assertSame(10, $item->fresh()->current_balance);
        $this->assertSame('Renamed', $item->fresh()->name);

        $this->actingAs($this->admin())->put(route('items.update', $item), [
            'stock_number' => $item->stock_number, 'name' => 'Renamed', 'unit' => 'Piece', 'reorder_point' => 3,
            'opening_quantity' => 500,
        ])->assertSessionHasErrors('opening_quantity');
        $this->assertSame(10, $item->fresh()->current_balance);
    }

    public function test_archiving_keeps_history_and_blocks_new_movements(): void
    {
        $item = $this->makeItem(10);
        $this->actingAs($this->admin())->patch(route('items.archive', $item))->assertRedirect();

        $item->refresh();
        $this->assertSame(InventoryItem::STATUS_INACTIVE, $item->status);
        $this->assertSame(1, $item->transactions()->count());

        $this->expectException(InventoryException::class);
        $this->receive($item, 5);
    }

    public function test_search_filter_and_pagination(): void
    {
        $this->makeItem(0, ['name' => 'Canon 337 Toner', 'stock_number' => 'IT-337', 'unit' => 'Cartridge']);
        $this->makeItem(50, ['name' => 'Binder Clips', 'stock_number' => 'OS-BC', 'unit' => 'Box', 'reorder_point' => 5]);
        $this->makeItem(3, ['name' => 'Air Freshener', 'stock_number' => 'JS-AF', 'unit' => 'Can', 'reorder_point' => 10]);
        foreach (range(1, 20) as $i) {
            $this->makeItem(100, ['name' => "Filler {$i}"]);
        }
        $user = $this->admin();

        $this->actingAs($user)->get(route('items.index', ['q' => 'IT-337']))
            ->assertOk()->assertSee('Canon 337 Toner')->assertDontSee('Binder Clips');

        $this->actingAs($user)->get(route('items.index', ['unit' => 'Box']))
            ->assertOk()->assertSee('Binder Clips')->assertDontSee('Air Freshener');

        $this->actingAs($user)->get(route('items.index', ['stock' => 'low']))
            ->assertOk()->assertSee('Air Freshener')->assertDontSee('Canon 337 Toner')->assertDontSee('Binder Clips');

        $this->actingAs($user)->get(route('items.index', ['stock' => 'out']))
            ->assertOk()->assertSee('Canon 337 Toner')->assertDontSee('Air Freshener');

        $page1 = $this->actingAs($user)->get(route('items.index', ['sort' => 'name']))->assertOk();
        $page1->assertSee('Showing 1–15 of 23 items', false);
        $this->actingAs($user)->get(route('items.index', ['sort' => 'name', 'page' => 2]))
            ->assertOk()->assertSee('Showing 16–23 of 23 items', false);
    }

    public function test_reorder_alert_uses_configured_reorder_point(): void
    {
        $item = $this->makeItem(12, ['reorder_point' => 10]);
        $this->assertFalse($item->isLowStock());

        $item->update(['reorder_point' => 12]);
        $this->assertTrue($item->fresh()->isLowStock());
        $this->assertSame(1, InventoryItem::lowStock()->count());

        $item->update(['reorder_point' => 11]);
        $this->assertFalse($item->fresh()->isLowStock());
        $this->assertSame(0, InventoryItem::lowStock()->count());

        $this->actingAs($this->admin())->get(route('stock-cards.show', $item))->assertOk()->assertDontSee('Re-order point reached');
        $item->update(['reorder_point' => 15]);
        $this->actingAs($this->admin())->get(route('stock-cards.show', $item))->assertOk()->assertSee('Re-order point reached');
    }
}
