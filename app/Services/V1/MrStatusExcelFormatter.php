<?php
namespace App\Services\V1;
use Carbon\Carbon;
use App\Models\V1\MaterialRequest;
class MrStatusExcelFormatter
{
    // Final Excel headers (exact column titles)
    public const HEADERS = [
        'SL NO',
        'PROJECT',
        'ENGINEER NAME',
        'MR DATE',
        'MR NUMBER',
        'CAT ID',
        'CATEGORY',
        'ITEM DESCRIPTION',
        'BRAND',
        'UNIT',
        'MR QUANTITY',
        'MR APPROVED DATE',
        'QUANTITY ISSUED FROM STORE STOCK',
        'PR NUMBER',
        'PR DATE',
        'PR QUANTITY',
        'LPO NUMBER',
        'LPO DATE',
        'SUPPLIER NAME',
        'LPO QUANTITY',
        'QUANTITY RECEIVED FROM LPO',
        'LPO BALANCE QUANTITY',
        'MR BALANCE QUANTITY',
    ];

    /**
     * Convert the eager-loaded $materialRequest (Eloquent model) to flat rows for Excel.
     * Uses snake_case keys throughout.
     *
     * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model $materialRequest
     * @return array<int, array<string, mixed>> rows matching HEADERS order
     */
    // public static function toExcelRows(MaterialRequest $materialRequest): array
    // {
    //     $mr = $materialRequest->toArray(); // snake_case keys guaranteed
    //     $rows = [];
    //     $sl = 1;

    //     $project = $mr['store']['name'] ?? '';
    //     $engineerName = $mr['engineer']['name']
    //         ?? trim(($mr['engineer']['first_name'] ?? '') . ' ' . ($mr['engineer']['last_name'] ?? ''));
    //     $mrDate = self::fmtDate($mr['created_at'] ?? null); // change if you prefer approved_date
    //     $mrNumber = $mr['request_number'] ?? '';

    //     $items = $mr['items'] ?? [];
    //     $purchaseRequests = $mr['purchase_requests'] ?? [];
    //     $stockTransfers = $mr['stock_transfers'] ?? [];


    //     // total received from PR/LPO per mr_item_id
    //     $totalReceivedByMrItemId = self::computeTotalReceivedByMrItemId($purchaseRequests);

    //     // total received from stock transfers per product_id
    //     $totalReceivedByProductId = self::computeTotalReceivedFromStockTransfers($stockTransfers);

    //     foreach ($items as $mrItem) {
    //         $mrItemId = $mrItem['id'];
    //         $mrQty = (float) ($mrItem['quantity'] ?? 0);
    //         $product = $mrItem['product'] ?? [];
    //         $productId = $product['id'] ?? null;
    //         $catId = $product['cat_id'] ?? '';
    //         $category = $product['category_name'] ?? '';
    //         $itemDesc = $product['item'] ?? '';
    //         $brand = $product['brand_name'] ?? '';
    //         $unit = $product['symbol'] ?? '';

    //         $mrTotalReceived = (float) ($totalReceivedByMrItemId[$mrItemId] ?? 0.0);
    //         $stockReceived = (float) ($totalReceivedByProductId[$productId] ?? 0.0);

    //         $mrBalance = $mrQty - ($stockReceived);
    //         // Gather all PR items that belong to this MR item
    //         $prContexts = self::findPrContextsForMrItem($purchaseRequests, $mrItemId);

    //         if (empty($prContexts)) {
    //             // No PR yet: emit a single MR-only row
    //             $rows[] = [
    //                 'SL NO' => $sl++,
    //                 'PROJECT' => $project,
    //                 'ENGINEER NAME' => $engineerName,
    //                 'MR DATE' => $mrDate,
    //                 'MR NUMBER' => $mrNumber,
    //                 'CAT ID' => $catId,
    //                 'CATEGORY' => $category,
    //                 'ITEM DESCRIPTION' => $itemDesc,
    //                 'BRAND' => $brand,
    //                 'UNIT' => $unit,
    //                 'MR QUANTITY' => self::nz($mrQty),
    //                 'PR NUMBER' => '',
    //                 'PR DATE' => '',
    //                 'PR QUANTITY' => '',
    //                 'LPO NUMBER' => '',
    //                 'LPO DATE' => '',
    //                 'SUPPLIER NAME' => '',
    //                 'LPO QUANTITY' => '',
    //                 'QUANTITY RECEIVED FROM LPO' => 0,
    //                 'LPO BALANCE QUANTITY' => 0,
    //                 'MR BALANCE QUANTITY' => self::nz($mrBalance),
    //             ];
    //             continue;
    //         }

    //         // For each PR item context, emit rows per LPO item; if no LPOs, emit PR-only row
    //         foreach ($prContexts as $ctx) {
    //             $pr = $ctx['pr'];
    //             $prItem = $ctx['pr_item'];
    //             $prNumber = $pr['purchase_request_number'] ?? '';
    //             $prDate = self::fmtDate($pr['created_datetime'] ?? null);
    //             $prQty = (float) ($prItem['quantity'] ?? 0);

    //             $lpos = $pr['lpos'] ?? [];

    //             // Collect LPO items that point to this PR item
    //             $matchingLpoItems = self::findLpoItemsForPrItem($lpos, $prItem['id']);

    //             if (empty($matchingLpoItems)) {
    //                 // No LPOs yet for this PR item: emit PR-only row
    //                 $rows[] = [
    //                     'SL NO' => $sl++,
    //                     'PROJECT' => $project,
    //                     'ENGINEER NAME' => $engineerName,
    //                     'MR DATE' => $mrDate,
    //                     'MR NUMBER' => $mrNumber,
    //                     'CAT ID' => $catId,
    //                     'CATEGORY' => $category,
    //                     'ITEM DESCRIPTION' => $itemDesc,
    //                     'BRAND' => $brand,
    //                     'UNIT' => $unit,
    //                     'MR QUANTITY' => self::nz($mrQty),
    //                     'PR NUMBER' => $prNumber,
    //                     'PR DATE' => $prDate,
    //                     'PR QUANTITY' => self::nz($prQty),
    //                     'LPO NUMBER' => '',
    //                     'LPO DATE' => '',
    //                     'SUPPLIER NAME' => '',
    //                     'LPO QUANTITY' => 0,
    //                     'QUANTITY RECEIVED FROM LPO' => 0,
    //                     'LPO BALANCE QUANTITY' => 0,
    //                     'MR BALANCE QUANTITY' => self::nz($mrBalance),
    //                 ];
    //                 continue;
    //             }

    //             // Emit rows per LPO item
    //             foreach ($matchingLpoItems as $pair) {
    //                 $lpo = $pair['lpo'];
    //                 $lpoItem = $pair['lpo_item'];

    //                 $lpoNumber = $lpo['lpo_number'] ?? '';
    //                 $lpoDate = self::fmtDate($lpo['date'] ?? null);
    //                 $supplier = $lpo['supplier']['name'] ?? ''; // ensure you eager-load: purchaseRequests.lpos.supplier
    //                 $lpoQty = (float) ($lpoItem['requested_quantity'] ?? 0);

    //                 // Received for THIS LPO item: prefer shipments sum; fallback to lpo_item.received_quantity
    //                 $receivedForThisLpoItem = self::sumShipmentDeliveredForLpoItem($lpo['shipments'] ?? [], $lpoItem['id']);
    //                 if ($receivedForThisLpoItem === 0.0) {
    //                     $receivedForThisLpoItem = (float) ($lpoItem['received_quantity'] ?? 0);
    //                 }
    //                 $lpoBalance = $lpoQty - $receivedForThisLpoItem;

    //                 $rows[] = [
    //                     'SL NO' => $sl++,
    //                     'PROJECT' => $project,
    //                     'ENGINEER NAME' => $engineerName,
    //                     'MR DATE' => $mrDate,
    //                     'MR NUMBER' => $mrNumber,
    //                     'CAT ID' => $catId,
    //                     'CATEGORY' => $category,
    //                     'ITEM DESCRIPTION' => $itemDesc,
    //                     'BRAND' => $brand,
    //                     'UNIT' => $unit,
    //                     'MR QUANTITY' => self::nz($mrQty),
    //                     'PR NUMBER' => $prNumber,
    //                     'PR DATE' => $prDate,
    //                     'PR QUANTITY' => self::nz($prQty),
    //                     'LPO NUMBER' => $lpoNumber,
    //                     'LPO DATE' => $lpoDate,
    //                     'SUPPLIER NAME' => $supplier,
    //                     'LPO QUANTITY' => self::nz($lpoQty),
    //                     'QUANTITY RECEIVED FROM LPO' => self::nz($receivedForThisLpoItem),
    //                     'LPO BALANCE QUANTITY' => self::nz($lpoBalance),
    //                     'MR BALANCE QUANTITY' => self::nz($mrBalance),
    //                 ];
    //             }
    //         }
    //     }

    //     return $rows;
    // }
    public static function toExcelRows(MaterialRequest $materialRequest): array
    {
        $mr = $materialRequest->toArray(); // snake_case keys guaranteed
        $rows = [];
        $sl = 1;

        $project = $mr['store']['name'] ?? '';
        $engineerName = $mr['engineer']['name']
            ?? trim(($mr['engineer']['first_name'] ?? '') . ' ' . ($mr['engineer']['last_name'] ?? ''));
        $mrDate = self::fmtDate($mr['created_at'] ?? null);
        $mrApprovedDate = self::fmtDate($mr['approved_date'] ?? null);   // ✅ new
        $mrNumber = $mr['request_number'] ?? '';

        $items = $mr['items'] ?? [];
        $purchaseRequests = $mr['purchase_requests'] ?? [];
        $stockTransfers = $mr['stock_transfers'] ?? [];

        // total received from PR/LPO per mr_item_id
        $totalReceivedByMrItemId = self::computeTotalReceivedByMrItemId($purchaseRequests);

        // total received from stock transfers per product_id
        $totalReceivedByProductId = self::computeTotalReceivedFromStockTransfers($stockTransfers);

        foreach ($items as $mrItem) {
            $mrItemId = $mrItem['id'];
            $mrQty = (float) ($mrItem['quantity'] ?? 0);
            $product = $mrItem['product'] ?? [];
            $productId = $product['id'] ?? null;
            $catId = $product['cat_id'] ?? '';
            $category = $product['category_name'] ?? '';
            $itemDesc = $product['item'] ?? '';
            $brand = $product['brand_name'] ?? '';
            $unit = $product['symbol'] ?? '';

            $mrTotalReceived = (float) ($totalReceivedByMrItemId[$mrItemId] ?? 0.0);
            $stockReceived = (float) ($totalReceivedByProductId[$productId] ?? 0.0);

            // ✅ calculate issued_from_stock = MR qty - sum(PR item qty)
            $prItemQty = 0.0;
            foreach ($purchaseRequests as $pr) {
                foreach (($pr['items'] ?? []) as $prItem) {
                    if (($prItem['material_request_item_id'] ?? null) === $mrItemId) {
                        $prItemQty += (float) ($prItem['quantity'] ?? 0);
                    }
                }
            }
            $issuedFromStock = $mrQty - $prItemQty;

            $mrBalance = $mrQty - ($stockReceived);

            // Gather all PR items that belong to this MR item
            $prContexts = self::findPrContextsForMrItem($purchaseRequests, $mrItemId);

            if (empty($prContexts)) {
                // No PR yet: emit a single MR-only row
                $rows[] = [
                    'SL NO' => $sl++,
                    'PROJECT' => $project,
                    'ENGINEER NAME' => $engineerName,
                    'MR DATE' => $mrDate,
                    'MR NUMBER' => $mrNumber,
                    'CAT ID' => $catId,
                    'CATEGORY' => $category,
                    'ITEM DESCRIPTION' => $itemDesc,
                    'BRAND' => $brand,
                    'UNIT' => $unit,
                    'MR QUANTITY' => self::nz($mrQty),
                    'MR APPROVED DATE' => $mrApprovedDate,
                    'QUANTITY ISSUED FROM STORE STOCK' => self::nz($issuedFromStock),
                    'PR NUMBER' => '',
                    'PR DATE' => '',
                    'PR QUANTITY' => '',
                    'LPO NUMBER' => '',
                    'LPO DATE' => '',
                    'SUPPLIER NAME' => '',
                    'LPO QUANTITY' => '',
                    'QUANTITY RECEIVED FROM LPO' => 0,
                    'LPO BALANCE QUANTITY' => 0,
                    'MR BALANCE QUANTITY' => self::nz($mrBalance),
                ];
                continue;
            }

            // For each PR item context, emit rows per LPO item; if no LPOs, emit PR-only row
            foreach ($prContexts as $ctx) {
                $pr = $ctx['pr'];
                $prItem = $ctx['pr_item'];
                $prNumber = $pr['purchase_request_number'] ?? '';
                $prDate = self::fmtDate($pr['created_datetime'] ?? null);
                $prQty = (float) ($prItem['quantity'] ?? 0);

                $lpos = $pr['lpos'] ?? [];

                // Collect LPO items that point to this PR item
                $matchingLpoItems = self::findLpoItemsForPrItem($lpos, $prItem['id']);

                if (empty($matchingLpoItems)) {
                    // No LPOs yet for this PR item: emit PR-only row
                    $rows[] = [
                        'SL NO' => $sl++,
                        'PROJECT' => $project,
                        'ENGINEER NAME' => $engineerName,
                        'MR DATE' => $mrDate,
                        'MR NUMBER' => $mrNumber,
                        'CAT ID' => $catId,
                        'CATEGORY' => $category,
                        'ITEM DESCRIPTION' => $itemDesc,
                        'BRAND' => $brand,
                        'UNIT' => $unit,
                        'MR QUANTITY' => self::nz($mrQty),
                        'MR APPROVED DATE' => $mrApprovedDate,
                        'QUANTITY ISSUED FROM STORE STOCK' => self::nz($issuedFromStock),
                        'PR NUMBER' => $prNumber,
                        'PR DATE' => $prDate,
                        'PR QUANTITY' => self::nz($prQty),
                        'LPO NUMBER' => '',
                        'LPO DATE' => '',
                        'SUPPLIER NAME' => '',
                        'LPO QUANTITY' => 0,
                        'QUANTITY RECEIVED FROM LPO' => 0,
                        'LPO BALANCE QUANTITY' => 0,
                        'MR BALANCE QUANTITY' => self::nz($mrBalance),
                    ];
                    continue;
                }

                // Emit rows per LPO item
                foreach ($matchingLpoItems as $pair) {
                    $lpo = $pair['lpo'];
                    $lpoItem = $pair['lpo_item'];

                    $lpoNumber = $lpo['lpo_number'] ?? '';
                    $lpoDate = self::fmtDate($lpo['date'] ?? null);
                    $supplier = $lpo['supplier']['name'] ?? '';
                    $lpoQty = (float) ($lpoItem['requested_quantity'] ?? 0);

                    // Received for THIS LPO item
                    $receivedForThisLpoItem = self::sumShipmentDeliveredForLpoItem($lpo['shipments'] ?? [], $lpoItem['id']);
                    if ($receivedForThisLpoItem === 0.0) {
                        $receivedForThisLpoItem = (float) ($lpoItem['received_quantity'] ?? 0);
                    }
                    $lpoBalance = $lpoQty - $receivedForThisLpoItem;

                    $rows[] = [
                        'SL NO' => $sl++,
                        'PROJECT' => $project,
                        'ENGINEER NAME' => $engineerName,
                        'MR DATE' => $mrDate,
                        'MR NUMBER' => $mrNumber,
                        'CAT ID' => $catId,
                        'CATEGORY' => $category,
                        'ITEM DESCRIPTION' => $itemDesc,
                        'BRAND' => $brand,
                        'UNIT' => $unit,
                        'MR QUANTITY' => self::nz($mrQty),
                        'MR APPROVED DATE' => $mrApprovedDate,
                        'QUANTITY ISSUED FROM STORE STOCK' => self::nz($issuedFromStock),
                        'PR NUMBER' => $prNumber,
                        'PR DATE' => $prDate,
                        'PR QUANTITY' => self::nz($prQty),
                        'LPO NUMBER' => $lpoNumber,
                        'LPO DATE' => $lpoDate,
                        'SUPPLIER NAME' => $supplier,
                        'LPO QUANTITY' => self::nz($lpoQty),
                        'QUANTITY RECEIVED FROM LPO' => self::nz($receivedForThisLpoItem),
                        'LPO BALANCE QUANTITY' => self::nz($lpoBalance),
                        'MR BALANCE QUANTITY' => self::nz($mrBalance),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Compute total received per MR item across all PR/LPO/Shipments.
     * Keyed by material_request_item_id.
     */
    private static function computeTotalReceivedByMrItemId(array $purchaseRequests): array
    {
        $totals = []; // mr_item_id => received float
        foreach ($purchaseRequests as $pr) {
            $prItems = $pr['items'] ?? [];
            $lpos = $pr['lpos'] ?? [];

            // Index shipments received by lpo_item_id for quick lookup
            $shipReceivedByLpoItemId = self::indexShipmentSumsByLpoItemId($lpos);

            foreach ($prItems as $prItem) {
                $mrItemId = $prItem['material_request_item_id'] ?? null;
                if (!$mrItemId)
                    continue;

                // Sum all LPO items belonging to this PR item
                $received = 0.0;

                foreach ($lpos as $lpo) {
                    $lpoItems = $lpo['items'] ?? [];
                    foreach ($lpoItems as $lpoItem) {
                        if (($lpoItem['pr_item_id'] ?? null) !== ($prItem['id'] ?? null)) {
                            continue;
                        }
                        $lpoItemId = $lpoItem['id'] ?? null;
                        $fromShip = $lpoItemId ? ($shipReceivedByLpoItemId[$lpoItemId] ?? 0.0) : 0.0;
                        $received += ($fromShip > 0.0)
                            ? $fromShip
                            : (float) ($lpoItem['received_quantity'] ?? 0);
                    }
                }

                $totals[$mrItemId] = ($totals[$mrItemId] ?? 0.0) + $received;
            }
        }
        return $totals;
    }

    /**
     * Build an index: lpo_item_id => sum(quantity_delivered)
     */
    private static function indexShipmentSumsByLpoItemId(array $lpos): array
    {
        $idx = [];
        foreach ($lpos as $lpo) {
            $shipments = $lpo['shipments'] ?? [];
            foreach ($shipments as $ship) {
                $shipItems = $ship['items'] ?? [];
                foreach ($shipItems as $si) {
                    $lpoItemId = $si['lpo_item_id'] ?? null;
                    if (!$lpoItemId)
                        continue;
                    $idx[$lpoItemId] = ($idx[$lpoItemId] ?? 0.0) + (float) ($si['quantity_delivered'] ?? 0);
                }
            }
        }
        return $idx;
    }

    /**
     * For a given MR item, collect all [pr, pr_item] contexts that point to it.
     * @return array<int, array{pr: array, pr_item: array}>
     */
    private static function findPrContextsForMrItem(array $purchaseRequests, int $mrItemId): array
    {
        $out = [];
        foreach ($purchaseRequests as $pr) {
            foreach (($pr['items'] ?? []) as $prItem) {
                if (($prItem['material_request_item_id'] ?? null) === $mrItemId) {
                    $out[] = ['pr' => $pr, 'pr_item' => $prItem];
                }
            }
        }
        return $out;
    }

    /**
     * For a given PR item id, find all LPO items referencing it, with their parent LPO.
     * @return array<int, array{lpo: array, lpo_item: array}>
     */
    private static function findLpoItemsForPrItem(array $lpos, int $prItemId): array
    {
        $pairs = [];
        foreach ($lpos as $lpo) {
            foreach (($lpo['items'] ?? []) as $lpoItem) {
                if (($lpoItem['pr_item_id'] ?? null) === $prItemId) {
                    $pairs[] = ['lpo' => $lpo, 'lpo_item' => $lpoItem];
                }
            }
        }
        return $pairs;
    }

    /**
     * Sum shipments delivered for a specific LPO item id (if shipments provided inline).
     */
    private static function sumShipmentDeliveredForLpoItem(array $shipments, int $lpoItemId): float
    {
        $sum = 0.0;
        foreach ($shipments as $ship) {
            foreach (($ship['items'] ?? []) as $si) {
                if (($si['lpo_item_id'] ?? null) === $lpoItemId) {
                    $sum += (float) ($si['quantity_delivered'] ?? 0);
                }
            }
        }
        return $sum;
    }

    private static function fmtDate(?string $iso): string
    {
        if (!$iso)
            return '';
        try {
            return Carbon::parse($iso)->format('d-m-Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function nz($v)
    {
        // normalize small negative zeros etc.
        $n = (float) $v;
        return abs($n) < 1e-9 ? 0 : $n;
    }

    /**
     * Compute total received quantities from stock transfers by product_id.
     */
    private static function computeTotalReceivedFromStockTransfers(array $stockTransfers): array
    {
        $totals = []; // product_id => sum(received)
        foreach ($stockTransfers as $st) {
            foreach (($st['stock_transfer_items'] ?? []) as $sti) {
                $pid = $sti['product_id'] ?? null;
                if (!$pid)
                    continue;
                $received = (float) ($sti['received_quantity'] ?? 0);
                $totals[$pid] = ($totals[$pid] ?? 0.0) + $received;
            }
        }
        return $totals;
    }

}
