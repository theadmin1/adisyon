<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DijiMenuIntegration;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDijiMenuController extends Controller
{
    public function show(string $companySlug, string $branchSlug): View
    {
        $integration = DijiMenuIntegration::query()
            ->where('company_slug', $companySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $requestedSlug = Str::slug($branchSlug);
        $branchSlugs = $integration->branch_slugs ?? [];
        $branch = $integration->organization
            ->branches()
            ->where('branches.is_active', true)
            ->get()
            ->first(function (Branch $candidate) use ($branchSlugs, $requestedSlug): bool {
                $configuredSlug = $branchSlugs[(string) $candidate->id] ?? $branchSlugs[$candidate->id] ?? null;

                return ($configuredSlug && hash_equals(Str::slug((string) $configuredSlug), $requestedSlug))
                    || hash_equals(Str::slug((string) $candidate->code), $requestedSlug)
                    || hash_equals(Str::slug((string) $candidate->name), $requestedSlug);
            });
        abort_unless($branch, 404);

        $categories = Category::withoutGlobalScope('authenticated_branch')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->with(['products' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $organization = $branch->organizations()->whereKey($integration->organization_id)->first();

        return view('diji-menu.public', compact('branch', 'categories', 'organization'));
    }
}
