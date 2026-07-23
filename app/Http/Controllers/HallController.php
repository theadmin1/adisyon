<?php

namespace App\Http\Controllers;

use App\Models\Hall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HallController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:halls,name',
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
