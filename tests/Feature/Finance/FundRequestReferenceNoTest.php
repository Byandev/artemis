<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

/** Create a request through the controller, so the reference number is stamped. */
function createFundRequest(): void
{
    test()->actingAs(test()->user)
        ->post(test()->url, [
            'amount_requested' => 500,
            'charge_to' => [['user_id' => test()->user->id]],
        ])
        ->assertRedirect();
}

test('reference numbers run in sequence', function () {
    createFundRequest();
    createFundRequest();
    createFundRequest();

    expect(FundRequest::orderBy('id')->pluck('reference_no')->all())
        ->toBe(['RF-00001', 'RF-00002', 'RF-00003']);
});

test('deleting an older request does not hand its successor a number already in use', function () {
    createFundRequest();
    createFundRequest();
    createFundRequest();

    $this->actingAs($this->user)
        ->delete("{$this->url}/".FundRequest::where('reference_no', 'RF-00001')->value('id'))
        ->assertRedirect();

    createFundRequest();

    // Counting the remaining rows would have produced RF-00003 a second time.
    expect(FundRequest::orderBy('id')->pluck('reference_no')->all())
        ->toBe(['RF-00002', 'RF-00003', 'RF-00004']);
});

test('numbering is per workspace', function () {
    createFundRequest();

    $other = Workspace::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post("/workspaces/{$other->slug}/finance/request-funds", [
            'amount_requested' => 500,
            'charge_to' => [['user_id' => $this->user->id]],
        ])
        ->assertRedirect();

    expect(FundRequest::where('workspace_id', $other->id)->value('reference_no'))
        ->toBe('RF-00001');
});

test('numbering survives deleting every request', function () {
    createFundRequest();
    createFundRequest();

    FundRequest::query()->delete();

    createFundRequest();

    expect(FundRequest::sole()->reference_no)->toBe('RF-00001');
});
