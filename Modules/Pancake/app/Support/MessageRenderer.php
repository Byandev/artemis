<?php

namespace Modules\Pancake\Support;

use App\Models\ParcelJourneyNotificationTemplate;
use App\Models\Workspace;

class MessageRenderer
{
    /**
     * Resolve the correct template and interpolate {{ variable }} placeholders.
     */
    public function render(Workspace $workspace, string $type, string $activity, string $receiver, array $data): string
    {
        $template = $this->resolveTemplate($workspace, $type, $activity, $receiver);

        return $this->interpolate($template, $data);
    }

    private function resolveTemplate(Workspace $workspace, string $type, string $activity, string $receiver): string
    {
        return $workspace->parcelJourneyNotificationTemplates()
            ->where('type', $type)
            ->where('activity', $activity)
            ->where('receiver', $receiver)
            ->value('message')
            ?? ParcelJourneyNotificationTemplate::defaults()
                ->where('type', $type)
                ->where('activity', $activity)
                ->where('receiver', $receiver)
                ->value('message')
            ?? '';
    }

    private function interpolate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($matches) use ($data) {
            return array_key_exists($matches[1], $data) ? (string) $data[$matches[1]] : $matches[0];
        }, $template);
    }
}
