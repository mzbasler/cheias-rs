<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $path = Storage::disk('public')->putFile('reports', $request->file('photo'));

        Report::create([
            ...$request->safe()->except('photo'),
            'photo_path' => $path,
        ]);

        return response()->json(['message' => 'Relato recebido.'], 201);
    }
}
