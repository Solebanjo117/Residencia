# Residencia Project Memory

## 📍 START HERE

**Full project status, architecture, problems, and next steps:** See [PROJECT_STATUS.md](PROJECT_STATUS.md)

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
- `/oficina/*` - role:JEFE_OFICINA
- `/depto/*` - role:JEFE_DEPTO (redirects to /admin)
- `/admin/*` - role:JEFE_OFICINA,JEFE_DEPTO (shared admin)
- `/files/*` - file manager (auth only, policy-based)
- `/asesorias` - authenticated (inside auth group)

## Recent Session Work (2026-03-16)

✅ Folder structure generation at semester creation
✅ Teacher folder generation button in admin
✅ File manager access control (roles can see all, depto sees all, docente sees own)
✅ Dashboard folder structure: `Semestre > Docente > [Horario, Instrumentaciones, Evaluacion, Evidencias, Proyectos]`

⚠️ Known Issues — See PROJECT_STATUS.md for full list:
- Dashboard.vue is empty placeholder
- Oficina/Reports.vue is stub
- Asesorias status changes don't persist
- No seeders for production data
- Notifications job never dispatched
