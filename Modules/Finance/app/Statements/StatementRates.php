<?php

namespace Modules\Finance\Statements;

use App\Models\Workspace;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\IncomeStatementSetting;
use Modules\Finance\Statements\Contracts\StatementOrderSource;

/**
 * The rates a statement is struck at.
 *
 * They travel together because they are always chosen together and then
 * snapshotted onto the statement, so a month that has been closed keeps the
 * terms it was closed under no matter what the workspace defaults do later.
 */
final class StatementRates
{
    public function __construct(
        public readonly float $codFee,
        public readonly float $vat,
        public readonly float $advisory,
        public readonly float $advisoryDelivered,
    ) {}

    /** The rates a saved statement was struck at — what a regenerate reuses. */
    public static function fromStatement(IncomeStatement $statement): self
    {
        return new self(
            codFee: (float) $statement->cod_fee_rate,
            vat: (float) $statement->vat_rate,
            advisory: (float) $statement->advisory_rate,
            advisoryDelivered: (float) $statement->advisory_delivered_rate,
        );
    }

    /**
     * The workspace's saved rates, falling back to the courier's own where it
     * has never chosen one.
     *
     * @param  array<string, float|null>  $overrides  keyed cod/vat/advisory/advisory_delivered
     */
    public static function resolve(
        Workspace $workspace,
        ?IncomeStatementSetting $settings,
        StatementOrderSource $source,
        array $overrides = [],
    ): self {
        $pick = fn (?float $override, ?string $saved, float $default) => (float) ($override ?? $saved ?? $default);

        return new self(
            codFee: $pick($overrides['cod'] ?? null, $settings?->cod_fee_rate, $source->defaultCodFeeRate()),
            vat: $pick($overrides['vat'] ?? null, $settings?->vat_rate, IncomeStatementSetting::DEFAULT_VAT_RATE),
            advisory: $pick($overrides['advisory'] ?? null, $settings?->advisory_rate, IncomeStatementSetting::DEFAULT_ADVISORY_RATE),
            advisoryDelivered: $pick($overrides['advisory_delivered'] ?? null, $settings?->advisory_delivered_rate, IncomeStatementSetting::DEFAULT_ADVISORY_DELIVERED_RATE),
        );
    }

    /** @return array<string, float> the columns these are snapshotted into */
    public function toAttributes(): array
    {
        return [
            'cod_fee_rate' => $this->codFee,
            'vat_rate' => $this->vat,
            'advisory_rate' => $this->advisory,
            'advisory_delivered_rate' => $this->advisoryDelivered,
        ];
    }
}
