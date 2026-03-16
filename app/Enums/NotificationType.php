<?php

namespace App\Enums;

enum NotificationType: string
{
    case NEW_ASSIGNMENT = 'NEW_ASSIGNMENT';
    case WINDOW_OPEN = 'WINDOW_OPEN';
    case WINDOW_CLOSING = 'WINDOW_CLOSING';
    case SUBMISSION_APPROVED = 'SUBMISSION_APPROVED';
    case SUBMISSION_REJECTED = 'SUBMISSION_REJECTED';
    case GENERAL = 'GENERAL';
}
