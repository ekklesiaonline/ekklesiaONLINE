<?php

/**
 * Backward-compatibility shim for Treasurer language strings.
 * All financial strings are canonically maintained in Finance.php.
 */
$finance = require __DIR__ . '/Finance.php';

return array_merge($finance, [
    // Preserve legacy specific wording for fundLocked
    'fundLocked' => match ('es') {
        'es' => 'El fondo seleccionado esta bloqueado y no puede recibir transacciones.',
        'fr' => 'Le fonds selectionne est verrouille et ne peut pas recevoir de transactions.',
        default => 'The selected fund is locked and cannot receive transactions.',
    },
]);
