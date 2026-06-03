<?php

use App\Jobs\TriggerFetchCsrErpDailRecord;
use App\Jobs\TriggerFetchInventoryKeywordRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('TriggerFetchCsrErpDailRecord posts to webhook and does not log on success', function () {
    Http::fake(['hooks.example.test/*' => Http::response(['ok' => true], 200)]);
    Log::spy();

    (new TriggerFetchCsrErpDailRecord('https://hooks.example.test/csr', [
        'csr_id' => 1, 'csr_name' => 'Bob', 'workspace_id' => 99,
    ]))->handle();

    Http::assertSent(fn ($req) => $req->url() === 'https://hooks.example.test/csr');
    Log::shouldNotHaveReceived('warning');
});

test('TriggerFetchCsrErpDailRecord logs warning on non-2xx response', function () {
    Http::fake(['hooks.example.test/*' => Http::response(['error' => 'down'], 500)]);
    Log::spy();

    (new TriggerFetchCsrErpDailRecord('https://hooks.example.test/csr', [
        'csr_id' => 1, 'workspace_id' => 99,
    ]))->handle();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'CSR ERP daily records') && $ctx['status'] === 500);
});

test('TriggerFetchInventoryKeywordRecord posts and logs warning on failure', function () {
    Http::fake(['hooks.example.test/*' => Http::response([], 502)]);
    Log::spy();

    (new TriggerFetchInventoryKeywordRecord('https://hooks.example.test/inv', [
        'inventory_item_id' => 5, 'workspace_id' => 7,
    ]))->handle();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $ctx['status'] === 502 && $ctx['inventory_item_id'] === 5);
});

test('TriggerFetchInventoryKeywordRecord does not log on 2xx', function () {
    Http::fake(['hooks.example.test/*' => Http::response([], 204)]);
    Log::spy();

    (new TriggerFetchInventoryKeywordRecord('https://hooks.example.test/inv', [
        'inventory_item_id' => 5,
    ]))->handle();

    Log::shouldNotHaveReceived('warning');
});
