<?php

namespace Modules\Finance\Statements;

/**
 * What a set of orders contributes to a statement, whichever system they came
 * from.
 *
 * Delivered and shipped are carried together because they are read together but
 * measured over different order sets — a parcel is delivered in one month and
 * may have shipped in another, and the courier is paid either way.
 */
final class OrderTotals
{
    public function __construct(
        public readonly int $deliveredOrders = 0,
        public readonly int $deliveredUnits = 0,
        public readonly float $deliveredAmount = 0.0,
        public readonly float $deliveredCogs = 0.0,
        public readonly int $shippedOrders = 0,
        public readonly float $shippingFee = 0.0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /** Fold another set in — used when several keys collapse onto one row. */
    public function plus(self $other): self
    {
        return new self(
            $this->deliveredOrders + $other->deliveredOrders,
            $this->deliveredUnits + $other->deliveredUnits,
            round($this->deliveredAmount + $other->deliveredAmount, 2),
            round($this->deliveredCogs + $other->deliveredCogs, 2),
            $this->shippedOrders + $other->shippedOrders,
            round($this->shippingFee + $other->shippingFee, 2),
        );
    }
}
