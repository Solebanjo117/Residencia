<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EvidenceItemController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/EvidenceItems/Index', [
            'categories' => EvidenceCategory::query()
                ->withCount('items')
                ->orderBy('name')
                ->get(),
            'items' => EvidenceItem::query()
                ->with('category')
                ->withCount(['requirements', 'submissions'])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvidenceItem($request);

        EvidenceItem::create($validated);

        return redirect()->route('admin.evidence-items.index')
            ->with('success', 'Rubro de evidencia creado exitosamente.');
    }

    public function update(Request $request, EvidenceItem $evidenceItem)
    {
        $validated = $this->validateEvidenceItem($request, $evidenceItem);

        $evidenceItem->update($validated);

        return redirect()->route('admin.evidence-items.index')
            ->with('success', 'Rubro de evidencia actualizado exitosamente.');
    }

    public function destroy(EvidenceItem $evidenceItem)
    {
        if ($evidenceItem->requirements()->exists() || $evidenceItem->submissions()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un rubro con matriz o evidencias asociadas. Puedes desactivarlo.',
            ]);
        }

        $evidenceItem->delete();

        return redirect()->route('admin.evidence-items.index')
            ->with('success', 'Rubro de evidencia eliminado exitosamente.');
    }

    private function validateEvidenceItem(Request $request, ?EvidenceItem $evidenceItem = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:evidence_categories,id'],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('evidence_items', 'name')
                    ->where('category_id', $request->input('category_id'))
                    ->ignore($evidenceItem?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'requires_subject' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
