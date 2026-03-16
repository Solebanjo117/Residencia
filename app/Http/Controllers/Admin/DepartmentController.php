<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->get();
        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments,name',
        ]);

        Department::create($validated);

        return back()->with('success', 'Departamento creado exitosamente.');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments,name,' . $department->id,
        ]);

        $department->update($validated);

        return back()->with('success', 'Departamento actualizado exitosamente.');
    }

    public function destroy(Department $department)
    {
        // Prevent deleting if it has related requirements or teachers
        if ($department->requirements()->exists() || $department->teachers()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar porque este departamento tiene docentes o requerimientos asignados.']);
        }

        $department->delete();
        return back()->with('success', 'Departamento eliminado exitosamente.');
    }
}
