<?php

namespace App\Imports;

use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports NEW pages from a sheet. Existing pages (matched by `id` within the
 * workspace) are skipped — import never updates. Header names are mapped to
 * database columns (accepting both the raw column names and the friendly
 * labels used in exported/hand-made sheets). `owner_id` is always the
 * importing user; `workspace_id` is forced to the current workspace.
 */
class PageImport implements ToCollection, WithHeadingRow
{
    /**
     * Map of accepted (slugged) header keys to database columns. Multiple
     * aliases may point at the same column.
     */
    private const COLUMN_MAP = [
        'shop_id' => 'shop_id',
        'product_id' => 'product_id',
        'status' => 'status',
        'name' => 'name',
        'pos_token' => 'pos_token',
        'pos_api_key' => 'pos_token',
        'botcake_token' => 'botcake_token',
        'infotxt_token' => 'infotxt_token',
        'infotxt_user_id' => 'infotxt_user_id',
        'orders_last_synced_at' => 'orders_last_synced_at',
        'pancake_token' => 'pancake_token',
        'parcel_journey_enabled' => 'parcel_journey_enabled',
        'parcel_journey_flow_id' => 'parcel_journey_flow_id',
        'parcel_journey_custom_field_id' => 'parcel_journey_custom_field_id',
        'parcel_journey_custom_field' => 'parcel_journey_custom_field_id',
        'is_sync_logic_updated' => 'is_sync_logic_updated',
    ];

    private const BOOLEANS = [
        'parcel_journey_enabled',
        'is_sync_logic_updated',
    ];

    public int $created = 0;

    public int $skipped = 0;

    public int $failed = 0;

    public function __construct(
        private Workspace $workspace,
        private int $ownerId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $attributes = $this->attributesFrom($row);
            $id = $row->get('id');

            // A page needs its (Facebook) id and a name; skip malformed rows.
            if (empty($id) || empty($attributes['name'])) {
                continue;
            }

            // Never update: skip rows whose id already exists in the workspace.
            $exists = Page::withTrashed()
                ->where('workspace_id', $this->workspace->id)
                ->whereKey($id)
                ->exists();

            if ($exists) {
                $this->skipped++;

                continue;
            }

            try {
                // pages.shop_id is a foreign key — make sure the shop exists
                // in this workspace before creating the page.
                if (! empty($attributes['shop_id'])) {
                    Shop::firstOrCreate(
                        [
                            'id' => $attributes['shop_id'],
                            'workspace_id' => $this->workspace->id,
                        ],
                        ['name' => 'Imported Shop '.$attributes['shop_id']],
                    );
                }

                Page::create($attributes + [
                    'id' => $id,
                    'workspace_id' => $this->workspace->id,
                    'owner_id' => $this->ownerId,
                ]);
                $this->created++;
            } catch (\Throwable $e) {
                report($e);
                $this->failed++;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(Collection $row): array
    {
        $attributes = [];

        foreach (self::COLUMN_MAP as $header => $column) {
            if (! $row->has($header)) {
                continue;
            }

            $value = $row->get($header);
            $value = $value === '' ? null : $value;

            if (in_array($column, self::BOOLEANS, true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            // Don't overwrite a mapped value with a blank alias column.
            if ($value === null && array_key_exists($column, $attributes)) {
                continue;
            }

            $attributes[$column] = $value;
        }

        return $attributes;
    }
}
