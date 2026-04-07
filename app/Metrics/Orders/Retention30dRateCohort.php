<?php

namespace App\Metrics\Orders;

final class Retention30dRateCohort extends RetentionRateCohort
{
    protected function days(): int
    {
        return 30;
    }
}
