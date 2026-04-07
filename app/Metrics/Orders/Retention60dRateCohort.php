<?php

namespace App\Metrics\Orders;

final class Retention60dRateCohort extends RetentionRateCohort
{
    protected function days(): int
    {
        return 60;
    }
}
