<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case NA = 'NA';
    case NE = 'NE';
}
