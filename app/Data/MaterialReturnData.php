<?php
namespace App\Data;

class MaterialReturnData
{
    public function __construct(
        public int $fromStoreId,
        public int $toStoreId,
        public array $items,
        public ?string $dnNumber,
    ) {
    }
}