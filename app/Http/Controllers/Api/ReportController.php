<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::with('user:id,name,role')
            ->latest()
            ->paginate(15);

        $items = collect($reports->items())->map(fn($r) => $this->publicReport($r));

        return $this->success([
            'reports'  => $items,
            'has_more' => $reports->hasMorePages(),
            'page'     => $reports->currentPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image'         => ['required', 'image', 'max:5120'],
            'description'   => ['required', 'string', 'min:10', 'max:1000'],
            'latitude'      => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'     => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'is_anonymous'  => ['sometimes', 'boolean'],
        ]);

        $path = $request->file('image')->store('reports', 'public');

        $report = $request->user()->reports()->create([
            'image_url'     => Storage::disk('public')->url($path),
            'description'   => $validated['description'],
            'latitude'      => $validated['latitude'] ?? null,
            'longitude'     => $validated['longitude'] ?? null,
            'location_name' => $validated['location_name'] ?? null,
            'status'        => 'pending',
            'is_anonymous'  => $validated['is_anonymous'] ?? false,
        ]);

        $report->load('user:id,name,role');

        return $this->success($this->publicReport($report), 'Report submitted successfully', 201);
    }

    /**
     * Lightweight endpoint for map markers.
     * Returns only the fields needed to render a pin.
     * Supports bounding-box filtering so clients only fetch visible area.
     */
    public function mapIndex(Request $request): JsonResponse
    {
        $request->validate([
            'min_lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'max_lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'min_lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'max_lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'status'  => ['sometimes', 'in:pending,in_progress,resolved'],
        ]);

        $reports = Report::select([
                'id', 'latitude', 'longitude',
                'status', 'image_url',
                'description', 'location_name', 'is_anonymous',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('min_lat'), fn($q) =>
                $q->where('latitude', '>=', $request->min_lat)
            )
            ->when($request->filled('max_lat'), fn($q) =>
                $q->where('latitude', '<=', $request->max_lat)
            )
            ->when($request->filled('min_lng'), fn($q) =>
                $q->where('longitude', '>=', $request->min_lng)
            )
            ->when($request->filled('max_lng'), fn($q) =>
                $q->where('longitude', '<=', $request->max_lng)
            )
            ->when($request->filled('status'), fn($q) =>
                $q->where('status', $request->status)
            )
            ->latest()
            ->limit(500) // performance cap — clustering handles high density
            ->get();

        return $this->success($reports);
    }

    public function show(Report $report): JsonResponse
    {
        $report->load('user:id,name,role', 'comments.user:id,name,role');
        return $this->success($this->publicReport($report));
    }

    public function update(Request $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id) {
            return $this->error('You can only edit your own reports', 403);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $report->update(['description' => $validated['description']]);

        return $this->success($this->publicReport($report->fresh()->load('user:id,name,role')), 'Report updated');
    }

    public function destroy(Request $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id) {
            return $this->error('You can only delete your own reports', 403);
        }

        $report->delete(); // soft delete

        return $this->success(null, 'Report deleted');
    }

    public function updateStatus(Request $request, Report $report): JsonResponse
    {
        if (! $request->user()->isAuthority()) {
            return $this->error('Only authorities can update report status', 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,resolved'],
        ]);

        $oldStatus = $report->status;
        $report->update(['status' => $validated['status']]);

        ActivityLog::record(
            $request->user(),
            'status_updated',
            'report',
            $report->id,
            "{$request->user()->name} changed report #{$report->id} status from {$oldStatus} to {$validated['status']}",
            ['old_status' => $oldStatus, 'new_status' => $validated['status']]
        );

        return $this->success($this->publicReport($report), 'Status updated');
    }

    /**
     * Mask the user field for anonymous reports on the public API.
     * The is_anonymous flag is always exposed so clients can render the shield.
     */
    private function publicReport(Report $report): array
    {
        $data = $report->toArray();

        if ($report->is_anonymous) {
            $data['user'] = null;
        }

        return $data;
    }
}