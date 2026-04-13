<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FileController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications API
    Route::get('/api/notifications', [\App\Http\Controllers\NotificationController::class, 'getUnread'])->name('notifications.unread');
    Route::post('/api/notifications/read/{id?}', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::group(['prefix' => 'docente', 'as' => 'docente.', 'middleware' => ['role:'.\App\Models\Role::DOCENTE]], function () {
        Route::get('dashboard', [\App\Http\Controllers\Teacher\DashboardController::class, 'index'])->name('teacher.dashboard');
        
        Route::get('evidencias', [\App\Http\Controllers\Teacher\EvidenceController::class, 'index'])->name('evidencias');
        Route::post('evidencias/init', [\App\Http\Controllers\Teacher\EvidenceController::class, 'initSubmission'])->name('evidencias.init');
        Route::post('evidencias/{submission}/submit', [\App\Http\Controllers\Teacher\EvidenceController::class, 'submit'])->name('evidencias.submit');
        Route::post('evidencias/{submission}/upload', [\App\Http\Controllers\Teacher\EvidenceController::class, 'storeFile'])->name('evidencias.upload');
        
        Route::get('asesorias', [\App\Http\Controllers\Teacher\AdvisorySessionController::class, 'index'])->name('asesorias');
        Route::post('asesorias', [\App\Http\Controllers\Teacher\AdvisorySessionController::class, 'store'])->name('asesorias.store');
        Route::delete('asesorias/{id}', [\App\Http\Controllers\Teacher\AdvisorySessionController::class, 'destroy'])->name('asesorias.destroy');
    });

    // Rutas para Jefe de Oficina
    Route::prefix('oficina')->name('oficina.')->middleware(['role:'.\App\Models\Role::JEFE_OFICINA])->group(function () {
        Route::get('revisiones', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('revisiones');
        Route::get('revisiones/{submission}', [\App\Http\Controllers\Admin\ReviewController::class, 'show'])->name('revisiones.show');
        Route::post('revisiones/{submission}/status', [\App\Http\Controllers\Admin\ReviewController::class, 'updateStatus'])->name('revisiones.status');

        Route::get('reportes', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reportes');
    });

    // Rutas para Jefe de Departamento (redirige a Admin que ya tiene middleware de rol compartido)
    Route::prefix('depto')->name('depto.')->middleware(['role:'.\App\Models\Role::JEFE_DEPTO])->group(function () {
        Route::redirect('ventanas', '/admin/windows')->name('ventanas');
        Route::redirect('semestres', '/admin/semesters')->name('semestres');
    });

    // Rutas de Administración (accesible para JEFE_OFICINA y JEFE_DEPTO)
    Route::prefix('admin')->name('admin.')->middleware(['role:'.\App\Models\Role::JEFE_OFICINA.','.\App\Models\Role::JEFE_DEPTO])->group(function () {
        Route::resource('departments', \App\Http\Controllers\Admin\DepartmentController::class)->except(['create', 'show', 'edit']);
        Route::resource('teachers', \App\Http\Controllers\Admin\TeacherController::class);
        Route::post('teachers/{teacher}/generate-folders', [\App\Http\Controllers\Admin\TeacherController::class, 'generateFolders'])->name('teachers.generate-folders');
        Route::resource('semesters', \App\Http\Controllers\Admin\SemesterController::class);
        Route::resource('teaching-loads', \App\Http\Controllers\Admin\TeachingLoadController::class);
        
        Route::get('requirements', [\App\Http\Controllers\Admin\RequirementController::class, 'index'])->name('requirements.index');
        Route::post('requirements', [\App\Http\Controllers\Admin\RequirementController::class, 'store'])->name('requirements.store');
        
        Route::resource('windows', \App\Http\Controllers\Admin\SubmissionWindowController::class);
        
        Route::get('audits', [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('audits.index');
    });

    // Seguimiento Docente (vista unificada, todos los roles)
    Route::get('asesorias', [\App\Http\Controllers\Admin\AdvisoryController::class, 'index'])->name('asesorias');
    Route::post('asesorias/{submission}/review', [\App\Http\Controllers\Admin\AdvisoryController::class, 'reviewEvidence'])
        ->middleware(['role:'.\App\Models\Role::JEFE_OFICINA])
        ->name('asesorias.review');

    // Horarios de Asesorías
    Route::get('asesorias-horarios', [\App\Http\Controllers\AdvisoryScheduleController::class, 'index'])->name('asesorias.horarios');

    // File Manager Routes
    Route::get('/files/manager', [FolderController::class, 'index'])->name('folders.index');
    Route::get('/files/folders/{folder}', [FolderController::class, 'show'])->name('folders.show');
    Route::post('/files/folders/{folder}/upload', [FileController::class, 'store'])->name('files.store');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::post('/files/{file}/replace', [FileController::class, 'replace'])->name('files.replace');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
});

require __DIR__.'/settings.php';
