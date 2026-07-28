<?php

namespace App\Services;

use App\Models\AuditLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        ?string $category = null,
        ?int $branchId = null
    ): ?AuditLog {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        try {
            $request = request();
            $staffId = $request->hasSession() ? $request->session()->get('active_staff_id') : null;
            $staffName = $request->hasSession() ? $request->session()->get('active_staff_name') : null;
            $ipAddress = $request->ip();
            $requestId = $request->attributes->get('_audit_request_id');

            if (! is_string($requestId) || $requestId === '') {
                $requestId = (string) Str::uuid();
                $request->attributes->set('_audit_request_id', $requestId);
            }

            return AuditLog::create([
                'actor_user_id' => $user->getAuthIdentifier(),
                'actor_staff_profile_id' => is_numeric($staffId) ? (int) $staffId : null,
                'branch_id' => $branchId ?? $this->subjectBranchId($subject) ?? $user->branch_id,
                'actor_user_name' => Str::limit((string) $user->name, 255, ''),
                'actor_staff_name' => $staffName ? Str::limit((string) $staffName, 255, '') : null,
                'action' => Str::limit($action, 255, ''),
                'category' => Str::limit($category ?? Str::before($action, '.'), 32, ''),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $this->subjectLabel($subject),
                'description' => $description ? Str::limit($description, 2000) : null,
                'old_values' => $oldValues === [] ? null : $this->sanitize($oldValues),
                'new_values' => $newValues === [] ? null : $this->sanitize($newValues),
                'ip_address' => filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null,
                'user_agent' => Str::limit((string) $request->userAgent(), 2000),
                'request_method' => Str::limit($request->method(), 10, ''),
                'request_path' => Str::limit($request->path(), 1000, ''),
                'route_name' => $request->route()?->getName(),
                'request_id' => $requestId,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Audit log could not be written.', [
                'action' => $action,
                'actor_user_id' => $user->getAuthIdentifier(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function subjectBranchId(?Model $subject): ?int
    {
        if ($subject === null || ! isset($subject->branch_id) || ! is_numeric($subject->branch_id)) {
            return null;
        }

        return (int) $subject->branch_id;
    }

    private function subjectLabel(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (['name', 'check_number', 'shift_number', 'device_code', 'role_name', 'key', 'channel'] as $attribute) {
            if (! empty($subject->{$attribute})) {
                return Str::limit((string) $subject->{$attribute}, 255, '');
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|passphrase|secret|token|api[_-]?key|private[_-]?key|pin|credential|remember[_-]?token|license[_-]?key)/i', $key) === 1;
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_string($value)) {
            if (str_starts_with($value, 'data:') || strlen($value) > 10000) {
                return '[LARGE_DATA_REDACTED]';
            }

            return Str::limit($value, 2000);
        }

        if (is_object($value)) {
            return Str::limit((string) $value, 2000);
        }

        return $value;
    }
}
