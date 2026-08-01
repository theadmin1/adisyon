<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiTrafficLog;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminApiTrafficController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $initialLogs = $this->query($filters)
            ->latest('id')
            ->limit(75)
            ->get()
            ->map(fn (ApiTrafficLog $log): array => $this->present($log))
            ->values();

        return view('admin.api-traffic.index', [
            'branches' => Branch::orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $filters,
            'initialLogs' => $initialLogs,
            'stats' => $this->stats($filters),
            'pollInterval' => (int) config('api-monitor.poll_interval_ms', 1500),
            'retentionDays' => (int) config('api-monitor.retention_days', 7),
        ]);
    }

    public function stream(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $afterId = max(0, $request->integer('after_id'));
        $query = $this->query($filters);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId)->oldest('id');
        } else {
            $query->latest('id');
        }

        $logs = $query
            ->limit(100)
            ->get()
            ->map(fn (ApiTrafficLog $log): array => $this->present($log))
            ->values();

        return response()->json([
            'status' => 'online',
            'server_time' => now()->toIso8601String(),
            'logs' => $logs,
            'stats' => $this->stats($filters),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
        return ApiTrafficLog::query()
            ->with('branch:id,name,code')
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['method'] ?? null, fn (Builder $query, $method) => $query->where('method', $method))
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $range = match ($status) {
                    '2xx' => [200, 299],
                    '3xx' => [300, 399],
                    '4xx' => [400, 499],
                    '5xx' => [500, 599],
                    default => null,
                };

                if ($range !== null) {
                    $query->whereBetween('status_code', $range);
                }
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('path', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%")
                        ->orWhere('restaurant_id', 'like', "%{$search}%")
                        ->orWhere('staff_name', 'like', "%{$search}%")
                        ->orWhere('request_id', 'like', "%{$search}%");
                });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float>
     */
    private function stats(array $filters): array
    {
        $lastDay = $this->query($filters)->where('occurred_at', '>=', now()->subDay());

        return [
            'requests_24h' => (clone $lastDay)->count(),
            'errors_24h' => (clone $lastDay)->where('status_code', '>=', 400)->count(),
            'average_ms_24h' => (int) round((float) ((clone $lastDay)->avg('duration_ms') ?? 0)),
            'requests_last_minute' => $this->query($filters)->where('occurred_at', '>=', now()->subMinute())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE'],
            'status' => ['nullable', 'in:2xx,3xx,4xx,5xx'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ApiTrafficLog $log): array
    {
        return [
            'id' => $log->id,
            'request_id' => $log->request_id,
            'method' => $log->method,
            'path' => $log->path,
            'route_name' => $log->route_name,
            'status_code' => $log->status_code,
            'duration_ms' => $log->duration_ms,
            'request_size' => $log->request_size,
            'response_size' => $log->response_size,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'restaurant_id' => $log->restaurant_id,
            'user_name' => $log->user_name,
            'staff_name' => $log->staff_name,
            'branch' => $log->branch ? [
                'id' => $log->branch->id,
                'name' => $log->branch->name,
                'code' => $log->branch->code,
            ] : null,
            'request_headers' => $log->request_headers,
            'request_payload' => $log->request_payload,
            'response_payload' => $log->response_payload,
            'occurred_at' => $log->occurred_at?->toIso8601String(),
            'occurred_at_label' => $log->occurred_at?->format('d.m.Y H:i:s.v'),
        ];
    }
}
