<?php

namespace App\Metrics\Concerns;

use App\Metrics\MetricSource;
use App\Support\Analytics\LiveReader;
use App\Support\Analytics\RollupReader;

/**
 * Adds a $source toggle to a metric class so it can compute either from the
 * pre-aggregated rollup table (RollupReader) or directly from pancake_orders
 * (LiveReader). Metric classes use $this->reader() in place of a hard-coded
 * RollupReader reference.
 */
trait HasMetricSource
{
    protected string $source = MetricSource::LIVE;

    public function setSource(string $source): static
    {
        $this->source = MetricSource::normalize($source);

        return $this;
    }

    /**
     * @return class-string<LiveReader|RollupReader>
     */
    protected function reader(): string
    {
        return $this->source === MetricSource::LIVE ? LiveReader::class : RollupReader::class;
    }
}
