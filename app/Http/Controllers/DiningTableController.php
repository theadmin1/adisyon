<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\UpdateDiningTableRequest;
use App\Models\Category;
use App\Models\Check;
use App\Models\DiningTable;
use App\Models\Hall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function index(Request $request): View
    {
        $tables = DiningTable::query()
            ->with([
                'hall',
                'checks' => fn ($query) => $query
                    ->whereIn('status', ['open', 'awaiting_payment'])
                    ->withCount('items')
                    ->latest(),
            ])
            ->when($request->filled('hall'), fn ($query) => $query->where('hall_id', $request->integer('hall')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get();

        $halls = Hall::query()->where('is_active', true)->orderBy('sort_order')->get();
        $totalTables = DiningTable::count();
        $occupiedCount = DiningTable::where('status', 'occupied')->count();
        $availableCount = DiningTable::where('status', 'available')->count();
        $awaitingCount = DiningTable::where('status', 'awaiting_payment')->count();
        $openRevenue = Check::whereIn('status', ['open', 'awaiting_payment'])->sum('total');

        $stats = [
            'total_tables' => $totalTables,
            'occupied_tables' => $occupiedCount,
            'available_tables' => $availableCount,
            'awaiting_tables' => $awaitingCount,
            'occupancy_rate' => $totalTables > 0 ? round(($occupiedCount / $totalTables) * 100) : 0,
            'open_revenue' => number_format($openRevenue, 2),
        ];

        return view('tables.index', [
            'tables' => $tables,
            'groupedTables' => $tables->groupBy(fn ($table) => $table->hall?->name ?: 'Salonsuz Alan'),
            'halls' => $halls,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, DiningTable $table): View
    {
        $table->load(['hall']);

        $activeCheck = Check::query()
            ->where('dining_table_id', $table->id)
            ->whereIn('status', ['open', 'awaiting_payment'])
            ->with(['items', 'payments'])
            ->latest()
            ->first();

        $siblingChecks = $activeCheck
            ? Check::query()
                ->where('dining_table_id', $table->id)
                ->whereIn('status', [CheckStatus::Open, CheckStatus::AwaitingPayment])
                ->whereKeyNot($activeCheck->id)
                ->get()
            : collect();

        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get();

        $allTables = DiningTable::query()
            ->where('is_active', true)
            ->with(['hall', 'checks' => fn ($query) => $query->whereIn('status', ['open', 'awaiting_payment'])])
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get();

        $moveTargets = $allTables->where('id', '!=', $table->id);

        return view('tables.show', [
            'table' => $table,
            'activeCheck' => $activeCheck,
            'siblingChecks' => $siblingChecks,
            'categories' => $categories,
            'moveTargets' => $moveTargets,
            'allTables' => $allTables,
        ]);
    }

    public function store(StoreDiningTableRequest $request): RedirectResponse
    {
        DiningTable::create([
            'hall_id' => $request->integer('hall_id') ?: null,
            'name' => $request->string('name')->toString(),
            'code' => $request->string('code')->toString(),
            'capacity' => $request->integer('capacity'),
            'status' => 'available',
            'is_active' => true,
            'notes' => $request->string('notes')->toString(),
        ]);

        return back()->with('status', 'Masa kaydı oluşturuldu.');
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $hallId = $request->integer('hall_id') ?: null;

        $table->update([
            'hall_id' => $hallId,
            'name' => $request->string('name')->toString(),
            'code' => $request->string('code')->toString(),
            'capacity' => $request->integer('capacity'),
            'status' => $request->filled('status') ? $request->string('status')->toString() : $table->status,
            'is_active' => $request->boolean('is_active'),
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        return back()->with('status', 'Masa güncellendi.');
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        $hasOpenCheck = $table->checks()
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
            ->exists();

        if ($hasOpenCheck) {
            return back()->withErrors([
                'table' => 'Masada açık adisyon var. Önce adisyonu kapatın.',
            ]);
        }

        $table->delete();

        return redirect()->route('tables.index')->with('status', 'Masa silindi.');
    }
}
