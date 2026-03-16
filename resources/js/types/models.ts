import {
    SubmissionStatus,
    ReviewDecision,
    NotificationType,
    AcademicPeriodStatus,
    SemesterStatus,
    WindowStatus,
    RoleName,
} from './enums';

export interface Role {
    id: number;
    name: RoleName;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role_id: number;
    is_active: boolean;
    role?: Role;
    departments?: Department[];
    created_at: string;
    updated_at: string;
}

export interface Department {
    id: number;
    name: string;
}

export interface AcademicPeriod {
    id: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    status: AcademicPeriodStatus;
    created_at: string;
    updated_at: string;
}

export interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: SemesterStatus;
    academic_period_id: number | null;
    academic_period?: AcademicPeriod;
    created_at: string;
    updated_at: string;
}

export interface Subject {
    id: number;
    code: string;
    name: string;
}

export interface TeachingLoad {
    id: number;
    teacher_user_id: number;
    semester_id: number;
    subject_id: number;
    group_code: string;
    hours_per_week: number | null;
    teacher?: User;
    semester?: Semester;
    subject?: Subject;
    created_at: string;
    updated_at: string;
}

export interface EvidenceCategory {
    id: number;
    name: string;
    description: string | null;
    items?: EvidenceItem[];
}

export interface EvidenceItem {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    requires_subject: boolean;
    active: boolean;
    category?: EvidenceCategory;
    formats?: EvidenceFormat[];
}

export interface EvidenceFormat {
    id: number;
    name: string;
    template_url: string | null;
    active: boolean;
}

export interface EvidenceRequirement {
    id: number;
    semester_id: number;
    department_id: number | null;
    evidence_item_id: number;
    is_mandatory: boolean;
    applies_condition: Record<string, any> | null;
    semester?: Semester;
    department?: Department;
    evidence_item?: EvidenceItem;
    created_at: string;
}

export interface SubmissionWindow {
    id: number;
    semester_id: number;
    evidence_item_id: number;
    opens_at: string;
    closes_at: string;
    created_by_user_id: number;
    status: WindowStatus;
    semester?: Semester;
    evidence_item?: EvidenceItem;
    created_by?: User;
    created_at: string;
    updated_at: string;
}

export interface StorageRoot {
    id: number;
    name: string;
    base_path: string;
    is_active: boolean;
}

export interface FolderNode {
    id: number;
    parent_id: number | null;
    storage_root_id: number;
    name: string;
    relative_path: string;
    owner_user_id: number | null;
    semester_id: number | null;
    parent?: FolderNode;
    children?: FolderNode[];
    root?: StorageRoot;
    owner?: User;
    semester?: Semester;
    created_at: string;
}

export interface EvidenceSubmission {
    id: number;
    semester_id: number;
    teacher_user_id: number;
    evidence_item_id: number;
    teaching_load_id: number;
    status: SubmissionStatus;
    submitted_at: string | null;
    last_updated_at: string;
    semester?: Semester;
    teacher?: User;
    evidence_item?: EvidenceItem;
    teaching_load?: TeachingLoad;
    files?: EvidenceFile[];
    reviews?: EvidenceReview[];
    status_history?: EvidenceStatusHistory[];
    active_resubmission_unlock?: ResubmissionUnlock;
    created_at: string;
    updated_at: string;
}

export interface EvidenceFile {
    id: number;
    submission_id: number;
    folder_node_id: number;
    file_name: string;
    stored_relative_path: string;
    mime_type: string | null;
    size_bytes: number | null;
    file_hash: string | null;
    uploaded_at: string;
    uploaded_by_user_id: number;
    deleted_at: string | null;
    deleted_by_user_id: number | null;
    submission?: EvidenceSubmission;
    folder_node?: FolderNode;
    uploaded_by?: User;
    deleted_by?: User;
    created_at: string;
    updated_at: string;
}

export interface EvidenceReview {
    id: number;
    submission_id: number;
    reviewed_by_user_id: number;
    decision: ReviewDecision;
    comments: string | null;
    reviewed_at: string;
    submission?: EvidenceSubmission;
    reviewer?: User;
}

export interface EvidenceStatusHistory {
    id: number;
    submission_id: number;
    old_status: SubmissionStatus;
    new_status: SubmissionStatus;
    changed_by_user_id: number;
    change_reason: string | null;
    changed_at: string;
    submission?: EvidenceSubmission;
    changed_by?: User;
}

export interface ResubmissionUnlock {
    id: number;
    submission_id: number;
    unlocked_by_user_id: number;
    unlocked_at: string;
    expires_at: string | null;
    reason: string | null;
    submission?: EvidenceSubmission;
    unlocked_by?: User;
}

export interface AuditLog {
    id: number;
    user_id: number;
    action: string;
    entity_type: string | null;
    entity_id: number | null;
    at: string;
    metadata: Record<string, any> | null;
    user?: User;
}

export interface Notification {
    id: number;
    user_id: number;
    type: NotificationType;
    title: string;
    message: string;
    related_entity_type: string | null;
    related_entity_id: number | null;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    user?: User;
}

export interface NotificationSchedule {
    id: number;
    semester_id: number;
    evidence_item_id: number;
    notify_at: string;
    notification_type: NotificationType;
    is_sent: boolean;
    created_at: string;
    updated_at: string;
    semester?: Semester;
    evidence_item?: EvidenceItem;
}

export interface AdvisorySession {
    id: number;
    teaching_load_id: number;
    semester_id: number;
    session_date: string;
    topic: string;
    duration_minutes: number | null;
    notes: string | null;
    created_by_user_id: number;
    created_at: string;
    updated_at: string;
    teaching_load?: TeachingLoad;
    semester?: Semester;
    creator?: User;
    files?: AdvisoryFile[];
}

export interface AdvisoryFile {
    id: number;
    advisory_session_id: number;
    file_name: string;
    stored_relative_path: string;
    mime_type: string | null;
    size_bytes: number | null;
    uploaded_at: string;
    uploaded_by_user_id: number;
    session?: AdvisorySession;
    uploaded_by?: User;
}
