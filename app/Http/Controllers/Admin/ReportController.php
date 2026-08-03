<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index', [
            'reports' => Report::with('reviewer')->latest()->paginate(30),
        ]);
    }

    public function approve(Request $request, Report $report): RedirectResponse
    {
        $report->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Relato aprovado.');
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        $report->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Relato rejeitado.');
    }
}
