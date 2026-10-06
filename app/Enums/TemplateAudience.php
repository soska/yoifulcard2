<?php

namespace App\Enums;

/**
 * Who can print with a card template (App\Enums\CardTemplate). Superadmins
 * print with every template; owners and managers of a business that can
 * preissue cards only with the business ones.
 */
enum TemplateAudience: string
{
    case Admin = 'admin';
    case Business = 'business';
}
