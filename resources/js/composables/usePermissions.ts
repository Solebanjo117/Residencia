import { useAuth } from './useAuth';
import { SubmissionStatus, RoleName } from '@/types/enums';
import type { EvidenceSubmission } from '@/types/models';

export function usePermissions() {
    const { user, isDocente, isJefeOficina, isJefeDepto } = useAuth();

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
            isJefeOficina.value &&
            submission.status === SubmissionStatus.SUBMITTED
        );
    };

    const canUnlockEvidence = (submission: EvidenceSubmission) => {
        return isJefeOficina.value;
    };

    const canMarkNA = () => isJefeOficina.value;

    const canConfigureWindows = () => isJefeDepto.value;

    return {
        canUploadEvidence,
        canReviewEvidence,
        canUnlockEvidence,
        canMarkNA,
        canConfigureWindows,
    };
}
