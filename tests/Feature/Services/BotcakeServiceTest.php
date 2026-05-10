<?php

use App\Services\Botcake;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->botcake = new Botcake('PAGE-1', 'TOKEN-1');
});

// updateCustomField

test('updateCustomField succeeds when API returns success:true', function () {
    Http::fake([
        'botcake.io/api/public_api/v1/pages/PAGE-1/customer/PSID/customer_fields' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $this->botcake->updateCustomField('PSID', 'field-1', 'val');

    Http::assertSent(fn ($req) => $req->hasHeader('access-token', 'TOKEN-1')
        && $req['data'][0]['id'] === 'field-1'
        && $req['data'][0]['value'] === 'val');
});

test('updateCustomField throws when API returns 4xx', function () {
    Http::fake(['botcake.io/*' => Http::response(['error' => 'bad'], 401)]);

    expect(fn () => $this->botcake->updateCustomField('PSID', 'f', 'v'))
        ->toThrow(\Exception::class);
});

test('updateCustomField throws when API returns success:false', function () {
    Http::fake(['botcake.io/*' => Http::response(['success' => false, 'message' => 'denied'], 200)]);

    expect(fn () => $this->botcake->updateCustomField('PSID', 'f', 'v'))
        ->toThrow(\Exception::class);
});

test('updateCustomField propagates connection exception', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => $this->botcake->updateCustomField('PSID', 'f', 'v'))
        ->toThrow(ConnectionException::class);
});

// sendFlow

test('sendFlow succeeds when API returns success:true', function () {
    Http::fake(['botcake.io/*' => Http::response(['success' => true], 200)]);

    $this->botcake->sendFlow('PSID', 'flow-1');

    Http::assertSent(fn ($req) => $req['psid'] === 'PSID' && $req['flow_id'] === 'flow-1');
});

test('sendFlow throws when API returns failure status', function () {
    Http::fake(['botcake.io/*' => Http::response([], 500)]);

    expect(fn () => $this->botcake->sendFlow('PSID', 'flow-1'))
        ->toThrow(\Exception::class);
});

// fetchFlows

test('fetchFlows returns flows from data.flows', function () {
    Http::fake(['botcake.io/*' => Http::response(['data' => ['flows' => [['id' => 1], ['id' => 2]]]], 200)]);

    expect($this->botcake->fetchFlows())->toHaveCount(2);
});

test('fetchFlows returns empty array when key missing', function () {
    Http::fake(['botcake.io/*' => Http::response(['data' => []], 200)]);

    expect($this->botcake->fetchFlows())->toBe([]);
});

test('fetchFlows throws on API failure', function () {
    Http::fake(['botcake.io/*' => Http::response([], 503)]);

    expect(fn () => $this->botcake->fetchFlows())->toThrow(\Exception::class);
});

// fetchSequences

test('fetchSequences returns array from data', function () {
    Http::fake(['botcake.io/*' => Http::response(['data' => [['id' => 1]]], 200)]);

    expect($this->botcake->fetchSequences())->toHaveCount(1);
});

test('fetchSequences throws on API failure', function () {
    Http::fake(['botcake.io/*' => Http::response([], 502)]);

    expect(fn () => $this->botcake->fetchSequences())->toThrow(\Exception::class);
});

// fetchFlowStatistics

test('fetchFlowStatistics returns data', function () {
    Http::fake(['botcake.io/*' => Http::response(['data' => ['delivery' => 5]], 200)]);

    expect($this->botcake->fetchFlowStatistics('flow-1'))->toBe(['delivery' => 5]);
});

test('fetchFlowStatistics throws on non-2xx', function () {
    Http::fake(['botcake.io/*' => Http::response([], 404)]);

    expect(fn () => $this->botcake->fetchFlowStatistics('flow-1'))->toThrow(\Exception::class);
});

// fetchSequenceStatistics

test('fetchSequenceStatistics returns data', function () {
    Http::fake(['botcake.io/*' => Http::response(['data' => [['message_id' => 1]]], 200)]);

    expect($this->botcake->fetchSequenceStatistics('seq-1'))->toHaveCount(1);
});

test('fetchSequenceStatistics throws on non-2xx', function () {
    Http::fake(['botcake.io/*' => Http::response([], 500)]);

    expect(fn () => $this->botcake->fetchSequenceStatistics('seq-1'))->toThrow(\Exception::class);
});
