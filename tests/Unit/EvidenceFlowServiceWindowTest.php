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
