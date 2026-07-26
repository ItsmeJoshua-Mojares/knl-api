<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, ActivityLog, InventoryLog};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    /**
     * POST /api/admin/import/products
     *
     * Accepts a CSV file with columns: sku, stock_quantity, price (optional), cost_price (optional)
     * Updates existing products by SKU.
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');

        if ($handle === false) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read the CSV file.',
            ], 422);
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'CSV file is empty or has no header row.',
            ], 422);
        }

        $header = array_map('strtolower', array_map('trim', $header));
        $skuIndex = array_search('sku', $header);
        $stockIndex = array_search('stock_quantity', $header);

        if ($skuIndex === false || $stockIndex === false) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'CSV must have "sku" and "stock_quantity" columns.',
            ], 422);
        }

        $priceIndex = array_search('price', $header);
        $costPriceIndex = array_search('cost_price', $header);

        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        DB::transaction(function () use ($handle, $skuIndex, $stockIndex, $priceIndex, $costPriceIndex, &$updated, &$skipped, &$errors, &$rowNum) {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $sku = trim($row[$skuIndex] ?? '');

                if (empty($sku)) {
                    $skipped++;
                    continue;
                }

                $product = Product::where('sku', $sku)->first();

                if (!$product) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                    $skipped++;
                    continue;
                }

                $newStock = (int) ($row[$stockIndex] ?? $product->stock_quantity);
                $quantityChange = $newStock - $product->stock_quantity;

                $updates = ['stock_quantity' => $newStock];

                if ($priceIndex !== false && isset($row[$priceIndex]) && $row[$priceIndex] !== '') {
                    $updates['price'] = (float) $row[$priceIndex];
                }

                if ($costPriceIndex !== false && isset($row[$costPriceIndex]) && $row[$costPriceIndex] !== '') {
                    $updates['cost_price'] = (float) $row[$costPriceIndex];
                }

                $product->update($updates);

                if ($quantityChange !== 0) {
                    InventoryLog::create([
                        'product_id'      => $product->id,
                        'user_id'         => $request->user()->id,
                        'type'            => 'import',
                        'quantity_before' => $product->stock_quantity - $quantityChange,
                        'quantity_change' => $quantityChange,
                        'quantity_after'  => $newStock,
                        'note'            => 'CSV bulk import',
                    ]);
                }

                ActivityLog::record($product, 'imported', ['sku' => $sku, 'stock' => $newStock]);
                $updated++;
            }
        });

        fclose($handle);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'subject_type' => Product::class,
            'subject_id'   => 0,
            'event'        => 'bulk_import',
            'properties'   => ['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors],
            'ip_address'   => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} product(s) updated, {$skipped} skipped.",
            'data'    => [
                'updated' => $updated,
                'skipped' => $skipped,
                'errors'  => $errors,
            ],
        ]);
    }
}
