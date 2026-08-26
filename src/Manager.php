<?php

namespace GlpiPlugin\Contractnotice;

use Glpi\Exception\Http\AccessDeniedHttpException;

final class Manager
{
    public static function canManage(): bool
    {
        return ($_SESSION['glpiactiveprofile']['name'] ?? '') === PLUGIN_CONTRACTNOTICE_MANAGER_PROFILE;
    }

    public static function checkCanManage(): void
    {
        if (!self::canManage()) {
            throw new AccessDeniedHttpException();
        }
    }
}
