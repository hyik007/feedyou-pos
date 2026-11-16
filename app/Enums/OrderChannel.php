<?php

namespace App\Enums;

enum OrderChannel: string
{
    case Pos = 'pos';   // cashier counter
    case Qr = 'qr';     // customer scans table QR
    case Ai = 'ai';     // natural-language / voice order via AI parser
}
