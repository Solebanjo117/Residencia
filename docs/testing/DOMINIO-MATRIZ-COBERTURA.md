# Matriz de cobertura de dominio

## Objetivo
Asegurar proteccion de reglas institucionales criticas con suites ejecutables y comandos estandar.

## Suites y comandos
- Suite completa: `php artisan test`
- Suite de dominio: `composer test:domain`
- Regresion critica: `composer test:critical`

## Cobertura critica por regla
| Regla institucional | Estado | Evidencia automatizada |
|---|---|---|
| Solo Jefatura Oficina revisa evidencias | Cubierta | `tests/Feature/Security/AsesoriasReviewAuthorizationTest.php`, `tests/Feature/Security/OfficeReviewStatusAuthorizationTest.php` |
| Transiciones de estado permitidas | Cubierta | `tests/Feature/Domain/EvidenceStatusTransitionsTest.php` |
| Upload no fuerza submit y respeta ventana/prorroga | Cubierta | `tests/Feature/Security/FileManagerUploadWorkflowTest.php`, `tests/Feature/Teacher/EvidenceUnlockWindowTest.php` |
| No solapamiento de ventanas activas | Cubierta | `tests/Feature/Admin/SubmissionWindowOverlapValidationTest.php` |
| Alcance JEFE_DEPTO por carpeta y contenido | Cubierta | `tests/Feature/Security/JefeDeptoFolderScopeTest.php` |
| Consolidacion de ruta viva de seguimiento | Cubierta | `tests/Feature/Seguimiento/AsesoriasConsolidationTest.php` |
| Carga de archivos desde celdas de seguimiento | Cubierta | `tests/Feature/Seguimiento/CellUploadTest.php` |
| Notificaciones accionables | Cubierta | `tests/Feature/NotificationActionUrlTest.php` |
| Dashboard por rol | Cubierta | `tests/Feature/DashboardOverviewTest.php` |
| Reportes de oficina | Cubierta | `tests/Feature/Admin/OfficeReportsTest.php` |
| Auditoria y saneamiento historico | Cubierta | `tests/Feature/Console/AuditHistoricalDataCommandTest.php` |

## Brechas vigentes de cobertura
| Riesgo vigente | Estado | Referencia |
|---|---|---|
| Unificacion de programacion de notificaciones (`NotifyWindows` vs job) | Parcial | `tests/Feature/Console/NotifyWindowsCommandTest.php` cubre el comando, pero no una estrategia unica |
| Heuristica carpeta -> evidencia en File Manager | Parcial | `tests/Feature/Security/FileManagerUploadWorkflowTest.php` cubre casos principales, falta prueba de renombre/alias explicito |
| `applies_condition` como regla viva | Pendiente | Existe metadata y clonacion, falta flujo operativo/UI si se decide activarlo |
| `AdvisoryFile` | Pendiente condicionado | Falta decidir si sigue en alcance funcional |

## Convenciones para nuevas pruebas
1. Priorizar `Feature` para reglas de negocio con autorizacion, validacion y persistencia.
2. Usar factories + datos minimos por caso, evitando fixtures globales rigidos.
3. Nombrar archivos de prueba por regla funcional, no por controlador.
4. Incluir al menos un caso negativo por regla critica.

## Criterio operativo
- Todo PR que toque flujo institucional debe ejecutar `composer test:critical`.
- Cambios de dominio o seguridad deben ejecutar ademas `composer test:domain`.
