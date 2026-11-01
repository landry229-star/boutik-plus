<?php

namespace App\Services\MobileMoney;

use RuntimeException;
use Throwable;

class MobileMoneyGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $safeToReleaseReservation = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
