<?php

namespace App\Data;
use App\Enums\StatusEnum;
use App\Enums\StockMovementType;
use App\Enums\TransferPartyRole;
use App\Enums\TransactionType;

class StockTransferData
{
    public function __construct(
        public ?int $fromStoreId,
        public int $toStoreId,
        public StatusEnum $statusId = StatusEnum::IN_TRANSIT,
        public ?string $dnNumber = null,
        public ?string $remarks = null,
        public ?int $requestId = null,
        public StockMovementType $requestType = StockMovementType::DIRECT,
        public TransactionType $transactionType = TransactionType::DIRECT,
        public ?int $sendBy = null,
        public TransferPartyRole $senderRole = TransferPartyRole::CENTRAL_STORE,
        public ?int $receivedBy = null,
        public ?string $receiverRole = null,
        public ?string $note = null,
    ) {
    }
}
