<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DijiMenuIntegration;
use Illuminate\View\View;

class PublicDijiMenuController extends Controller
{
    public function show(string $companySlug, string $branchSlug): View
    {
        $integration = DijiMenuIntegration::query()
            ->where('company_slug', $companySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $branchId = collect($integration->branch_slugs ?? [])->search(
            fn ($slug) => hash_equals((string) $slug, $branchSlug),
        );
        abort_if($branchId === false, 404);

        $branch = Branch::query()
            ->whereKey((int) $branchId)
            ->where('is_active', true)
            ->whereHas('organizations', fn ($query) => $query->whereKey($integration->organization_id))
            ->firstOrFail();

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
