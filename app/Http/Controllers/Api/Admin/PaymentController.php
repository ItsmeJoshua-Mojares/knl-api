<?php
// app/Http/Controllers/Api/Admin/PaymentController.php
// ─────────────────────────────────────────────────────────────
// These endpoints are used by the Phase 5 admin dashboard to
// manually verify GCash/Maya/bank transfer payments.
// All routes here require auth:api + role:admin middleware.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * GET /api/admin/payments?status=pending
     *
     * List payments awaiting verification — the admin's "to-do" queue.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with('order:id,order_number,user_id,grand_total')
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->filled('method'), fn ($q) =>
                $q->where('payment_method', $request->method)
            )
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $payments]);
    }

    /**
     * POST /api/admin/payments/{payment}/verify
     *
     * Admin confirms they checked their GCash/Maya app and the
     * reference number matches a real incoming payment.
     */
    public function verify(Request $request, Payment $payment): JsonResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $updated = $this->paymentService->markAsPaid($payment, $request->note);

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully.',
            'data'    => ['payment' => $updated],
        ]);
    }

    /**
     * POST /api/admin/payments/{payment}/reject
     *
     * Admin couldn't find a matching transaction — reject it.
     */
    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $updated = $this->paymentService->markAsFailed($payment, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Payment rejected.',
            'data'    => ['payment' => $updated],
        ]);
    }

    /**
     * POST /api/admin/payments/{payment}/refund
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $payment->amount,
            'reason' => 'required|string|max:500',
        ]);

        $updated = $this->paymentService->refund(
            $payment,
            (float) $request->amount,
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund processed.',
            'data'    => ['payment' => $updated],
        ]);
    }
}
