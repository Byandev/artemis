<?php

use Modules\MetaAds\Models\Insight;

it('updateOrCreate scopes the UPDATE to the composite key (does not clobber other rows)', function () {
    $a = Insight::create(['meta_ads_ad_id' => 111, 'date' => '2026-06-01', 'meta_ads_account_id' => 1, 'spend' => 10, 'impressions' => 100]);
    $b = Insight::create(['meta_ads_ad_id' => 222, 'date' => '2026-06-01', 'meta_ads_account_id' => 1, 'spend' => 20, 'impressions' => 200]);

    // Re-sync only row A.
    Insight::updateOrCreate(
        ['meta_ads_ad_id' => 111, 'date' => '2026-06-01'],
        ['meta_ads_account_id' => 1, 'spend' => 99, 'impressions' => 999],
    );

    $a->refresh();
    $b->refresh();

    expect((float) $a->spend)->toBe(99.0)
        ->and($a->impressions)->toBe(999)
        // Row B must be untouched — the no-WHERE bug would have set it to 99/999.
        ->and((float) $b->spend)->toBe(20.0)
        ->and($b->impressions)->toBe(200)
        ->and(Insight::count())->toBe(2);
});

it('updates an existing row in place rather than inserting a duplicate', function () {
    $key = ['meta_ads_ad_id' => 333, 'date' => '2026-06-02'];

    Insight::updateOrCreate($key, ['meta_ads_account_id' => 1, 'spend' => 5]);
    Insight::updateOrCreate($key, ['meta_ads_account_id' => 1, 'spend' => 50]);

    expect(Insight::where($key)->count())->toBe(1)
        ->and((float) Insight::where($key)->value('spend'))->toBe(50.0);
});
