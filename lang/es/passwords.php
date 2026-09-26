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
    'reset' => 'Tu contraseña ha sido restablecida.',
    'sent' => 'Te hemos enviado por correo electrónico el enlace para restablecer tu contraseña.',
    'throttled' => 'Por favor espera antes de volver a intentarlo.',
    'token' => 'El token de restablecimiento de contraseña es inválido.',
    'user' => 'No encontramos ningún usuario con ese correo electrónico.',
];
