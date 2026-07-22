<?php

use Modules\Creatives\Models\Creative;

function makeCreative($workspace, $user): Creative
{
    return Creative::create([
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'name' => 'Test Creative',
        'creative_date' => '2026-07-01',
        'format' => 'video',
        'ads_status' => 'pending',
        'final_status' => 'for_approval',
    ]);
}

it('returns the full edit form to the list with its filters intact', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeCreative($workspace, $user);

    $listQuery = 'filter%5Bformat%5D=video&filter%5Bfinal_status%5D=for_approval&sort=-creative_date&per_page=25';

    $response = $this->from("/workspaces/{$workspace->slug}/creatives/{$creative->id}/edit")
        ->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}?{$listQuery}", [
            'name' => 'Renamed Creative',
            'creative_date' => '2026-07-01',
            'format' => 'video',
            'ads_status' => 'pending',
            'final_status' => 'for_approval',
        ]);

    $target = $response->headers->get('Location');

    expect($target)->toContain("/workspaces/{$workspace->slug}/creatives")
        ->and($target)->not->toContain('/edit')
        ->and(urldecode($target))->toContain('filter[format]=video')
        ->and(urldecode($target))->toContain('filter[final_status]=for_approval')
        ->and(urldecode($target))->toContain('sort=-creative_date');

    expect($creative->fresh()->name)->toBe('Renamed Creative');
});

it('keeps an inline status update on the filtered list it was sent from', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeCreative($workspace, $user);

    $listUrl = "/workspaces/{$workspace->slug}/creatives?filter%5Bformat%5D=video";

    $this->from($listUrl)
        ->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}", [
            'final_status' => 'approved',
        ])
        ->assertRedirect($listUrl);

    expect($creative->fresh()->final_status)->toBe('approved');
});
