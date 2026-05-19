# Planes de Implementacion ASAD

Este directorio contiene un plan individual por tarea del backlog.
El objetivo es ejecutar cambios de forma ordenada, auditable y con validacion por cada entrega.

## Estado actual
Los planes listados aqui son registro historico de ejecucion. Al corte 2026-05-18, los planes ASAD-P0 a ASAD-P7 estan cerrados o cerrados con limitaciones documentadas en su archivo individual.

El backlog vigente ya no es este orden completo. La fuente viva de pendientes esta en [../../PROJECT_STATUS.md](../../PROJECT_STATUS.md), seccion "Pendientes Vigentes".

Pendientes vigentes resumidos:
1. `P1-NOTIF-01`: unificar `NotifyWindows` y `SendScheduledNotificationsJob`.
2. `P1-FILE-01`: reemplazar heuristica carpeta -> rubro de evidencia.
3. `P2-APPLIES-01`: activar o retirar del alcance operativo `applies_condition`.
4. `P2-ADVISORY-01`: confirmar alcance real de `AdvisoryFile`.
5. `P3-DOCS-01`: marcar documentos historicos como historicos y evitar backlog duplicado.

## Como usar cada plan
1. Ejecutar primero la seccion "Investigacion previa".
2. Implementar los pasos en el orden indicado.
3. Correr la seccion "Validacion" y guardar evidencia.
4. Marcar "Estado" al cerrar.

## Archivos de planes
- ASAD-P0-00-baseline-ruta-viva-legado.md
- ASAD-P0-01-blindaje-endpoint-revision.md
- ASAD-P0-02-refactor-file-manager-estados.md
- ASAD-P0-03-endurecer-transiciones-estado.md
- ASAD-P1-01-habilitar-init-submission.md
- ASAD-P1-02-scheduler-notificaciones.md
- ASAD-P1-03-fix-department-relaciones-delete.md
- ASAD-P1-04-unificar-prorrogas.md
- ASAD-P2-01-matriz-formatos-archivo.md
- ASAD-P2-02-alcance-jefe-depto.md
- ASAD-P2-03-validacion-solapamiento-ventanas.md
- ASAD-P2-04-seeders-robustos.md
- ASAD-P3-01-dashboard-principal.md
- ASAD-P3-02-reportes-oficina.md
- ASAD-P3-03-consolidar-vistas-asesorias.md
- ASAD-P3-04-normalizar-idioma-ui.md
- ASAD-P4-01-saneamiento-datos-historicos.md
- ASAD-P4-02-limpieza-legado.md
- ASAD-P4-03-normalizacion-encoding.md
- ASAD-P5-01-estrategia-pruebas-dominio.md
- ASAD-P5-02-pruebas-e2e-por-rol.md
- ASAD-P5-03-pipeline-ci-gates.md
- ASAD-P6-01-observabilidad-operativa.md
- ASAD-P6-02-backup-recuperacion.md
- ASAD-P6-03-runbook-release-rollback.md
- ASAD-P7-01-hardening-seguridad-archivos.md

## Nota del reanalisis
Con el contexto adicional del proyecto, ASAD-P0-00 sirvio para congelar el alcance sobre rutas y vistas vivas. Esa decision ya fue incorporada al codigo: `/asesorias` usa `SeguimientoDocente.vue` como vista viva y la ruta legacy `asesorias2` no debe reabrirse.

## Baselines generados
- baseline-rutas-vivas-legado-2026-04-12.md
