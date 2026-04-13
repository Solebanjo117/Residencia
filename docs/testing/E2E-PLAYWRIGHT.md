# Pruebas E2E con Playwright

## Objetivo
Validar humo operativo por rol institucional (DOCENTE, JEFE_OFICINA, JEFE_DEPTO).

## Estructura
- Configuracion: `playwright.config.ts`
- Specs:
  - `tests/e2e/docente.smoke.spec.ts`
  - `tests/e2e/oficina.smoke.spec.ts`
  - `tests/e2e/depto.smoke.spec.ts`

## Usuarios de prueba esperados
Sembrados por `DatabaseSeeder`:
- `docente1@example.com`
- `oficina@example.com`
- `depto@example.com`

Password para todos: `password`

## Ejecucion local
1. Preparar datos:
```powershell
npm run e2e:prepare
```

2. Instalar navegador de Playwright (primera vez):
```powershell
npm run e2e:install
```

3. Ejecutar E2E:
```powershell
npm run e2e
```

## Criterio de humo
- Login exitoso por rol.
- Acceso permitido a ruta principal de cada rol.
- Bloqueo 403 en ruta fuera de alcance.
