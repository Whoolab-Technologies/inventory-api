<?php
namespace App\Services\V1;
use Carbon\Carbon;
use App\Models\V1\MaterialRequest;
class MrDeliveryDetailsFormatter
{
    public const HEADERS = [
        'SL NO',
        'DATE',
        'MR NUMBER',
        'PR NUMBER',
        'LPO NUMBER',
        'ENGINEER NAME',
        'CAT ID',
        'CATEGORY',
        'ITEM DESCRIPTION',
        'BRAND',
        'UNIT',
        'DELIVERY NOTE NUMBER',
        'LPO DELIVERY NOTE NUMBER',
        'QUANTITY',
        'SUPPLIER NAME/ STORE DETAILS',
    ];

    /**
     * Convert the eager-loaded $materialRequest (Eloquent model) to flat rows for Excel.
     * Uses snake_case keys throughout.
     *
     * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model $materialRequest
     * @return array<int, array<string, mixed>> rows matching HEADERS order
     */
    public static function toExcelRows($materialRequest): array
    {
        $mr = $materialRequest->toArray();
        $rows = [];
        $sl = 1;

        $engineerName = $mr['engineer']['name'] ?? '';
        $mrNumber = $mr['request_number'] ?? '';

        $items = $mr['items'] ?? [];
        $purchaseRequests = $mr['purchase_requests'] ?? [];
        $stockTransfers = $mr['stock_transfers'] ?? [];

        foreach ($items as $mrItem) {
            $product = $mrItem['product'] ?? [];
            $catId = $product['cat_id'] ?? '';
            $category = $product['category_name'] ?? '';
            $itemDesc = $product['item'] ?? '';
            $brand = $product['brand_name'] ?? '';
            $unit = $product['symbol'] ?? '';
            $mrDate = self::fmtDate($mr['created_at'] ?? null);

            // --- aggregate stock transfer qty ---
            $stockQty = 0;
            foreach ($stockTransfers as $st) {
                foreach (($st['stock_transfer_items'] ?? []) as $sti) {
                    if ($sti['product_id'] === $product['id']) {
                        $stockQty += (float) ($sti['received_quantity'] ?? 0);
                    }
                }
            }

            // --- collect PR/LPO info only for this product ---
            $prNumbers = [];
            $lpoNumbers = [];
            $supplierNames = [];
            $shipmentQty = 0;

            foreach ($purchaseRequests as $pr) {
                $prHasProduct = false;

                // check PR items for this product
                foreach (($pr['items'] ?? []) as $prItem) {
                    if ($prItem['product_id'] === $product['id']) {
                        $prHasProduct = true;
                        break;
                    }
                }

                if ($prHasProduct) {
                    $prNumbers[] = $pr['purchase_request_number'] ?? '';
                }

                foreach (($pr['lpos'] ?? []) as $lpo) {
                    $lpoHasProduct = false;

                    foreach (($lpo['items'] ?? []) as $lpoItem) {
                        if ($lpoItem['product_id'] === $product['id']) {
                            $lpoHasProduct = true;
                            break;
                        }
                    }

                    if ($lpoHasProduct) {
                        $lpoNumbers[] = $lpo['lpo_number'] ?? '';
                        $supplierNames[] = $lpo['supplier']['name'] ?? '';
                    }

                    // shipments for this product
                    foreach (($lpo['shipments'] ?? []) as $shipment) {
                        foreach (($shipment['items'] ?? []) as $shipItem) {
                            if ($shipItem['product_id'] === $product['id']) {
                                $shipmentQty += (float) ($shipItem['quantity_delivered'] ?? 0);
                            }
                        }
                    }
                }
            }

            $rows[] = [
                'SL NO' => $sl++,
                'DATE' => $mrDate,
                'MR NUMBER' => $mrNumber,
                'PR NUMBER' => implode(', ', array_filter($prNumbers)),
                'LPO NUMBER' => implode(', ', array_filter($lpoNumbers)),
                'ENGINEER NAME' => $engineerName,
                'CAT ID' => $catId,
                'CATEGORY' => $category,
                'ITEM DESCRIPTION' => $itemDesc,
                'BRAND' => $brand,
                'UNIT' => $unit,
                'DELIVERY NOTE NUMBER' => '',
                'LPO DELIVERY NOTE NUMBER' => '',
                'QUANTITY' => self::nz($stockQty),
                'SUPPLIER NAME/ STORE DETAILS' => implode(', ', array_filter($supplierNames)) ?: 'CENTRAL STORE',
            ];
        }

        return $rows;
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
        $n = (float) $v;
        return abs($n) < 1e-9 ? 0 : $n;
    }
}
