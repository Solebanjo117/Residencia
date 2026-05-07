<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Subjects/Index', [
            'subjects' => Subject::query()
                ->withCount('teachingLoads')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSubject($request);

        Subject::create($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Materia creada exitosamente.');
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $this->validateSubject($request, $subject);

        $subject->update($validated);

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Materia actualizada exitosamente.');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->teachingLoads()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar una materia con cargas academicas asociadas.',
            ]);
        }

        $subject->delete();

        return redirect()->route('admin.subjects.index')
            ->with('success', 'Materia eliminada exitosamente.');
    }

    private function validateSubject(Request $request, ?Subject $subject = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')->ignore($subject?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('subjects', 'name')->ignore($subject?->id),
            ],
        ]);
    }
}
