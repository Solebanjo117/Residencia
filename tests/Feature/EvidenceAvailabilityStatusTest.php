<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceSubmission;
use App\Models\SubmissionWindow;
use App\Services\EvidenceFlowService;

it('only reports blocked status before the submission window opens', function () {
    $service = new EvidenceFlowService;

    $futureWindow = new SubmissionWindow([
        'opens_at' => now()->addDay(),
        'closes_at' => now()->addDays(2),
    ]);
    $openWindow = new SubmissionWindow([
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
    ]);

    $upcoming = $service->resolveAvailability($futureWindow, false);
    $openDespitePreviousStages = $service->resolveAvailability($openWindow, false);

    expect($upcoming['code'])->toBe('UPCOMING')
        ->and($service->uiStatus(null, $upcoming))->toBe('BL')
        ->and($openDespitePreviousStages['code'])->toBe('OPEN')
        ->and($service->uiStatus(null, $openDespitePreviousStages))->toBe('NE');
});

it('does not turn unavailable non-date conditions into blocked status', function () {
    $service = new EvidenceFlowService;
    $draft = new EvidenceSubmission(['status' => SubmissionStatus::DRAFT]);

    $notConfigured = $service->resolveAvailability(null, true);

    expect($notConfigured['code'])->toBe('NOT_CONFIGURED')
        ->and($service->uiStatus($draft, $notConfigured))->toBe('NE')
        ->and($service->uiStatus(null, $service->notApplicableAvailability()))->toBe('NA');
});
