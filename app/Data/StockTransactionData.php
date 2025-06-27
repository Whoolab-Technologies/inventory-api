<?php

namespace App\Data;
use App\Enums\StockMovement;
use App\Enums\StockMovementType;
class StockTransactionData
{
    public function __construct(
        public int $storeId,
        public int $productId,
        public int $engineerId,
        public int $quantityChange,
        public StockMovementType $type,
        public StockMovement $movement,
        public ?string $lpo = null,
        public ?string $dnNumber = null,
    ) {
    }
}