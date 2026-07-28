<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierProductSubmission;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SupplierPortalController extends Controller
{
    public function setup(Request $request, Supplier $supplier, AuditLogger $auditLogger): RedirectResponse
    {
        if (! $supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier' => 'Pasif tedarikçi için portal erişimi açılamaz.',
            ]);
        }

        if (! $supplier->portal_token_hash || ! $supplier->portal_code_hash) {
            $this->generateCredentials($supplier);
        }
        $supplier->update(['portal_enabled' => true]);

        $auditLogger->record(
            'supplier_portal.enabled',
            $supplier,
            oldValues: ['portal_enabled' => false],
            newValues: ['portal_enabled' => true],
            description: 'Tedarikçi ürün portalı erişime açıldı.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'supplier-products'])
            ->with('success', 'Tedarikçi portalı hazırlandı ve erişime açıldı.');
    }

    public function regenerate(Request $request, Supplier $supplier, AuditLogger $auditLogger): RedirectResponse
    {
        if (! $supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier' => 'Pasif tedarikçi için portal bilgileri yenilenemez.',
            ]);
        }

        $this->generateCredentials($supplier);
        $supplier->update(['portal_enabled' => true]);
        $request->session()->forget('supplier_portal');

        $auditLogger->record(
            'supplier_portal.credentials_regenerated',
            $supplier,
            newValues: ['portal_enabled' => true],
            description: 'Tedarikçi portal linki ve 4 haneli erişim kodu yenilendi.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'supplier-products'])
            ->with('success', 'Portal linki ve 4 haneli kod yenilendi. Eski bilgiler artık kullanılamaz.');
    }

    public function toggle(Supplier $supplier, AuditLogger $auditLogger): RedirectResponse
    {
        if (! $supplier->portal_token_hash || ! $supplier->portal_code_hash) {
            throw ValidationException::withMessages([
                'supplier' => 'Önce tedarikçi için portal erişimi oluşturulmalıdır.',
            ]);
        }
        if (! $supplier->is_active && ! $supplier->portal_enabled) {
            throw ValidationException::withMessages([
                'supplier' => 'Pasif tedarikçinin portalı erişime açılamaz.',
            ]);
        }

        $old = $supplier->portal_enabled;
        $supplier->update(['portal_enabled' => ! $old]);
        $auditLogger->record(
            'supplier_portal.status_changed',
            $supplier,
            oldValues: ['portal_enabled' => $old],
            newValues: ['portal_enabled' => $supplier->portal_enabled],
            description: $supplier->portal_enabled ? 'Tedarikçi ürün portalı erişime açıldı.' : 'Tedarikçi ürün portalı erişime kapatıldı.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'supplier-products'])
            ->with('success', $supplier->portal_enabled ? 'Portal erişime açıldı.' : 'Portal erişime kapatıldı.');
    }

    public function show(Request $request, string $token): Response
    {
        $supplier = $this->findSupplier($token);
        $verified = $supplier->portal_enabled && $this->isVerified($request, $supplier);
        $submissions = collect();
        $initialItems = old('items', []);

        if ($verified) {
            $submissions = SupplierProductSubmission::forBranch((int) $supplier->branch_id)
                ->where('supplier_id', $supplier->id)
                ->with('items')
                ->latest('submitted_at')
                ->limit(10)
                ->get();
        }

        return response()
            ->view('purchasing.supplier-portal', compact('supplier', 'submissions', 'initialItems', 'token', 'verified'))
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $supplier = $this->findSupplier($token);
        if (! $supplier->portal_enabled || ! $supplier->is_active) {
            return redirect()
                ->route('supplier-portal.show', $token)
                ->withErrors(['code' => 'Bu tedarikçi portalı şu anda kullanıma kapalı.']);
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:4'],
        ]);
        $candidateHash = $this->codeHash($validated['code']);
        if (! $supplier->portal_code_hash || ! hash_equals($supplier->portal_code_hash, $candidateHash)) {
            throw ValidationException::withMessages([
                'code' => 'Girdiğiniz 4 haneli kod hatalı.',
            ]);
        }

        $request->session()->put('supplier_portal', [
            'supplier_id' => $supplier->id,
            'token_hash' => $supplier->portal_token_hash,
            'verified_at' => now()->timestamp,
        ]);
        $request->session()->regenerate();

        return redirect()->route('supplier-portal.show', $token);
    }

    public function logout(Request $request, string $token): RedirectResponse
    {
        $this->findSupplier($token);
        $request->session()->forget('supplier_portal');
        $request->session()->regenerateToken();

        return redirect()->route('supplier-portal.show', $token);
    }

    public function submitProducts(Request $request, string $token): RedirectResponse
    {
        $supplier = $this->findSupplier($token);
        if (! $supplier->portal_enabled || ! $supplier->is_active || ! $this->isVerified($request, $supplier)) {
            $request->session()->forget('supplier_portal');

            return redirect()
                ->route('supplier-portal.show', $token)
                ->withErrors(['portal' => 'Ürün eklemek için aktif portal erişimi ve geçerli kod doğrulaması gerekir.']);
        }

        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'supplier_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_name' => ['required', 'string', 'max:255', 'distinct:strict'],
            'items.*.supplier_sku' => ['nullable', 'string', 'max:100'],
            'items.*.barcode' => ['nullable', 'string', 'max:64'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.package_description' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.minimum_order_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.delivery_days' => ['nullable', 'integer', 'between:0,365'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = DB::transaction(function () use ($supplier, $validated, $request): SupplierProductSubmission {
            $submission = SupplierProductSubmission::create([
                'branch_id' => $supplier->branch_id,
                'supplier_id' => $supplier->id,
                'submission_number' => 'TU-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'status' => 'pending',
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'supplier_notes' => $validated['supplier_notes'] ?? null,
                'submitted_ip' => filter_var($request->ip(), FILTER_VALIDATE_IP) ? $request->ip() : null,
                'submitted_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'submitted_at' => now(),
            ]);

            foreach ($validated['items'] as $item) {
                $submission->items()->create([
                    'branch_id' => $supplier->branch_id,
                    'product_name' => $item['product_name'],
                    'supplier_sku' => $item['supplier_sku'] ?? null,
                    'barcode' => $item['barcode'] ?? null,
                    'brand' => $item['brand'] ?? null,
                    'unit' => $item['unit'],
                    'package_description' => $item['package_description'] ?? null,
                    'unit_price' => round((float) $item['unit_price'], 4),
                    'tax_rate' => round((float) ($item['tax_rate'] ?? 0), 2),
                    'minimum_order_quantity' => round((float) $item['minimum_order_quantity'], 3),
                    'delivery_days' => isset($item['delivery_days']) ? (int) $item['delivery_days'] : null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $submission;
        });

        return redirect()
            ->route('supplier-portal.show', $token)
            ->with('portal_success', "{$submission->submission_number} numaralı ürün bildiriminiz yönetime iletildi.");
    }

    public function approve(
        Request $request,
        SupplierProductSubmission $supplierProductSubmission,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $submission = DB::transaction(function () use ($supplierProductSubmission, $request): SupplierProductSubmission {
            $locked = SupplierProductSubmission::query()
                ->whereKey($supplierProductSubmission->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'submission' => 'Yalnızca onay bekleyen ürün bildirimi onaylanabilir.',
                ]);
            }
            $locked->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_by_staff_profile_id' => $this->staffId($request),
                'reviewed_by_name' => $this->actorName($request),
                'review_notes' => $request->validate(['review_notes' => ['nullable', 'string', 'max:1000']])['review_notes'] ?? null,
                'reviewed_at' => now(),
            ]);

            return $locked->fresh();
        });
        $auditLogger->record(
            'supplier_product_submission.approved',
            $submission,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'approved'],
            description: 'Tedarikçinin gönderdiği ürün bilgileri doğrulanıp onaylandı.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'supplier-products'])
            ->with('success', 'Ürün bilgileri doğrulandı ve onaylandı.');
    }

    public function reject(
        Request $request,
        SupplierProductSubmission $supplierProductSubmission,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);
        if ($supplierProductSubmission->status !== 'pending') {
            throw ValidationException::withMessages([
                'submission' => 'Yalnızca onay bekleyen ürün bildirimi reddedilebilir.',
            ]);
        }
        $supplierProductSubmission->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_by_staff_profile_id' => $this->staffId($request),
            'reviewed_by_name' => $this->actorName($request),
            'review_notes' => $validated['review_notes'],
            'reviewed_at' => now(),
        ]);
        $auditLogger->record(
            'supplier_product_submission.rejected',
            $supplierProductSubmission,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'rejected', 'reason' => $validated['review_notes']],
            description: 'Tedarikçinin gönderdiği ürün bilgileri reddedildi.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'supplier-products'])
            ->with('success', 'Ürün bildirimi reddedildi.');
    }

    private function generateCredentials(Supplier $supplier): void
    {
        do {
            $token = Str::random(64);
            $tokenHash = hash('sha256', $token);
        } while (Supplier::withoutGlobalScope('authenticated_branch')->where('portal_token_hash', $tokenHash)->exists());

        $code = $this->uniqueCode();
        $supplier->update([
            'portal_token_hash' => $tokenHash,
            'portal_token' => $token,
            'portal_code_hash' => $this->codeHash($code),
            'portal_code' => $code,
            'portal_credentials_generated_at' => now(),
        ]);
    }

    private function uniqueCode(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = (string) random_int(1000, 9999);
            $hash = $this->codeHash($code);
            if (! Supplier::withoutGlobalScope('authenticated_branch')->where('portal_code_hash', $hash)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Benzersiz tedarikçi portal kodu üretilemedi.');
    }

    private function findSupplier(string $token): Supplier
    {
        abort_unless(strlen($token) === 64, 404);

        return Supplier::withoutGlobalScope('authenticated_branch')
            ->with('branch')
            ->where('portal_token_hash', hash('sha256', $token))
            ->firstOrFail();
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function isVerified(Request $request, Supplier $supplier): bool
    {
        $portal = $request->session()->get('supplier_portal');
        if (! is_array($portal)
            || (int) ($portal['supplier_id'] ?? 0) !== (int) $supplier->id
            || ! is_string($portal['token_hash'] ?? null)
            || ! hash_equals($supplier->portal_token_hash, $portal['token_hash'])
            || ! is_numeric($portal['verified_at'] ?? null)) {
            return false;
        }

        return (int) $portal['verified_at'] >= now()->subHours(12)->timestamp;
    }

    private function staffId(Request $request): ?int
    {
        $value = $request->session()->get('active_staff_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function actorName(Request $request): string
    {
        return (string) ($request->session()->get('active_staff_name') ?: $request->user()->name);
    }
}
