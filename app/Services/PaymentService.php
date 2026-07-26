<?php
// app/Services/PaymentService.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Manual payment verification (the GCash/Maya reality)
//
// Unlike Stripe or PayPal, GCash and Maya in the Philippines
// often don't offer self-service API integration for small
// merchants — many businesses verify payments MANUALLY:
//   1. Customer sends money to the merchant's GCash/Maya number
//   2. Customer enters the transaction reference number at checkout
//   3. Admin manually checks their GCash/Maya app for that reference
//   4. Admin marks the payment as 'paid' in the admin dashboard
//
// This service models that real-world workflow. The "verification"
// at checkout time is really just RECORDING the reference number —
// actual confirmation happens later via the admin dashboard
// (built in Phase 5).
//
// For COD, there's nothing to verify upfront — payment happens
// on delivery, so the order proceeds with status 'pending'.
//
// If you later integrate a real payment gateway (Xendit, PayMongo,
// which DO offer Philippine GCash/Maya APIs), this is the ONLY
// file you'd need to change — the rest of the app doesn't know
// or care how payment verification actually happens internally.
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Record/verify a payment for an order based on the method used.
     * Called right after order creation in OrderService, or
     * separately if payment is confirmed after the fact.
     */
    public function recordPayment(Order $order, array $paymentData): Payment
    {
        return match ($paymentData['payment_method']) {
            'gcash', 'maya'   => $this->recordEwalletPayment($order, $paymentData),
            'bank_transfer'   => $this->recordBankTransferPayment($order, $paymentData),
            'cod'             => $this->recordCodPayment($order),
            default           => throw new \InvalidArgumentException('Unsupported payment method.'),
        };
    }

    /**
     * GCash/Maya — store the reference number for manual admin verification.
     * Status stays 'pending' until an admin confirms it in the dashboard.
     */
    private function recordEwalletPayment(Order $order, array $data): Payment
    {
        return $order->payments()->create([
            'payment_method'   => $data['payment_method'],
            'status'           => 'pending', // awaiting manual admin verification
            'amount'           => $order->grand_total,
            'currency'         => 'PHP',
            'transaction_id'   => $data['reference_number'],
            'gateway_response' => [
                'submitted_at'      => now()->toISOString(),
                'reference_number'  => $data['reference_number'],
                'verification_method' => 'manual',
            ],
        ]);
    }

    /**
     * Bank transfer — same manual verification pattern.
     */
    private function recordBankTransferPayment(Order $order, array $data): Payment
    {
        return $order->payments()->create([
            'payment_method'   => 'bank_transfer',
            'status'           => 'pending',
            'amount'           => $order->grand_total,
            'currency'         => 'PHP',
            'transaction_id'   => $data['reference_number'] ?? null,
            'gateway_response' => [
                'submitted_at' => now()->toISOString(),
                'verification_method' => 'manual',
            ],
        ]);
    }

    /**
     * Cash on Delivery — payment happens on delivery, nothing to verify now.
     */
    private function recordCodPayment(Order $order): Payment
    {
        return $order->payments()->create([
            'payment_method' => 'cod',
            'status'         => 'pending', // becomes 'paid' when admin confirms delivery + cash received
            'amount'         => $order->grand_total,
            'currency'       => 'PHP',
        ]);
    }

    /**
     * Admin action: mark a payment as verified/paid.
     * Called from the Phase 5 admin dashboard.
     */
    public function markAsPaid(Payment $payment, ?string $adminNote = null): Payment
    {
        $payment->update([
            'status'  => 'paid',
            'paid_at' => now(),
            'notes'   => $adminNote,
        ]);

        Log::info("Payment #{$payment->id} for order {$payment->order->order_number} marked as paid.");

        return $payment->fresh();
    }

    /**
     * Admin action: reject/fail a payment (e.g. reference number doesn't match).
     */
    public function markAsFailed(Payment $payment, string $reason): Payment
    {
        $payment->update([
            'status' => 'failed',
            'notes'  => $reason,
        ]);

        return $payment->fresh();
    }

    /**
     * Process a refund for a payment.
     */
    public function refund(Payment $payment, float $amount, string $reason): Payment
    {
        $payment->update([
            'status'        => $amount >= (float) $payment->amount ? 'refunded' : 'partially_refunded',
            'refund_amount' => $amount,
            'refunded_at'   => now(),
            'notes'         => $reason,
        ]);

        return $payment->fresh();
    }
}
