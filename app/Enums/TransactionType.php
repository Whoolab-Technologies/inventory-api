<?php

namespace App\Enums;

enum TransactionType: string
{
    case DIRECT = 'DIRECT';
    case CS_SS = 'CS-SS';
    case SS_CS = 'SS-CS';
    case SS_ENGG = 'SS-ENGG';

    case ENGG_SS = 'ENGG-SS';




}