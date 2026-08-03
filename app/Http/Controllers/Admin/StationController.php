<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStationRequest;
use App\Models\Station;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StationController extends Controller
{
    public function index(): View
    {
        return view('admin.stations.index', [
            'stations' => Station::with('latestReading')->orderBy('name')->paginate(50),
        ]);
    }

    public function edit(Station $station): View
    {
        return view('admin.stations.edit', ['station' => $station]);
    }

    public function update(UpdateStationRequest $request, Station $station): RedirectResponse
    {
        $station->update($request->validated());

        return redirect()->route('admin.stations.index')->with('status', 'Estação atualizada.');
    }
}
