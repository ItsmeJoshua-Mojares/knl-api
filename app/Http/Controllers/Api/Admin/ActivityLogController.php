<?php
// app/Http/Controllers/Api/Admin/ActivityLogController.php
// ─────────────────────────────────────────────────────────────
// Read-only endpoint — activity logs are written automatically
// by LogAdminActivity middleware (Phase 5, Step 2) and the
// explicit ActivityLog::record() calls in each admin controller.
// This controller only exposes a way to BROWSE that audit trail.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\{JsonResponse, Request};

class ActivityLogController extends Controller
{
    /**
     * GET /api/admin/activity-log
     *
     * Filters: ?subject_type=Product&event=updated&user_id=3
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user:id,first_name,last_name');

        if ($request->filled('subject_type')) {
            $query->where('subject_type', 'like', "%{$request->subject_type}%");
        }
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 30));

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
