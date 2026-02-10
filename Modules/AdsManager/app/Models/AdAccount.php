<?php

namespace Modules\AdsManager\Models;

use App\Models\FacebookAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdAccount extends Model
{

    protected $guarded = [];

    public function facebookAccounts(): BelongsToMany
    {
        return $this->belongsToMany(FacebookAccount::class, 'facebook_account_ad_account');
    }
}
