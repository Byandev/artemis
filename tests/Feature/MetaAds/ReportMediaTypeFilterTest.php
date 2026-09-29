<?php

use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Creative;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * The image / video filter (`media_type`) on the Ads Manager data engine, which
 * the Reports builder renders against. One campaign runs a video ad (spend 100)
 * and an image ad (spend 40); a second campaign runs only an image ad whose
 * creative was never synced (spend 25), which still counts as an image.
 */
function seedMediaTypes($workspace): void
{
    $metaUser = MetaUser::create(['id' => 8101, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 801, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $video = Creative::create(['id' => 8901, 'meta_ads_account_id' => $account->id, 'object_type' => 'VIDEO']);
    $image = Creative::create(['id' => 8902, 'meta_ads_account_id' => $account->id, 'object_type' => 'PHOTO']);
    // A video Meta only exposes inside the story spec — no top-level video_id.
    $specVideo = Creative::create([
        'id' => 8903,
        'meta_ads_account_id' => $account->id,
        'object_type' => 'SHARE',
        'object_story_spec' => ['page_id' => '1', 'video_data' => ['video_id' => '4311688215808753']],
    ]);

    $build = function (int $campaignId, string $label, array $ads) use ($account) {
        $campaign = Campaign::create(['id' => $campaignId, 'meta_ads_account_id' => $account->id, 'name' => "{$label} Campaign"]);
        $set = AdSet::create([
            'id' => $campaignId + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'name' => "{$label} Ad Set",
        ]);

        foreach ($ads as [$adId, $name, $creativeId, $spend]) {
            Ad::create([
                'id' => $adId,
                'meta_ads_account_id' => $account->id,
                'meta_ads_campaign_id' => $campaign->id,
                'meta_ads_set_id' => $set->id,
                'meta_ads_creative_id' => $creativeId,
                'name' => $name,
            ]);

            Insight::create([
                'meta_ads_ad_id' => $adId,
                'date' => '2026-07-20',
                'meta_ads_account_id' => $account->id,
                'meta_ads_campaign_id' => $campaign->id,
                'meta_ads_set_id' => $set->id,
                'spend' => $spend,
            ]);
        }
    };

    $build(8200, 'Mixed', [[8210, 'Video Ad', $video->id, 100], [8211, 'Image Ad', $image->id, 40], [8212, 'Spec Video Ad', $specVideo->id, 10]]);
    $build(8300, 'Static', [[8310, 'Uncreatived Ad', null, 25]]);
}

function mediaTypeRows($workspace, array $query): array
{
    return test()->getJson(route('workspaces.metaads.ads-manager.data', [
        'workspace' => $workspace,
        'since' => '2026-07-01',
        'until' => '2026-07-31',
        ...$query,
    ]))->assertOk()->json('rows.data');
}

/** name => spend for every returned row. */
function spendByName(array $rows): array
{
    return collect($rows)->mapWithKeys(fn ($r) => [$r['name'] => (float) $r['spend']])->sortKeys()->all();
}

it('keeps only video ads', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'ad', 'media_type' => 'video'])))
        ->toBe(['Spec Video Ad' => 10.0, 'Video Ad' => 100.0]);
});

it('treats ads without a synced creative as images', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'ad', 'media_type' => 'image'])))
        ->toBe(['Image Ad' => 40.0, 'Uncreatived Ad' => 25.0]);
});

it('sums only matching ads on campaign rows and drops campaigns without any', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    $video = mediaTypeRows($workspace, ['group_by' => 'campaign', 'media_type' => 'video']);
    expect(spendByName($video))->toBe(['Mixed Campaign' => 110.0])
        ->and($video[0]['ads_count'])->toBe(2);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'campaign', 'media_type' => 'image'])))
        ->toBe(['Mixed Campaign' => 40.0, 'Static Campaign' => 25.0]);
});

it('narrows account totals to the chosen media type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'account', 'media_type' => 'image'])))
        ->toBe(['Account A' => 65.0]);
});

it('ignores an unknown media type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'account', 'media_type' => 'carousel'])))
        ->toBe(['Account A' => 175.0]);
});
