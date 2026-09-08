<?php

namespace Modules\MetaAds\Support;

use Modules\MetaAds\Exceptions\MetaGraphException;
use Throwable;

/**
 * Turns a sync failure into the two things a reader actually needs: what broke,
 * and what to do about it.
 *
 * "code 190 · subcode 463 · OAuthException" is precise and useless in a channel
 * — it makes every reader look the number up. The code still travels with the
 * alert for the support ticket, but the headline says "Token expired" and the
 * body says "reconnect this account's Meta login".
 */
final class SyncErrorCause
{
    /**
     * Meta error code => [headline, what to do]. Only codes that actually reach
     * a terminal failure are listed; rate-limit and transient codes release the
     * job for a retry and never get here.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const CAUSES = [
        190 => ['Token expired', 'Reconnect this account’s Meta login — the access token is no longer valid.'],
        102 => ['Session invalid', 'Reconnect this account’s Meta login.'],
        10 => ['Permission denied', 'The token is missing a permission this sync needs. Re-grant ads_read in Business Manager.'],
        200 => ['Permission denied', 'The token cannot read this ad account. Check its Business Manager access.'],
        100 => ['Request rejected', 'Meta refused the request — usually a removed ad account or a field it no longer serves.'],
        803 => ['Object not found', 'Meta no longer returns this object. It was probably deleted on their side.'],
        294 => ['Managing ads requires access', 'The token needs ads_management for this account.'],
        272 => ['Insufficient role', 'The connected user’s role on this ad account is too low to read ads data.'],
    ];

    /**
     * The headline and action for a failure. A non-active ad account wins over
     * the error code: when billing is unsettled or the account is disabled,
     * that is the cause and whatever the job threw is a symptom.
     *
     * @return array{headline: string, action: string}
     */
    public static function for(Throwable $e, ?int $accountStatus): array
    {
        if ($accountStatus !== null && ! AccountStatus::isActive($accountStatus)) {
            $label = AccountStatus::label($accountStatus);

            return [
                'headline' => $label,
                'action' => "Meta reports this ad account as {$label}, which is why the sync cannot run. Settle it in Ads Manager first.",
            ];
        }

        $code = $e instanceof MetaGraphException ? $e->errorCode : null;

        if ($code !== null && isset(self::CAUSES[$code])) {
            [$headline, $action] = self::CAUSES[$code];

            return ['headline' => $headline, 'action' => $action];
        }

        return [
            'headline' => 'Sync failed',
            'action' => 'The sync gave up after exhausting its retries. The error below is what Meta returned.',
        ];
    }
}
