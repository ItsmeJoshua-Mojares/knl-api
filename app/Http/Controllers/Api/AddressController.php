<?php
// app/Http/Controllers/Api/AddressController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Resource Controller
//
// A resource controller handles all CRUD operations for a
// single model. This follows the standard RESTful pattern:
//   GET    /addresses       → index  (list all)
//   POST   /addresses       → store  (create new)
//   GET    /addresses/{id}  → show   (view one)
//   PUT    /addresses/{id}  → update (edit one)
//   DELETE /addresses/{id}  → destroy (remove one)
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Models\Address;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    /**
     * GET /api/addresses
     *
     * List all addresses for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $addresses = Address::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $addresses,
        ]);
    }

    /**
     * POST /api/addresses
     *
     * Create a new address for the authenticated user.
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // If this is set as default, unset all other defaults first
        if (!empty($data['is_default']) && $data['is_default']) {
            Address::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        $address = Address::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'data'    => $address,
        ], 201);
    }

    /**
     * GET /api/addresses/{id}
     *
     * View a single address (must belong to the user).
     */
    public function show(Address $address): JsonResponse
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $address,
        ]);
    }

    /**
     * PUT /api/addresses/{id}
     *
     * Update an existing address.
     */
    public function update(StoreAddressRequest $request, Address $address): JsonResponse
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
            ], 404);
        }

        $data = $request->validated();

        // If this is set as default, unset all other defaults first
        if (!empty($data['is_default']) && $data['is_default']) {
            Address::where('user_id', auth()->id())
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data'    => $address,
        ]);
    }

    /**
     * DELETE /api/addresses/{id}
     *
     * Delete an address.
     */
    public function destroy(Address $address): JsonResponse
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
            ], 404);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }
}
