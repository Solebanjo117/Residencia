<?php

namespace App\Enums;

enum AcademicPeriodStatus: string
{
    case PLANNED = 'PLANNED';
    case ACTIVE = 'ACTIVE';
    case CLOSED = 'CLOSED';
}
