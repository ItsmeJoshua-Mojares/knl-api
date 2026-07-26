<?php
// app/Http/Controllers/Api/Admin/CustomerController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\{JsonResponse, Request};

class CustomerController extends Controller
{
    /**
     * GET /api/admin/customers
     *
     * List customers (role 'customer' only — admins manage staff
     * separately under Roles & Permissions in a future phase).
     * withCount/withSum compute aggregate stats in the SAME query
     * instead of N+1 queries per customer.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::whereHas('role', fn ($q) => $q->where('name', 'customer'))
            ->withCount('orders')
            ->withSum('orders as total_spent', 'grand_total');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) =>
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
            );
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $query->orderBy($sortBy, $request->get('sort_dir', 'desc'));

        $customers = $query->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $customers]);
    }

    /**
     * GET /api/admin/customers/{id}
     *
     * Full customer profile with order history — used for the
     * admin's "view customer" detail panel.
     */
    public function show(int $id): JsonResponse
    {
        $customer = User::with([
            'role',
            'addresses',
            'orders' => fn ($q) => $q->latest()->limit(10)->with('items'),
        ])
            ->withCount('orders')
            ->withSum('orders as total_spent', 'grand_total')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => ['customer' => $customer]]);
    }

    /**
     * PUT /api/admin/customers/{id}/toggle-active
     *
     * Suspend or reactivate a customer account.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $customer = User::findOrFail($id);
        $customer->update(['is_active' => !$customer->is_active]);

        return response()->json([
            'success' => true,
            'message' => $customer->is_active ? 'Account reactivated.' : 'Account suspended.',
            'data'    => ['customer' => $customer->fresh()],
        ]);
    }
}
