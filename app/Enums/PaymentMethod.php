<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case DuitNow = 'duitnow';
    case EWallet = 'ewallet';
}
