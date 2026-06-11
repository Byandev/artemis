<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Full Meta Ads permission set, matching App\Enums\Permission (category
     * "Meta Ads"). The first four already existed in some databases; the last
     * three are the newly added read permissions.
     *
     * @var array<int, string>
     */
    private array $permissions = [
        'View Meta Ads',
        'Connect FB Account',
        'View Ad Accounts',
        'Manage Meta Ads Accounts',
        'View Optimization Rules',
        'Manage Optimization Rules',
        'Approve Optimization Rules',
        'View Optimization Logs',
    ];

    /**
     * Only these are introduced by this migration, so only these are removed on
     * rollback (the others predate it).
     *
     * @var array<int, string>
     */
    private array $added = [
        'Connect FB Account',
        'View Ad Accounts',
        'View Optimization Rules',
        'View Optimization Logs',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'category' => 'Meta Ads',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', $this->added)->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
