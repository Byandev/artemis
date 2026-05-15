<?php

use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class ActivityLogPagesTest extends TestCase
{
    public function test_workspace_activity_log_page_loads()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get("/workspaces/{$workspace->slug}/admin/activity-log");

        $this->assertTrue(
            in_array($response->status(), [200, 302]),
            "Expected 200 or 302, got {$response->status()}"
        );
    }

    public function test_admin_activity_log_page_loads()
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($user)
            ->get('/admin/activity-log');

        $this->assertTrue(
            in_array($response->status(), [200, 302]),
            "Expected 200 or 302, got {$response->status()}"
        );
    }
}
