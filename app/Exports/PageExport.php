<?php

namespace App\Exports;

use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports every page in a workspace with all columns except `owner_id`.
 * Headings match the database column names so the file round-trips through
 * the importer (see App\Imports\PageImport).
 */
class PageExport implements FromCollection, WithHeadings, WithMapping
{
    /** All page columns except owner_id, in export order. */
    public const COLUMNS = [
        'id',
        'shop_id',
        'product_id',
        'workspace_id',
        'status',
        'name',
        'facebook_url',
        'pos_token',
        'botcake_token',
        'infotxt_token',
        'infotxt_user_id',
        'orders_last_synced_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'pancake_token',
        'parcel_journey_enabled',
        'parcel_journey_flow_id',
        'parcel_journey_custom_field_id',
        'is_sync_logic_updated',
    ];

    public function __construct(private Workspace $workspace, private ?User $user = null) {}

    public function collection(): Collection
    {
        return $this->workspace
            ->pages()
            ->when($this->user, fn ($q) => $q->visibleTo($this->user, $this->workspace))
            ->withTrashed()
            ->orderBy('id')
            ->get(array_merge(self::COLUMNS, ['owner_id']));
    }

    public function headings(): array
    {
        return self::COLUMNS;
    }

    /**
     * @param  Page  $page
     */
    public function map($page): array
    {
        return array_map(fn (string $column) => $page->{$column}, self::COLUMNS);
    }
}
