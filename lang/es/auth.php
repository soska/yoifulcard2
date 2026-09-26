<?php

declare(strict_types=1);

/*
 * Sourced from laravel-lang/common (`php artisan lang:add es`), then EDITED.
 * laravel-lang's Spanish addresses the reader as USTED; Yoiful uses TÚ
 * everywhere (resources/js/locales/style/es.md), and these strings appear on
 * the same screens as copy that already does.
 *
 * `php artisan lang:update` will overwrite the edits. Re-apply them: the only
 * changes are second-person verb forms and possessives.
 * tests/Feature/Locale/I18nBridgeTest.php (`framework spanish uses tú`) fails
 * if an usted form comes back.
 */

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Por favor inténtalo de nuevo en :seconds segundos.',
];
