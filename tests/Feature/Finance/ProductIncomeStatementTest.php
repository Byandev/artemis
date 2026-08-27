
test('an order carrying two products splits across both by quantity', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $widget->id]);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC2', 'product_id' => $gadget->id]);

    // One parcel, 800 pesos, 200 of goods — but 1 widget and 3 gadgets inside.
    $order = pis_order(890001, $workspace, 'Anyone', ['price_final' => 800, 'total_cog' => 200], null);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC1', 'quantity' => 1]);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC2', 'quantity' => 3]);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    app(ProductIncomeStatementService::class)->snapshot($statement);

    $row = fn (int $id) => $statement->productStatements()->where('product_id', $id)->first();

    // A quarter of the order is the widget, three quarters the gadget.
    expect((float) $row($widget->id)->delivered_amount)->toBe(200.0)
        ->and((float) $row($widget->id)->total_delivered_cogs)->toBe(50.0)
        ->and((float) $row($gadget->id)->delivered_amount)->toBe(600.0)
        ->and((float) $row($gadget->id)->total_delivered_cogs)->toBe(150.0)
        // The parcel counts once for each product it carries.
        ->and((int) $row($widget->id)->delivered_count)->toBe(1)
        ->and((int) $row($gadget->id)->delivered_count)->toBe(1);

    // Nothing is created or lost splitting the order up.
    $all = $statement->productStatements()->get();
    expect(round($all->sum(fn ($r) => (float) $r->delivered_amount), 2))->toBe(800.0)
        ->and(round($all->sum(fn ($r) => (float) $r->total_delivered_cogs), 2))->toBe(200.0);
});

test('an item with no unit code behind it takes only its share to Unresolved', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $widget->id]);

    // Half the parcel is a mapped widget, half is a sku nobody has mapped.
    $order = pis_order(890010, $workspace, 'Anyone', ['price_final' => 500, 'total_cog' => 100], null);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC1', 'quantity' => 1]);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'NOPE', 'quantity' => 1]);

    // An order with no items at all still has to land somewhere.
    pis_order(890011, $workspace, 'Anyone', ['price_final' => 90, 'total_cog' => 20], null);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    app(ProductIncomeStatementService::class)->snapshot($statement);

    $mapped = $statement->productStatements()->where('product_id', $widget->id)->first();
    $unresolved = $statement->productStatements()->whereNull('product_id')->first();

    expect((float) $mapped->delivered_amount)->toBe(250.0)
        // 250 from the unmapped half, plus the whole 90 order with no items.
        ->and((float) $unresolved->delivered_amount)->toBe(340.0)
        ->and((int) $unresolved->delivered_count)->toBe(2);

    // 500 + 90, all still accounted for.
    expect(round($statement->productStatements()->get()->sum(fn ($r) => (float) $r->delivered_amount), 2))->toBe(590.0);
});
