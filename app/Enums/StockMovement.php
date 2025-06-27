<?php

namespace App\Enums;


enum StockMovement: string
{
    case IN = 'IN';
    case OUT = 'OUT';
    case TRANSIT = 'TRANSIT';
}