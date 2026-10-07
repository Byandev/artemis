<?php

use App\Models\Page;
use App\Models\User;

function autoBudgetUrl(Page $page): string
{
    return "/workspaces/{$page->workspace->slug}/pages/{$page->id}/auto-budget";
}

it('turns auto update on when the Meta Ads module is enabled', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['meta_ads_module_enabled' => true]);
    $page = Page::factory()->forWorkspace($workspace)->create(['auto_update_ad_budget' => false]);

    $this->patch(autoBudgetUrl($page), ['auto_update_ad_budget' => true])->assertRedirect();

    expect($page->fresh()->auto_update_ad_budget)->toBeTrue();
});

it('404s when the Meta Ads module is disabled', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['meta_ads_module_enabled' => false]);
    $page = Page::factory()->forWorkspace($workspace)->create(['auto_update_ad_budget' => false]);

    $this->patch(autoBudgetUrl($page), ['auto_update_ad_budget' => true])->assertNotFound();

    expect($page->fresh()->auto_update_ad_budget)->toBeFalse();
});

it('validates the flag', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $workspace->update(['meta_ads_module_enabled' => true]);
    $page = Page::factory()->forWorkspace($workspace)->create();

    $this->patch(autoBudgetUrl($page), [])->assertSessionHasErrors('auto_update_ad_budget');
});

it('redirects guests to login', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['meta_ads_module_enabled' => true]);
    $page = Page::factory()->forWorkspace($workspace)->create();

    $this->patch(autoBudgetUrl($page), ['auto_update_ad_budget' => true])->assertRedirect('/login');
});

it('forbids users outside the workspace', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['meta_ads_module_enabled' => true]);
    $page = Page::factory()->forWorkspace($workspace)->create(['auto_update_ad_budget' => false]);

    $this->actingAs(User::factory()->create())
        ->patch(autoBudgetUrl($page), ['auto_update_ad_budget' => true])
        ->assertForbidden();

    expect($page->fresh()->auto_update_ad_budget)->toBeFalse();
});
