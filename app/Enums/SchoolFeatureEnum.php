<?php

namespace App\Enums;

enum SchoolFeatureEnum: string
{
    case CBT = 'cbt';
    case E_LEARNING = 'e_learning';
    case ANNOUNCEMENT = 'announcement';
    case ATTENDANCE = 'attendance';
    case QUESTION_BANK = 'question_bank';
    case RESULT_SHEET = 'result_sheet';
    
}
