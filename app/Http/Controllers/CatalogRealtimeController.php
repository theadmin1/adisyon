<?php

namespace App\Http\Controllers;

use App\Support\CatalogVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogRealtimeController extends Controller
{
    public function version(Request $request): JsonResponse
    {
        return response()->json([
            'version' => CatalogVersion::current((int) $request->user()->branch_id),
        ])->header('Cache-Control', 'no-store, private');
    }
}
