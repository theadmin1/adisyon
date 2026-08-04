<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DijiMenuIntegration;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChainDijiMenuController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $branches = Branch::whereIn('id', $user->accessibleChainBranchIds())
            ->with(['diningTables' => fn ($query) => $query->with('hall')->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();
        $qrOrderingByBranch = Setting::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('key', 'enable_qr_ordering')
            ->pluck('value', 'branch_id');
        $integration = DijiMenuIntegration::firstOrCreate(
            ['organization_id' => $user->organization_id],
            [
                'organization_id' => $user->organization_id,
                'base_url' => rtrim(url('/'), '/'),
                'admin_path' => '/chain/menu',
                'company_slug' => Str::slug($user->organization->code ?: $user->organization->name),
                'branch_slugs' => [],
                'is_active' => true,
            ],
        );

        return view('chain.diji-menu.index', compact('branches', 'integration', 'qrOrderingByBranch'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->chain_role === 'analyst', 403);

        $branchIds = Branch::whereIn('id', $user->accessibleChainBranchIds())->pluck('id')->map(fn ($id) => (string) $id)->all();
        $validated = $request->validate([
            'company_slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'branch_slugs' => ['nullable', 'array'],
            'branch_slugs.*' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'welcome_message' => ['nullable', 'string', 'max:160'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'whatsapp_url' => ['nullable', 'url:http,https', 'max:255'],
            'google_review_url' => ['nullable', 'url:http,https', 'max:255'],
            'google_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'google_review_count' => ['nullable', 'integer', 'min:0'],
            'branch_settings' => ['nullable', 'array'],
            'branch_settings.*.wifi_ssid' => ['nullable', 'string', 'max:100'],
            'branch_settings.*.wifi_password' => ['nullable', 'string', 'max:100'],
            'branch_settings.*.phone' => ['nullable', 'string', 'max:30'],
            'branch_settings.*.address' => ['nullable', 'string', 'max:500'],
            'branch_settings.*.qr_ordering' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $submittedSlugs = collect($validated['branch_slugs'] ?? [])
            ->only($branchIds)
            ->map(fn ($slug) => Str::slug((string) $slug))
            ->filter()
            ->all();

        $integration = DijiMenuIntegration::firstOrNew(['organization_id' => $user->organization_id]);
        $branchSlugs = array_replace($integration->branch_slugs ?? [], $submittedSlugs);
        $submittedBranchSettings = collect($validated['branch_settings'] ?? [])->only($branchIds)->all();
        $settings = array_replace_recursive($integration->settings ?? [], [
            'brand' => [
                'welcome_message' => $validated['welcome_message'] ?? null,
                'primary_color' => strtoupper($validated['primary_color'] ?? data_get($integration->settings, 'brand.primary_color', '#12825F')),
                'instagram_url' => $validated['instagram_url'] ?? null,
                'facebook_url' => $validated['facebook_url'] ?? null,
                'whatsapp_url' => $validated['whatsapp_url'] ?? null,
                'google_review_url' => $validated['google_review_url'] ?? null,
                'google_rating' => filled($validated['google_rating'] ?? null) ? (float) $validated['google_rating'] : null,
                'google_review_count' => filled($validated['google_review_count'] ?? null) ? (int) $validated['google_review_count'] : null,
            ],
            'branches' => $submittedBranchSettings,
        ]);

        $integration->fill(
            [
                'organization_id' => $user->organization_id,
                'base_url' => rtrim(url('/'), '/'),
                'admin_path' => '/chain/menu',
                'company_slug' => $validated['company_slug'],
                'branch_slugs' => $branchSlugs,
                'settings' => $settings,
                'is_active' => $request->boolean('is_active'),
            ]
        );
        $integration->save();

        foreach ($branchIds as $branchId) {
            $branchSetting = data_get($validated, "branch_settings.{$branchId}", []);
            if (array_key_exists('qr_ordering', $branchSetting)) {
                Setting::set(
                    'enable_qr_ordering',
                    filter_var($branchSetting['qr_ordering'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
                    'tables',
                    (int) $branchId,
                );
            }
        }

        return back()->with('success', 'Diji Menü bağlantı ayarları kaydedildi.');
    }
}
