<?php

namespace App\Data;
use App\Enums\StatusEnum;
use App\Enums\StockMovementType;
use App\Enums\TransferPartyRole;
use App\Enums\TransactionType;

class PurchaseRequestData
{
    public function __construct(
        public int $materialRequestId,
        public string $materialRequestNumber,
        public ?int $lpo = null,
        public ?int $do = null,
        public StatusEnum $statusId = StatusEnum::PENDING,
        public array $items = []
    ) {
    }
}
