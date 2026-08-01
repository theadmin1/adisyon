<?php

namespace App\Http\Middleware;

use App\Models\ApiTrafficLog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CaptureApiTraffic
{
    public const REQUEST_ID_ATTRIBUTE = 'api_traffic_request_id';

    private const REDACTED = '[REDACTED]';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api-monitor.enabled', true)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $requestId = $this->requestId($request);
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->captureException($request, $exception, $requestId, $startedAt);

            throw $exception;
        }

        $response->headers->set('X-Request-ID', $requestId);
        $this->captureResponse($request, $response, $requestId, $startedAt);

        return $response;
    }

    private function captureResponse(Request $request, Response $response, string $requestId, int $startedAt): void
    {
        $content = $response->getContent();
        $responseSize = is_string($content) ? strlen($content) : 0;

        $payload = null;
        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
        } elseif (is_string($content) && str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $payload = json_decode($content, true);
        } elseif (is_string($content) && $content !== '') {
            $payload = ['text' => $content];
        }

        $this->persist(
            $request,
            $requestId,
            $response->getStatusCode(),
            $startedAt,
            $payload,
            $responseSize,
        );
    }

    private function captureException(Request $request, Throwable $exception, string $requestId, int $startedAt): void
    {
        $status = match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        $payload = [
            'exception' => class_basename($exception),
            'message' => $status >= 500 ? 'Internal server error' : $exception->getMessage(),
        ];

        if ($exception instanceof ValidationException) {
            $payload['errors'] = $exception->errors();
        }

        $this->persist($request, $requestId, $status, $startedAt, $payload, 0);
    }

    private function persist(
        Request $request,
        string $requestId,
        int $status,
        int $startedAt,
        mixed $responsePayload,
        int $responseSize,
    ): void {
        try {
            $token = $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE);
            $staff = $request->attributes->get(EnsureWaiterApiToken::STAFF_ATTRIBUTE);
            $user = $request->user();
            $responseBranchId = $this->positiveInteger(data_get($responsePayload, 'data.branch.id'));
            $responseStaffId = $this->positiveInteger(data_get($responsePayload, 'data.staff.id'));

            ApiTrafficLog::create([
                'request_id' => $requestId,
                'branch_id' => $token?->branch_id ?? $user?->branch_id ?? $responseBranchId,
                'user_id' => $token?->user_id ?? $user?->getAuthIdentifier(),
                'staff_profile_id' => $token?->staff_profile_id ?? $staff?->id ?? $responseStaffId,
                'restaurant_id' => $this->scalarString($request->input('restaurant_id')),
                'user_name' => $token?->user?->name ?? $user?->name,
                'staff_name' => $staff?->name ?? $this->scalarString(data_get($responsePayload, 'data.staff.name')),
                'method' => $request->getMethod(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'status_code' => $status,
                'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'request_headers' => $this->limitPayload($this->sanitize($request->headers->all())),
                'request_payload' => $this->limitPayload($this->sanitize($request->all())),
                'response_payload' => $this->limitPayload($this->sanitize($responsePayload)),
                'request_size' => strlen($request->getContent()),
                'response_size' => $responseSize,
                'occurred_at' => now(),
            ]);

            if (random_int(1, 100) === 1) {
                ApiTrafficLog::where('occurred_at', '<', now()->subDays((int) config('api-monitor.retention_days', 7)))->delete();
            }
        } catch (Throwable $loggingException) {
            Log::warning('API traffic monitor could not persist a request.', [
                'exception' => $loggingException::class,
                'request_id' => $requestId,
            ]);
        }
    }

    private function requestId(Request $request): string
    {
        $candidate = trim((string) $request->header('X-Request-ID'));

        return Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }

    private function scalarString(mixed $value): ?string
    {
        return is_scalar($value) ? Str::limit(trim((string) $value), 255, '') : null;
    }

    private function positiveInteger(mixed $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : $integer;
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($value instanceof UploadedFile) {
            return [
                'file_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitize($itemValue, is_string($itemKey) ? $itemKey : null);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return ['type' => $value::class];
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_', '.', ' '], '', $key));

        foreach ([
            'password', 'passwd', 'pin', 'token', 'secret', 'authorization', 'cookie',
            'apikey', 'credential', 'cardnumber', 'cardholder', 'cvv', 'cvc',
        ] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function limitPayload(mixed $payload): ?array
    {
        if ($payload === null || $payload === '' || $payload === []) {
            return null;
        }

        if (! is_array($payload)) {
            $payload = ['value' => $payload];
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $limit = (int) config('api-monitor.max_payload_bytes', 32768);

        if (! is_string($encoded) || strlen($encoded) <= $limit) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_original_bytes' => strlen($encoded),
            '_preview' => mb_strcut($encoded, 0, $limit, 'UTF-8'),
        ];
    }
}
