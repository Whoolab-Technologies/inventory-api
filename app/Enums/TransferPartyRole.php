<?php

namespace App\Enums;

enum TransferPartyRole: string
{
    case CENTRAL_STORE = 'CENTRAL STORE';
    case SITE_STORE = 'SITE STORE';
    case ENGINEER = 'ENGINEER';
}