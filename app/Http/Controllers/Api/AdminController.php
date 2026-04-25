<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends ApiController
{
    public function stats(): JsonResponse
    {
        return $this->success([
            'total_users'   => User::where('role', '!=', 'admin')->count(),
            'total_reports' => Report::count(),
            'pending'       => Report::where('status', 'pending')->count(),
            'in_progress'   => Report::where('status', 'in_progress')->count(),
            'resolved'      => Report::where('status', 'resolved')->count(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $users = User::where('role', '!=', 'admin')
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->latest()
            ->paginate(20);

        return $this->success($users);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:user,authority'],
        ]);

        $oldRole = $user->role;
        $user->update(['role' => $validated['role']]);

        $action = $validated['role'] === 'authority' ? 'user_promoted' : 'user_demoted';
        ActivityLog::record(
            $request->user(),
            $action,
            'user',
            $user->id,
            "{$request->user()->name} changed {$user->name}'s role from {$oldRole} to {$validated['role']}",
            ['old_role' => $oldRole, 'new_role' => $validated['role']]
        );

        return $this->success($user, 'Role updated');
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($user->email_verified_at) {
            $user->update(['email_verified_at' => null]);
            $message = 'User suspended';
            ActivityLog::record(
                $request->user(),
                'user_suspended',
                'user',
                $user->id,
                "{$request->user()->name} suspended {$user->name}"
            );
        } else {
            $user->update(['email_verified_at' => now()]);
            $message = 'User activated';
            ActivityLog::record(
                $request->user(),
                'user_activated',
                'user',
                $user->id,
                "{$request->user()->name} activated {$user->name}"
            );
        }

        return $this->success($user, $message);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        $name = $user->name;
        ActivityLog::record(
            $request->user(),
            'user_deleted',
            'user',
            $user->id,
            "{$request->user()->name} deleted user {$name}"
        );

        $user->delete();
        return $this->success(null, 'User deleted');
    }

    public function reports(Request $request): JsonResponse
    {
        $reports = Report::withTrashed()
            ->with('user:id,name,email,role')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) =>
                $q->where('description', 'like', "%{$request->search}%")
                  ->orWhere('location_name', 'like', "%{$request->search}%")
            )
            ->when($request->trashed === 'only', fn($q) => $q->onlyTrashed())
            ->latest()
            ->paginate(20);

        return $this->success($reports);
    }

    public function deleteReport(Request $request, int $report): JsonResponse
    {
        $reportModel = Report::withTrashed()->findOrFail($report);

        ActivityLog::record(
            $request->user(),
            'report_deleted',
            'report',
            $reportModel->id,
            "{$request->user()->name} permanently deleted report #{$reportModel->id}"
        );

        $reportModel->forceDelete();
        return $this->success(null, 'Report permanently deleted');
    }

    public function restoreReport(Request $request, int $report): JsonResponse
    {
        $reportModel = Report::withTrashed()->findOrFail($report);

        $reportModel->restore();

        ActivityLog::record(
            $request->user(),
            'report_restored',
            'report',
            $reportModel->id,
            "{$request->user()->name} restored report #{$reportModel->id}"
        );

        return $this->success($reportModel, 'Report restored');
    }

    public function analytics(): JsonResponse
    {
        $reportsByDay = Report::select(
                DB::raw("TO_CHAR(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD') as date"),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'pending' then 1 else 0 end) as pending"),
                DB::raw("sum(case when status = 'in_progress' then 1 else 0 end) as in_progress"),
                DB::raw("sum(case when status = 'resolved' then 1 else 0 end) as resolved")
            )
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => [
                'date'        => $r->date,
                'total'       => (int) $r->total,
                'pending'     => (int) $r->pending,
                'in_progress' => (int) $r->in_progress,
                'resolved'    => (int) $r->resolved,
            ]);

        $topLocations = Report::select('location_name', DB::raw('count(*) as count'))
            ->whereNotNull('location_name')
            ->where('location_name', '!=', '')
            ->groupBy('location_name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn($r) => [
                'location' => $r->location_name,
                'count'    => (int) $r->count,
            ]);

        return $this->success([
            'reports_by_day' => $reportsByDay,
            'top_locations'  => $topLocations,
        ]);
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $logs = ActivityLog::with('user:id,name,role')
            ->when($request->action, fn($q) => $q->where('action', $request->action))
            ->latest()
            ->paginate(25);

        return $this->success($logs);
    }
}