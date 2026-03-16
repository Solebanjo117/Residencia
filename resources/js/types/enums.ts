export enum SubmissionStatus {
    DRAFT = 'DRAFT',
    SUBMITTED = 'SUBMITTED',
    APPROVED = 'APPROVED',
    REJECTED = 'REJECTED',
    NA = 'NA',
    NE = 'NE',
}

export enum ReviewDecision {
    APPROVE = 'APPROVE',
    REJECT = 'REJECT',
}

export enum NotificationType {
    NEW_ASSIGNMENT = 'NEW_ASSIGNMENT',
    WINDOW_OPEN = 'WINDOW_OPEN',
    WINDOW_CLOSING = 'WINDOW_CLOSING',
    SUBMISSION_APPROVED = 'SUBMISSION_APPROVED',
    SUBMISSION_REJECTED = 'SUBMISSION_REJECTED',
    GENERAL = 'GENERAL',
}

export enum AcademicPeriodStatus {
    PLANNED = 'PLANNED',
    ACTIVE = 'ACTIVE',
    CLOSED = 'CLOSED',
}

export enum SemesterStatus {
    OPEN = 'OPEN',
    CLOSED = 'CLOSED',
}

export enum WindowStatus {
    ACTIVE = 'ACTIVE',
    INACTIVE = 'INACTIVE',
}

export enum RoleName {
    DOCENTE = 'DOCENTE',
    JEFE_OFICINA = 'JEFE_OFICINA',
    JEFE_DEPTO = 'JEFE_DEPTO',
}
