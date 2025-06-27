<?php

namespace App\Enums;

enum StatusEnum: int
{
    case UNKNOWN = 1;
    case PENDING = 2;
    case APPROVED = 3;
    case REJECTED = 4;
    case PROCESSING = 5;
    case CANCELLED = 6;
    case COMPLETED = 7;
    case PARTIALLY_RECEIVED = 8;
    case AWAITING_PROC = 9;
    case IN_TRANSIT = 10;
    case RECEIVED = 11;

    public static function getIdByCode(string $code): ?int
    {
        return match ($code) {
            'UNKNOWN' => self::UNKNOWN->value,
            'PENDING' => self::PENDING->value,
            'APPROVED' => self::APPROVED->value,
            'REJECTED' => self::REJECTED->value,
            'PROCESSING' => self::PROCESSING->value,
            'CANCELLED' => self::CANCELLED->value,
            'COMPLETED' => self::COMPLETED->value,
            'PARTIALLY_RECEIVED' => self::PARTIALLY_RECEIVED->value,
            'AWAITING_PROC' => self::AWAITING_PROC->value,
            'IN_TRANSIT' => self::IN_TRANSIT->value,
            'RECEIVED' => self::RECEIVED->value,
            default => null,
        };
    }
}
