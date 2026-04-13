# ASAD-P1-03 - Fix Department relaciones y delete seguro

## Objetivo
Corregir relaciones faltantes en Department para evitar error runtime al eliminar.

## Alcance inicial
- app/Models/Department.php
- app/Http/Controllers/Admin/DepartmentController.php
- tests/Feature/Admin (departments)

## Investigacion previa
1. Confirmar relaciones utilizadas por `destroy()`.
2. Revisar FKs en `evidence_requirements` y `user_department`.
3. Validar semantica de `teachers` vs `users` en modelo.

## Implementacion paso a paso
1. Agregar relaciones faltantes al modelo Department.
2. Ajustar controlador para usar relaciones reales.
3. Homologar mensajes de error para dependencias activas.

## Validacion
- php artisan test tests/Feature/Admin/DepartmentDeletionGuardTest.php

## Criterio de cierre
1. No hay excepcion por metodo inexistente.
2. Eliminacion bloquea cuando hay dependencias.

## Resultado de ejecucion
1. `Department` ahora expone relaciones `teachers()` y `requirements()`.
2. `DepartmentController::destroy()` ya valida dependencias sin errores runtime.
3. Pruebas agregadas en `tests/Feature/Admin/DepartmentDeletionGuardTest.php`.
4. Resultado de pruebas: 3 passed, 0 failed.

## Riesgos
- Cambio de nombre de relacion puede afectar consultas existentes.

## Estimacion
3 a 5 horas

## Estado
Completado
