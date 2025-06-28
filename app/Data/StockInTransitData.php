<?php
namespace App\Data;

class StockInTransitData
{
    public function __construct(
        public int $stockTransferId,
        public int $stockTransferItemId,
        public int $productId,
        public int $issuedQuantity,
        public ?int $materialRequestId = null,
        public ?int $materialRequestItemId = null,
        public ?int $materialReturnId = null,
        public ?int $materialReturnItemId = null,
    ) {
    }
}
