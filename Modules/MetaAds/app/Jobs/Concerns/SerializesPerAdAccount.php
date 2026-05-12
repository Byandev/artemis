<?php

namespace Modules\MetaAds\Jobs\Concerns;

use Illuminate\Queue\Middleware\WithoutOverlapping;

trait SerializesPerAdAccount
{
    public function middleware(): array
    {
        // Lock per MetaUser (access token) rather than per AdAccount.
        // Meta's "User request limit reached" (code 17) is enforced at the
        // token level, so two ad accounts sharing one MetaUser must run
        // serially to keep the per-user quota from blowing.
        $metaUserId = $this->adAccount->metaUsers()->first()?->id;
        $key = $metaUserId
            ? "meta-sync:user:{$metaUserId}"
            : "meta-sync:account:{$this->adAccount->id}";

        return [
            (new WithoutOverlapping($key))
                ->expireAfter(900)
                ->releaseAfter(60),
        ];
    }
}
