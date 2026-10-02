<?php

use App\Models\Order as OrderRecord;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Modules\Pancake\Actions\SyncParcelTrackingAction;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['owner_id' => User::factory()->create()->id]);
    $this->page = Page::factory()->forWorkspace($this->workspace)->create();
    $this->shop = Shop::factory()->forWorkspace($this->workspace)->create();
    $this->order = Order::find(OrderRecord::factory()->forPage($this->page)->create([
        'workspace_id' => $this->workspace->id,
        'shop_id' => $this->shop->id,
    ])->id);
    $this->sync = fn () => app(SyncParcelTrackingAction::class)->execute($this->order, [
        'partner' => [
            'extend_code' => 'JT0001',
            'partner_status' => 'on_delivery',
            'extend_update' => [[
                'status' => 'On Delivery',
                'note' => '【Hub】【Juan Dela Cruz : +639171234567】',
                'updated_at' => now()->toDateTimeString(),
            ]],
        ],
    ], $this->page, $this->workspace);
});

test('a freshly fetched RMO row starts on the rider PENDING status', function () {
    ($this->sync)();

    $row = OrderForDelivery::where('order_id', $this->order->id)->sole();

    expect($row->status)->toBe('PENDING')
        ->and($row->rider_status_id)->toBe($this->workspace->rmoRiderStatuses()->where('name', 'PENDING')->value('id'))
        ->and($row->cx_status_id)->toBeNull();
});

test('a later fetch keeps the rider status a CSR picked', function () {
    ($this->sync)();
    $row = OrderForDelivery::where('order_id', $this->order->id)->sole();
    $otw = $this->workspace->rmoRiderStatuses()->where('name', 'RIDER OTW')->value('id');
    $row->update(['rider_status_id' => $otw]);

    ($this->sync)();

    expect($row->fresh()->rider_status_id)->toBe($otw);
});

test('a workspace without a rider PENDING status fetches rows with none', function () {
    $this->workspace->rmoRiderStatuses()->where('name', 'PENDING')->delete();

    ($this->sync)();

    expect(OrderForDelivery::where('order_id', $this->order->id)->sole()->rider_status_id)->toBeNull();
});
