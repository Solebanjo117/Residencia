<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function download(Request $request, EvidenceFile $file)
    {
        $this->authorize('download', $file);

        if (!Storage::disk('local')->exists($file->stored_relative_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($file->stored_relative_path, $file->file_name);
    }

    public function store(Request $request, FolderNode $folder)
    {
        // Add authorization check. Let's assume the user can explicitly view/access the folder to upload.
        $this->authorize('view', $folder);

        $request->validate([
            'file' => 'required|file|mimes:docx|max:10240', // Max 10MB
            'submission_id' => 'required|exists:evidence_submissions,id',
        ]);

        $submission = EvidenceSubmission::findOrFail($request->submission_id);

        try {
            $this->storageService->storeEvidence($request->file('file'), $folder, $request->user(), $submission);
            return back()->with('success', 'File uploaded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function replace(Request $request, EvidenceFile $file)
    {
        $this->authorize('delete', $file); // If they can delete, they can replace

        $request->validate([
            'file' => 'required|file|mimes:docx|max:10240',
        ]);

        try {
            // Delete old
            $this->storageService->deleteEvidence($file, $request->user());
            
            // Store new
            $submission = $file->submission;
            $folder = $file->folderNode;
            $this->storageService->storeEvidence($request->file('file'), $folder, $request->user(), $submission);

            return back()->with('success', 'File replaced successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, EvidenceFile $file)
    {
        $this->authorize('delete', $file);

        $this->storageService->deleteEvidence($file, $request->user());

        return back()->with('success', 'File deleted successfully.');
    }
}
