<?php

namespace App\Enums;

enum TransactionType: string
{
    case DIRECT = 'DIRECT';
    case PR = 'PR';
    case SS_ENGG = 'SS-ENGG';

    case ENGG_SS = 'ENGG-SS';

    case SS_SS = 'SS-SS';
    case CS_SS = 'CS-SS';

}