import { SubmissionStatus } from '@/types/enums';
import type { EvidenceSubmission } from '@/types/models';
import { useAuth } from './useAuth';

export function usePermissions() {
    const { isDocente, isAdminAuthority } = useAuth();

    const canUploadEvidence = (submission?: EvidenceSubmission) => {
        if (!isDocente.value) return false;
        if (!submission) return true; // Can create new
        return (
            [SubmissionStatus.DRAFT, SubmissionStatus.REJECTED].includes(
                submission.status,
            ) || !!submission.active_resubmission_unlock
        );
    };

    const canReviewEvidence = (submission: EvidenceSubmission) => {
        return (
            isAdminAuthority.value &&
            submission.status === SubmissionStatus.SUBMITTED
        );
    };

    const canUnlockEvidence = (submission: EvidenceSubmission) => {
        void submission;

        return isAdminAuthority.value;
    };

    const canMarkNA = () => isAdminAuthority.value;

    const canConfigureWindows = () => isAdminAuthority.value;

    return {
        canUploadEvidence,
        canReviewEvidence,
        canUnlockEvidence,
        canMarkNA,
        canConfigureWindows,
    };
}
