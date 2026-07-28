<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DeviceLog;
use App\Models\LoginLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSecurityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $activeTab = $filters['tab'] ?? 'logins';

        $logs = match ($activeTab) {
            'devices' => $this->deviceLogQuery($filters)->paginate(25)->withQueryString(),
            'audits' => $this->auditLogQuery($filters)->paginate(25)->withQueryString(),
            default => $this->loginLogQuery($filters)->paginate(25)->withQueryString(),
        };

        $since = now()->subDay();
        $stats = [
            'logins_24h' => LoginLog::where('logged_in_at', '>=', $since)->count(),
            'unique_ips_24h' => LoginLog::where('logged_in_at', '>=', $since)
                ->whereNotNull('ip_address')
                ->distinct()
                ->count('ip_address'),
            'admin_logins_24h' => LoginLog::where('logged_in_at', '>=', $since)
                ->where('portal', 'admin')
                ->count(),
            'device_events_24h' => DeviceLog::where('created_at', '>=', $since)->count(),
            'audit_events_24h' => AuditLog::where('occurred_at', '>=', $since)->count(),
        ];

        $branches = Branch::orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.logs.index', compact('activeTab', 'branches', 'filters', 'logs', 'stats'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $activeTab = $filters['tab'] ?? 'logins';
        $prefix = match ($activeTab) {
            'devices' => 'cihaz-loglari',
            'audits' => 'islem-loglari',
            default => 'giris-loglari',
        };
        $filename = sprintf('%s-%s.csv', $prefix, now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($activeTab, $filters): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");

            if ($activeTab === 'audits') {
                fputcsv($stream, [
                    'Tarih',
                    'Kategori',
                    'İşlem',
                    'Kullanıcı',
                    'Personel',
                    'Şube',
                    'Hedef',
                    'Açıklama',
                    'Eski Değerler',
                    'Yeni Değerler',
                    'IP Adresi',
                    'İstek Kimliği',
                ], ';');

                foreach ($this->auditLogQuery($filters)->lazy(500) as $log) {
                    fputcsv($stream, array_map($this->csvCell(...), [
                        $log->occurred_at?->format('Y-m-d H:i:s'),
                        $log->category,
                        $log->action,
                        $log->actor_user_name,
                        $log->actor_staff_name,
                        $log->branch?->name,
                        $log->subject_label,
                        $log->description,
                        $this->jsonCell($log->old_values),
                        $this->jsonCell($log->new_values),
                        $log->ip_address,
                        $log->request_id,
                    ]), ';');
                }
            } elseif ($activeTab === 'devices') {
                fputcsv($stream, ['Tarih', 'Şube', 'Cihaz', 'Olay', 'IP Adresi'], ';');

                foreach ($this->deviceLogQuery($filters)->lazy(500) as $log) {
                    fputcsv($stream, array_map($this->csvCell(...), [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->device?->branch?->name,
                        $log->device?->device_code,
                        $log->event_type,
                        $log->ip_address,
                    ]), ';');
                }
            } else {
                fputcsv($stream, [
                    'Giriş Tarihi',
                    'Portal',
                    'Kullanıcı',
                    'E-posta',
                    'Restoran Kodu',
                    'Şube',
                    'IP Adresi',
                    'Beni Hatırla',
                    'Tarayıcı / Cihaz',
                ], ';');

                foreach ($this->loginLogQuery($filters)->lazy(500) as $log) {
                    fputcsv($stream, array_map($this->csvCell(...), [
                        $log->logged_in_at?->format('Y-m-d H:i:s'),
                        $log->portal,
                        $log->user_name,
                        $log->user_email,
                        $log->restaurant_id,
                        $log->branch?->name,
                        $log->ip_address,
                        $log->remember_me ? 'Evet' : 'Hayır',
                        $log->user_agent,
                    ]), ';');
                }
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function loginLogQuery(array $filters): Builder
    {
        return LoginLog::query()
            ->with('branch:id,name,code')
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['portal'] ?? null, fn (Builder $query, $portal) => $query->where('portal', $portal))
            ->when($filters['ip'] ?? null, fn (Builder $query, $ip) => $query->where('ip_address', $ip))
            ->when($filters['search'] ?? null, function (Builder $query, $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('user_name', 'like', "%{$search}%")
                        ->orWhere('user_email', 'like', "%{$search}%")
                        ->orWhere('restaurant_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('logged_in_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('logged_in_at', '<=', $date))
            ->latest('logged_in_at')
            ->latest('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function deviceLogQuery(array $filters): Builder
    {
        return DeviceLog::query()
            ->with(['device:id,branch_id,device_code', 'device.branch:id,name,code'])
            ->when($filters['branch_id'] ?? null, function (Builder $query, $branchId): void {
                $query->whereHas('device', fn (Builder $query) => $query->where('branch_id', $branchId));
            })
            ->when($filters['ip'] ?? null, fn (Builder $query, $ip) => $query->where('ip_address', $ip))
            ->when($filters['search'] ?? null, function (Builder $query, $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('event_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('device', fn (Builder $query) => $query->where('device_code', 'like', "%{$search}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->latest('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function auditLogQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->with([
                'branch:id,name,code',
                'actorUser:id,name,email',
                'actorStaffProfile:id,name',
            ])
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['category'] ?? null, fn (Builder $query, $category) => $query->where('category', $category))
            ->when($filters['ip'] ?? null, fn (Builder $query, $ip) => $query->where('ip_address', $ip))
            ->when($filters['search'] ?? null, function (Builder $query, $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('subject_label', 'like', "%{$search}%")
                        ->orWhere('actor_user_name', 'like', "%{$search}%")
                        ->orWhere('actor_staff_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->latest('occurred_at')
            ->latest('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'tab' => ['nullable', 'in:logins,devices,audits'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'portal' => ['nullable', 'in:restaurant,admin'],
            'category' => ['nullable', 'in:sales,cash,purchasing,inventory,catalog,staff,settings,integration,administration,tables,system'],
            'search' => ['nullable', 'string', 'max:100'],
            'ip' => ['nullable', 'ip'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    private function csvCell(mixed $value): string
    {
        $cell = str_replace(["\r", "\n"], ' ', (string) ($value ?? ''));

        return preg_match('/^[=+\-@]/', $cell) === 1 ? "'{$cell}" : $cell;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private function jsonCell(?array $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
