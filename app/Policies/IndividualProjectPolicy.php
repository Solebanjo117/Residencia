<?php

namespace App\Policies;

use App\Models\IndividualProject;
use App\Models\User;

class IndividualProjectPolicy
{
    public function view(User $user, IndividualProject $project): bool
    {
        if ($user->isJefeOficina()) {
            return true;
        }

        return $user->isDocente()
            && (int) $project->teacher_user_id === (int) $user->id;
    }

    public function update(User $user, IndividualProject $project): bool
    {
        return $user->isDocente()
            && (int) $project->teacher_user_id === (int) $user->id
            && in_array($project->status, [IndividualProject::STATUS_DRAFT, IndividualProject::STATUS_REJECTED], true);
    }

    public function submit(User $user, IndividualProject $project): bool
    {
        return $this->update($user, $project)
            && $project->folder_node_id !== null
            && $project->docx_file_id !== null;
    }

    public function review(User $user, IndividualProject $project): bool
    {
        return $user->isJefeOficina()
            && $project->status === IndividualProject::STATUS_SUBMITTED;
    }
}
