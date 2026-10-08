<?php
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (
        !is_string($sent) ||
        empty($_SESSION['csrf']) ||
        !hash_equals($_SESSION['csrf'], $sent)
    ) {
        http_response_code(419);
        exit(
            'Your session expired. Please go back, refresh the page and try again.'
        );
    }
}
