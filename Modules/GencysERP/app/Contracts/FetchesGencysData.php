<?php

namespace Modules\GencysERP\Contracts;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\GencysERP\Support\Fetchers\GencysFetcherFactory;

/**
 * One Gencys ERP data type's fetch strategy: how it resolves dates, which
 * workspace relations its payload needs, how it chunks work, and what it hands
 * n8n.
 *
 * The strategy for a given type is built by {@see GencysFetcherFactory}, which
 * is the only place that knows which class serves which type.
 */
interface FetchesGencysData
{
    /** The sync type these runs are recorded under, and n8n's branch selector. */
    public function type(): string;

    /** The n8n webhook this type posts to, or null when none is configured. */
    public function webhookUrl(): ?string;

    /**
     * Load the workspaces this type needs, with whatever relations its payload
     * reads. $query already carries ERP-eligibility and any --workspace filter.
     *
     * @return Collection<int, Workspace>
     */
    public function loadWorkspaces(Builder $query): Collection;

    /**
     * Open the sync runs and dispatch the webhook calls.
     *
     * @param  Collection<int, Workspace>  $workspaces
     * @return int runs opened
     */
    public function dispatch(Collection $workspaces): int;
}
