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
    // An existing page post reused as an ad — Meta gives no video_id or spec,
    // only a thumbnail served from the video CDN path.
    $postVideo = Creative::create([
        'id' => 8904,
        'meta_ads_account_id' => $account->id,
        'object_type' => 'STATUS',
        'thumbnail_url' => 'https://scontent.fcrk3-3.fna.fbcdn.net/v/t15.5256-10/485211335_948401_n.jpg',
    ]);
    // The same kind of post, but a photo — its thumbnail is on the photo path.
    $postPhoto = Creative::create([
        'id' => 8905,
        'meta_ads_account_id' => $account->id,
        'object_type' => 'STATUS',
        'thumbnail_url' => 'https://scontent.fcrk3-3.fna.fbcdn.net/v/t39.30808-6/512345678_1234_n.jpg',
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

    $build(8200, 'Mixed', [[8210, 'Video Ad', $video->id, 100], [8211, 'Image Ad', $image->id, 40], [8212, 'Spec Video Ad', $specVideo->id, 10], [8213, 'Post Video Ad', $postVideo->id, 5], [8214, 'Post Photo Ad', $postPhoto->id, 3]]);
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
        ->toBe(['Post Video Ad' => 5.0, 'Spec Video Ad' => 10.0, 'Video Ad' => 100.0]);
});

it('treats ads without a synced creative as images', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'ad', 'media_type' => 'image'])))
        ->toBe(['Image Ad' => 40.0, 'Post Photo Ad' => 3.0, 'Uncreatived Ad' => 25.0]);
});

it('sums only matching ads on campaign rows and drops campaigns without any', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    $video = mediaTypeRows($workspace, ['group_by' => 'campaign', 'media_type' => 'video']);
    expect(spendByName($video))->toBe(['Mixed Campaign' => 115.0])
        ->and($video[0]['ads_count'])->toBe(3);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'campaign', 'media_type' => 'image'])))
        ->toBe(['Mixed Campaign' => 43.0, 'Static Campaign' => 25.0]);
});

it('narrows account totals to the chosen media type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'account', 'media_type' => 'image'])))
        ->toBe(['Account A' => 68.0]);
});

it('ignores an unknown media type', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedMediaTypes($workspace);

    expect(spendByName(mediaTypeRows($workspace, ['group_by' => 'account', 'media_type' => 'carousel'])))
        ->toBe(['Account A' => 183.0]);
});
