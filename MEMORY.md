# Residencia Project Memory

## Start Here

**Full project status, architecture, resolved work, and current pending items:** See [PROJECT_STATUS.md](PROJECT_STATUS.md)

This file is a quick reference only.

---

## Stack
- Laravel 12 + PHP 8.2 + Fortify (2FA) + Inertia.js v2 + Vue 3 + TypeScript
- UI: shadcn-vue (reka-ui) + Tailwind CSS v4 + Lucide icons
- Build: Vite 7 + Wayfinder (typed routes)

## Roles
- `DOCENTE`, `JEFE_OFICINA`, `JEFE_DEPTO` (defined in `app/Models/Role.php`)
- Role middleware: `role:ROLE_NAME` alias registered in `bootstrap/app.php`

## Key Architecture
- **Services layer**: AuditService, EvidenceService, NotificationService, StorageService, FolderStructureService, AdvisoryService, TeacherService
- **Policies**: EvidenceSubmissionPolicy, AdvisorySessionPolicy, EvidenceFilePolicy, FolderNodePolicy
- **State machine**: Evidence status transitions validated in `EvidenceService::ALLOWED_TRANSITIONS`
- **Folder storage**: Hierarchical FolderNode tree under StorageRoot, managed by FolderStructureService

## Route Structure
- `/docente/*` - role:DOCENTE
- `/oficina/*` - role:JEFE_OFICINA,JEFE_DEPTO for review/report surfaces
- `/depto/*` - role:JEFE_DEPTO redirects to shared admin surfaces
- `/admin/*` - role:JEFE_OFICINA,JEFE_DEPTO
- `/files/*` - file manager, auth plus policies
- `/asesorias` - unified seguimiento docente surface

## Current Operational Snapshot (2026-05-18)

Resolved baseline:
- Folder structure generation at semester/carga creation.
- File manager access and evidence uploads without forcing `SUBMITTED`.
- Protected evidence review and final approval routes.
- Docente evidence initialization, unlocks, upload formats, window overlap checks.
- Dashboards, office reports, audit commands, backup/restore, operational logs.

Current pending items are intentionally narrow; see [PROJECT_STATUS.md](PROJECT_STATUS.md):
- Unify scheduled notification execution (`NotifyWindows` vs `SendScheduledNotificationsJob`).
- Replace File Manager folder-name heuristics with explicit evidence-item metadata.
- Decide whether `applies_condition` becomes a live rule or remains inactive metadata.
- Confirm whether `AdvisoryFile` is still in functional scope before adding file endpoints.
