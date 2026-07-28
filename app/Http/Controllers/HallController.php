<?php

namespace App\Http\Controllers;

use App\Models\Hall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HallController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('halls', 'name')
                    ->where(fn ($query) => $query->where('branch_id', $request->user()->branch_id)),
            ],
            'code' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
        ]);

        $maxOrder = Hall::max('sort_order') ?? 0;

        Hall::create([
            'name' => trim($validated['name']),
            'code' => $validated['code'] ?? strtoupper(substr($validated['name'], 0, 3)),
            'sort_order' => $validated['sort_order'] ?? ($maxOrder + 1),
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', "'{$validated['name']}' salonu başarıyla eklendi.");
    }

    public function update(Request $request, Hall $hall): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('halls', 'name')
                    ->where(fn ($query) => $query->where('branch_id', $request->user()->branch_id))
                    ->ignore($hall->id),
            ],
            'code' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $hall->update([
            'name' => trim($validated['name']),
            'code' => $validated['code'] ?? $hall->code,
            'sort_order' => $validated['sort_order'] ?? $hall->sort_order,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $hall->is_active,
        ]);

        return redirect()->back()->with('status', "'{$hall->name}' salonu başarıyla güncellendi.");
    }

    public function destroy(Hall $hall): RedirectResponse
    {
        if ($hall->tables()->count() > 0) {
            return redirect()->back()->withErrors([
                'hall' => "'{$hall->name}' salonunda masalar bulunmaktadır. Önce masaları başka salona taşıyın veya silin.",
            ]);
        }

        $name = $hall->name;
        $hall->delete();

        return redirect()->back()->with('status', "'{$name}' salonu silindi.");
    }
}
