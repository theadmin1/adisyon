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

        $branchId = collect($integration->branch_slugs ?? [])->search(
            fn ($slug) => hash_equals((string) $slug, $branchSlug),
        );
        $branch = Branch::query()
            ->when($branchId !== false, fn ($query) => $query->whereKey((int) $branchId))
            ->when($branchId === false, fn ($query) => $query->where(
                fn ($branchQuery) => $branchQuery
                    ->whereRaw('LOWER(code) = ?', [Str::lower($branchSlug)])
                    ->orWhereRaw('LOWER(REPLACE(name, ? , ?)) = ?', [' ', '-', Str::lower($branchSlug)]),
            ))
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
