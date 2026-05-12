<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Services\Pancake;

beforeEach(function () {
    $this->pancake = new Pancake(123, 'KEY');
});

// listProducts

test('listProducts returns decoded JSON on success', function () {
    Http::fake(['pos.pages.fm/*' => Http::response(['data' => [['id' => 1]]], 200)]);

    expect($this->pancake->listProducts())->toBe(['data' => [['id' => 1]]]);
});

test('listProducts throws RequestException on 4xx', function () {
    Http::fake(['pos.pages.fm/*' => Http::response(['error' => 'unauthorized'], 401)]);

    expect(fn () => $this->pancake->listProducts())->toThrow(RequestException::class);
});

test('listProducts throws RequestException on 5xx', function () {
    Http::fake(['pos.pages.fm/*' => Http::response([], 503)]);

    expect(fn () => $this->pancake->listProducts())->toThrow(RequestException::class);
});

test('listProducts propagates ConnectionException on network failure', function () {
    Http::fake(fn () => throw new ConnectionException('dns lookup failed'));

    expect(fn () => $this->pancake->listProducts())->toThrow(ConnectionException::class);
});

// listCustomers / listUsers

test('listCustomers returns decoded JSON on success', function () {
    Http::fake(['pos.pages.fm/*' => Http::response(['customers' => []], 200)]);
    expect($this->pancake->listCustomers())->toBe(['customers' => []]);
});

test('listCustomers throws on failure', function () {
    Http::fake(['pos.pages.fm/*' => Http::response([], 500)]);
    expect(fn () => $this->pancake->listCustomers())->toThrow(RequestException::class);
});

test('listUsers returns decoded JSON on success', function () {
    Http::fake(['pos.pages.fm/*' => Http::response(['users' => []], 200)]);
    expect($this->pancake->listUsers())->toBe(['users' => []]);
});

test('listUsers throws on failure', function () {
    Http::fake(['pos.pages.fm/*' => Http::response([], 401)]);
    expect(fn () => $this->pancake->listUsers())->toThrow(RequestException::class);
});

// listPageCustomers (static)

test('listPageCustomers makes the correct GET request and returns JSON', function () {
    Http::fake(['pages.fm/*' => Http::response(['success' => true, 'data' => []], 200)]);

    $result = Pancake::listPageCustomers('page-99', 'tok', 1700000000, 1700100000);

    expect($result)->toBe(['success' => true, 'data' => []]);

    Http::assertSent(function ($req) {
        return str_contains($req->url(), 'pages.fm/api/public_api/v1/pages/page-99/page_customers')
            && $req['page_access_token'] === 'tok';
    });
});

test('listPageCustomers throws RequestException on failure', function () {
    Http::fake(['pages.fm/*' => Http::response([], 502)]);

    expect(fn () => Pancake::listPageCustomers('p', 't', 1, 2))->toThrow(RequestException::class);
});
