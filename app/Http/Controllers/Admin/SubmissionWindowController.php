<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubmissionWindow;
use App\Models\Semester;
use App\Models\EvidenceItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class SubmissionWindowController extends Controller
{
    public function index(Request $request)
    {
        $semesterId = $request->query('semester_id');
        
        $query = SubmissionWindow::with(['semester', 'evidenceItem', 'createdBy'])
            ->orderBy('opens_at', 'desc');

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $windows = $query->paginate(15)->withQueryString();

        $semesters = Semester::orderBy('start_date', 'desc')->get();
        // Solo items activos para poder asignarles una ventana
        $evidenceItems = EvidenceItem::where('active', true)->orderBy('name')->get();

        return Inertia::render('Admin/Windows/Index', [
            'windows' => $windows,
            'semesters' => $semesters,
            'evidenceItems' => $evidenceItems,
            'selectedSemester' => $semesterId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'evidence_item_id' => 'required|exists:evidence_items,id',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date|after:opens_at',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $validated['created_by_user_id'] = Auth::id();

        SubmissionWindow::create($validated);

        return redirect()->back()->with('success', 'Submission window created successfully.');
    }

    public function update(Request $request, SubmissionWindow $window)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'evidence_item_id' => 'required|exists:evidence_items,id',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date|after:opens_at',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $window->update($validated);

        return redirect()->back()->with('success', 'Submission window updated successfully.');
    }

    public function destroy(SubmissionWindow $window)
    {
        $window->delete();

        return redirect()->back()->with('success', 'Submission window deleted successfully.');
    }
}
