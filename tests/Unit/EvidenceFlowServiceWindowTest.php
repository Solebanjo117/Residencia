<?php

use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Services\EvidenceFlowService;

it('prefers modality-specific submission windows and falls back to general', function () {
    $service = new EvidenceFlowService;
    $load = new TeachingLoad(['modality' => TeachingLoad::MODALITY_EN_LINEA]);

    $general = new SubmissionWindow(['modality' => null]);
    $online = new SubmissionWindow(['modality' => TeachingLoad::MODALITY_EN_LINEA]);

    expect($service->resolveWindowForLoad(collect([$general, $online]), $load))->toBe($online);
    expect($service->resolveWindowForLoad(collect([$general]), $load))->toBe($general);
});

it('maps evidence items to the configured institutional stages', function (string $itemName, int $order, string $label) {
    $service = new EvidenceFlowService;

    expect($service->stageOrder($itemName))->toBe($order)
        ->and($service->stageLabel($order))->toBe($label);
})->with([
    ['HORARIO', 0, 'Etapa inicial'],
    ['INSTRUM', 0, 'Etapa inicial'],
    ['EV.DIAGN', 10, 'SD1'],
    ['SEG 01', 10, 'SD1'],
    ['CALIF. PARCIALES', 10, 'SD1'],
    ['SEG 02', 20, 'SD2'],
    ['CALIF. PARCIALES 2', 20, 'SD2'],
    ['PROY IND', 20, 'SD2'],
    ['SEG 03', 30, 'SD3'],
    ['CALIF. PARCIALES 3', 30, 'SD3'],
    ['SEG 04 FINAL', 40, 'SD4'],
    ['CALIF. PARCIALES FINAL', 50, 'Etapa final'],
    ['REPORTES EVIDENCIAS ASIGNATURAS', 50, 'Etapa final'],
    ['REP FINAL', 50, 'Etapa final'],
    ['ACTAS FINALES', 50, 'Etapa final'],
]);
