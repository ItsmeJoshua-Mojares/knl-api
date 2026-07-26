<?php
// app/Services/OrderService.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Database Transactions
//
// Creating an order touches FOUR tables:
//   1. orders — insert the order row
//   2. order_items — insert one row per cart item
//   3. products — decrement stock for each item
//   4. coupons — increment used_count if a coupon was applied
//
// If step 3 fails (e.g. out of stock), we don't want to have
// already created a half-finished order. DB::transaction() wraps
// all four operations. If anything throws an exception, ALL the
// writes are rolled back automatically, leaving the DB clean.
//
// Without transactions: failed orders leave orphaned rows.
// With transactions: it's all-or-nothing. Atomic.
//
// CONCEPT: Event dispatching
//
// After a successful order, we fire OrderPlaced::class.
// This decouples the OrderService from the email logic.
// OrderService doesn't know or care HOW notifications are sent —
// it just announces "an order was placed."
// Listeners (registered in EventServiceProvider) handle the rest.
// If you later add SMS notifications, you add another Listener
// without touching OrderService at all.
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\{Order, OrderItem, OrderStatusHistory, Product, Coupon, Cart};
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    // ── Constants ─────────────────────────────────────────────
    const SHIPPING_THRESHOLD = 1500.00; // free shipping above this (PHP)
    const SHIPPING_FEE       = 150.00;
    const TAX_RATE           = 0.12;    // 12% VAT

    // Injected via constructor — Laravel resolves this automatically
    public function __construct(private PaymentService $paymentService) {}

    /**
     * Create a new order from the user's cart.
     *
     * @param  int   $userId
     * @param  array $data   Validated checkout form data
     * @return Order
     *
     * @throws ValidationException if stock is insufficient
     * @throws \Exception on any other failure (transaction rolls back)
     */
    public function createOrder(int $userId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $data) {

            // 1. Load the user's cart with products
            $cart = Cart::where('user_id', $userId)
                ->with('items.product')
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw new \Exception('Your cart is empty.');
            }

            // 2. Validate stock for every item BEFORE writing anything
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => "{$cartItem->product_name} is no longer available.",
                    ]);
                }

                if ($product->stock_quantity < $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Only {$product->stock_quantity} unit(s) of {$product->name} are in stock.",
                    ]);
                }
            }

            // 3. Calculate pricing
            $subtotal = $cart->items->sum(fn ($i) => $i->unit_price * $i->quantity);
            $discount = 0.0;
            $coupon   = null;

            if (!empty($data['coupon_code'])) {
                $coupon   = Coupon::where('code', $data['coupon_code'])->valid()->first();
                $discount = $coupon?->calculateDiscount($subtotal) ?? 0.0;
            }

            $shipping   = $subtotal >= self::SHIPPING_THRESHOLD ? 0.0 : self::SHIPPING_FEE;
            $tax        = $subtotal * self::TAX_RATE;
            $grandTotal = $subtotal - $discount + $shipping + $tax;

            // 4. Create the order row
            $order = Order::create([
                'user_id'          => $userId,
                // Shipping address (from checkout form)
                'ship_first_name'  => $data['first_name'],
                'ship_last_name'   => $data['last_name'],
                'ship_phone'       => $data['phone'],
                'ship_address_line1'  => $data['address_line1'],
                'ship_address_line2'  => $data['address_line2'] ?? null,
                'ship_city'        => $data['city'],
                'ship_province'    => $data['province'],
                'ship_postal_code' => $data['postal_code'],
                'ship_country'     => 'Philippines',
                // Pricing
                'subtotal'         => $subtotal,
                'discount_amount'  => $discount,
                'shipping_fee'     => $shipping,
                'tax_amount'       => $tax,
                'grand_total'      => $grandTotal,
                // Coupon
                'coupon_id'        => $coupon?->id,
                'coupon_code'      => $coupon?->code,
                // Meta
                'customer_notes'   => $data['customer_notes'] ?? null,
                'ip_address'       => request()->ip(),
            ]);

            // 5. Create order items (snapshot of cart)
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $product->id,
                    // Snapshot columns — prices locked at order time
                    'product_name'  => $product->name . ($product->specifications['nickname'] ?? ''),
                    'product_sku'   => $product->sku,
                    'product_image' => $product->primaryImage?->image_url,
                    'unit_price'    => $cartItem->unit_price,
                    'quantity'      => $cartItem->quantity,
                    'total_price'   => $cartItem->unit_price * $cartItem->quantity,
                ]);

                // 6. Decrement stock atomically
                // decrement() is a single UPDATE — safe under concurrent load
                $product->decrement('stock_quantity', $cartItem->quantity);

                // Log inventory change
                $product->inventoryLogs()->create([
                    'user_id'         => $userId,
                    'type'            => 'sale',
                    'quantity_before' => $product->stock_quantity + $cartItem->quantity,
                    'quantity_change' => -$cartItem->quantity,
                    'quantity_after'  => $product->stock_quantity,
                    'reference_id'    => $order->id,
                    'note'            => "Order {$order->order_number}",
                ]);
            }

            // 7. Record payment via PaymentService
            // (delegates the method-specific logic — gcash/maya store a
            //  reference number, COD just records intent, etc.)
            $this->paymentService->recordPayment($order, [
                'payment_method'   => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
            ]);

            // 8. Record initial status history
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'note'       => 'Order placed by customer.',
                'changed_by' => $userId,
                'created_at' => now(),
            ]);

            // 9. Increment coupon usage
            $coupon?->incrementUsage();

            // 10. Clear the cart
            $cart->items()->delete();

            // 11. Fire event — listeners handle email, admin notification, etc.
            // This runs AFTER the transaction commits
            event(new OrderPlaced($order->load('items', 'user', 'payment')));

            return $order;
        });
    }

    /**
     * Update order status — called by admins.
     *
     * @throws \Exception if the transition is invalid
     */
    public function updateStatus(Order $order, string $newStatus, ?string $note = null): Order
    {
        $allowed = $this->getAllowedTransitions($order->status);

        if (!in_array($newStatus, $allowed)) {
            throw new \Exception(
                "Cannot transition from '{$order->status}' to '{$newStatus}'."
            );
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        // Set timestamps for specific statuses
        match ($newStatus) {
            'shipped'   => $order->update(['shipped_at'   => now()]),
            'delivered' => $order->update(['delivered_at' => now()]),
            default     => null,
        };

        // Log history
        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'status'     => $newStatus,
            'note'       => $note,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // Fire event — triggers customer email notification
        event(new OrderStatusChanged($order, $oldStatus, $newStatus));

        return $order->fresh();
    }

    /**
     * Cancel an order — customer-initiated.
     */
    public function cancelOrder(Order $order, int $userId): Order
    {
        if (!$order->canBeCancelled()) {
            throw new \Exception('This order cannot be cancelled.');
        }

        if ($order->user_id !== $userId) {
            throw new \Exception('You do not have permission to cancel this order.');
        }

        return DB::transaction(function () use ($order, $userId) {
            // Restore stock for each item
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock_quantity', $item->quantity);
                }
            }

            return $this->updateStatus($order, 'cancelled', 'Cancelled by customer.');
        });
    }

    /**
     * Define valid status transitions.
     * Prevents invalid state changes (e.g. delivered → pending).
     */
    private function getAllowedTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            'pending'    => ['confirmed', 'cancelled'],
            'confirmed'  => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped'    => ['delivered'],
            'delivered'  => ['returned'],
            'returned'   => ['refunded'],
            default      => [],
        };
    }
}
