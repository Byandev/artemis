<?php

namespace App\Metrics\Orders;

final class Retention90dRateCohort extends RetentionRateCohort
{
    protected function days(): int
    {
        return 90;
    }
}
