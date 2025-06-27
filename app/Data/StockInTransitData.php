<?php
namespace App\Data;

class StockInTransitData
{
    public function __construct(
        public int $stockTransferId,
        public int $materialRequestId,
        public int $stockTransferItemId,
        public int $productId,
        public int $issuedQuantity
    ) {
    }
}
