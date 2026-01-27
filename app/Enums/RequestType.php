<?php

namespace App\Enums;

enum RequestType: string
{
    case DIRECT = 'DIRECT';
    case MR = 'MR';
    case PR = 'PR';

    case SS_RETURN = 'SS-RETURN';
    case ENGG_RETURN = 'ENGG-RETURN';
    case DISPATCH = 'DISPATCH';

}