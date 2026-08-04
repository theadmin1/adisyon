<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DijiMenuIntegration;
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
        $branches = Branch::whereIn('id', $user->accessibleChainBranchIds())->orderBy('name')->get();
        $integration = DijiMenuIntegration::where('organization_id', $user->organization_id)->first();
        if (! $integration && config('services.diji_menu.url')) {
            $integration = new DijiMenuIntegration([
                'base_url' => rtrim((string) config('services.diji_menu.url'), '/'),
                'admin_path' => '/menu-management',
                'company_slug' => Str::slug($user->organization->code ?: $user->organization->name),
                'branch_slugs' => [],
                'is_active' => true,
            ]);
        }

        return view('chain.diji-menu.index', compact('branches', 'integration'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->chain_role === 'analyst', 403);

        $branchIds = Branch::whereIn('id', $user->accessibleChainBranchIds())->pluck('id')->map(fn ($id) => (string) $id)->all();
        $validated = $request->validate([
            'base_url' => ['required', 'url:http,https', 'max:500'],
            'admin_path' => ['required', 'string', 'max:255', 'regex:/^\/[A-Za-z0-9_\-\/]*$/'],
            'company_slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'branch_slugs' => ['nullable', 'array'],
            'branch_slugs.*' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $submittedSlugs = collect($validated['branch_slugs'] ?? [])
            ->only($branchIds)
            ->map(fn ($slug) => Str::slug((string) $slug))
            ->filter()
            ->all();

        $integration = DijiMenuIntegration::firstOrNew(['organization_id' => $user->organization_id]);
        $branchSlugs = array_replace($integration->branch_slugs ?? [], $submittedSlugs);

        $integration->fill(
            [
                'organization_id' => $user->organization_id,
                'base_url' => rtrim($validated['base_url'], '/'),
                'admin_path' => '/'.ltrim($validated['admin_path'], '/'),
                'company_slug' => $validated['company_slug'],
                'branch_slugs' => $branchSlugs,
                'is_active' => $request->boolean('is_active'),
            ]
        );
        $integration->save();

        return back()->with('success', 'Diji Menü bağlantı ayarları kaydedildi.');
    }
}
