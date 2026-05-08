# AGENTS.md

Guidance for AI coding agents working in this repository.

## Start Here
- Read [MEMORY.md](MEMORY.md) for a quick project map.
- Read [PROJECT_STATUS.md](PROJECT_STATUS.md) for current priorities and validated findings.
- Use docs as source of truth; link to docs instead of copying large sections into PR notes.

High-value docs:
- [docs/CONTRIBUCION-ENCODING.md](docs/CONTRIBUCION-ENCODING.md)
- [docs/testing/DOMINIO-MATRIZ-COBERTURA.md](docs/testing/DOMINIO-MATRIZ-COBERTURA.md)
- [docs/testing/E2E-PLAYWRIGHT.md](docs/testing/E2E-PLAYWRIGHT.md)
- [docs/planes-implementacion/README.md](docs/planes-implementacion/README.md)

## Stack And Structure
- Backend: Laravel 12 / PHP 8.2.
- Frontend: Inertia.js + Vue 3 + TypeScript + Vite.
- Testing: Pest/PHPUnit + Playwright.

Main code locations:
- `app/Http/Controllers` for HTTP endpoints.
- `app/Services` for business logic.
- `app/Policies` for authorization rules.
- `app/Models` and `app/Enums` for domain model/state.
- `resources/js/pages` and `resources/js/components` for UI.
- `routes/web.php` for role-grouped routes.

## Non-Negotiable Conventions
- Keep controllers thin; place domain workflow in services.
- Enforce authorization with both route middleware and policy checks.
- For evidence state changes, use `App\Services\EvidenceService::changeStatus()` rather than direct status assignment.
- Keep auditability intact when touching critical flows (status, review, file operations).
- Follow role constants from `App\Models\Role` (`DOCENTE`, `JEFE_OFICINA`, `JEFE_DEPTO`).

## Commands Agents Should Run
- Setup: `composer setup`
- Dev (server + queue + vite): `composer dev`
- PHP lint/fix: `composer lint`
- PHP lint check: `composer test:lint`
- Critical regression suite: `composer test:critical`
- Domain/security suite: `composer test:domain`
- Full test suite: `composer test`
- Frontend lint check: `npm run lint:check`
- Frontend build: `npm run build`
- E2E: `npm run e2e:prepare`, `npm run e2e:install`, `npm run e2e`

## Environment Notes (Windows)
- Prefer `rg`/`rg --files` when available.
- If `rg` is unavailable in PowerShell, use `Select-String` on scoped folders (`app`, `routes`, `resources`, `tests`, `docs`).
- Keep text files UTF-8 without BOM and line endings LF (see [docs/CONTRIBUCION-ENCODING.md](docs/CONTRIBUCION-ENCODING.md)).

## Change Workflow
1. Locate the existing pattern in nearby code before editing.
2. Implement minimal focused changes; avoid unrelated refactors.
3. Run the smallest relevant test/lint command first, then broader checks.
4. If behavior changes, update or add tests in matching suite folders.
5. Reference docs in final notes rather than duplicating long explanations.
