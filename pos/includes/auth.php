<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

function pos_is_manager(?array $user = null): bool
{
    $user = $user ?? pos_current_user();
    return $user && ($user['role'] ?? '') === 'manager';
}

function pos_require_manager(): array
{
    $user = pos_require_setup_complete();
    if (!pos_is_manager($user)) {
        throw new RuntimeException('Manager access required.');
    }
    return $user;
}

function pos_can_delete(?array $user = null): bool
{
    return pos_is_manager($user);
}
