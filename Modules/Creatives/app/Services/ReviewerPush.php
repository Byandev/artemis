<?php

namespace Modules\Creatives\Services;

/**
 * One notification to a set of reviewers, on every device they turned it on:
 * the phone app (Expo push) and the installed web app (Web Push).
 */
class ReviewerPush
{
    public function __construct(private ExpoPush $expo, private WebPushSender $web) {}

    /**
     * @param  array<int>  $userIds
     * @param  bool  $urgent  deliver right away even on an idle phone
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data, bool $urgent = false): void
    {
        $this->expo->sendToUsers($userIds, [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            // Without high priority Android may hold the push while the phone is idle.
            ...($urgent ? ['priority' => 'high'] : []),
            'channelId' => 'reviews',
            'data' => $data,
        ]);

        $this->web->sendToUsers($userIds, ['title' => $title, 'body' => $body, 'data' => $data], $urgent);
    }
}
