<?php

namespace App\Enums;

enum EarnMode: string
{
    /** Points per amount spent, at the program's earn rate. */
    case Spend = 'spend';

    /** One point per visit. */
    case Visits = 'visits';
}
