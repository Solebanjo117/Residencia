<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function storeEvidence(UploadedFile $file, FolderNode $folderNode, User $user, EvidenceSubmission $submission)
    {
        $allowed = ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed)) {
            throw new \Exception("Formato no permitido. Formatos válidos: " . implode(', ', $allowed));
        }

        // Check root path
        $root = $folderNode->root;
        if (!$root || !$root->is_active) {
            throw new \Exception("Storage root not active or defined.");
        }

        // Generate stored path
        $fileName = $file->getClientOriginalName();
        $storedName = Str::uuid() . '_' . $fileName;
        
        // Full relative path from root base
        $relativePath = $folderNode->relative_path . '/' . $storedName;

        // Store file physically
        // Ideally we use a disk configured for the root path
        $path = $file->storeAs($folderNode->relative_path, $storedName, 'local'); 

        // Create EvidenceFile record
        $evidenceFile = EvidenceFile::create([
            'submission_id' => $submission->id,
            'folder_node_id' => $folderNode->id,
            'file_name' => $fileName,
            'stored_relative_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'uploaded_at' => now(),
            'uploaded_by_user_id' => $user->id,
        ]);

        $this->auditService->log($user, 'UPLOAD_FILE', 'EvidenceFile', $evidenceFile->id, ['filename' => $fileName]);

        return $evidenceFile;
    }

    public function deleteEvidence(EvidenceFile $file, User $user)
    {
        // Set who deleted it, then use Laravel's SoftDeletes properly
        $file->deleted_by_user_id = $user->id;
        $file->save();
        $file->delete();

        $this->auditService->log($user, 'DELETE_FILE', 'EvidenceFile', $file->id);

        return $file;
    }

    public function getAccessibleRoots(User $user)
    {
        if ($user->isJefeOficina() || $user->isJefeDepto()) {
            return $this->buildTree(FolderNode::all());
        }

        if ($user->isDocente()) {
            $nodes = FolderNode::where('owner_user_id', $user->id)->get();
            return $this->buildTree($nodes);
        }

        return [];
    }

    private function buildTree($nodes)
    {
        $grouped = $nodes->groupBy('parent_id');
        
        // Find roots within the collection (nodes whose parent is not in the collection)
        $ids = $nodes->pluck('id');
        $roots = $nodes->filter(function ($node) use ($ids) {
            return is_null($node->parent_id) || !$ids->contains($node->parent_id);
        });

        foreach ($nodes as $node) {
            $children = $grouped->get($node->id) ?? collect([]);
            $node->setRelation('children', $children);
        }

        return $roots->values();
    }
}
