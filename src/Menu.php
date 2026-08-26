<?php

namespace GlpiPlugin\Contractnotice;

use CommonGLPI;

final class Menu extends CommonGLPI
{
    public static function getMenuName($nb = 0): string
    {
        return __('Disparar aviso', 'contractnotice');
    }

    public static function getMenuContent(): array
    {
        global $CFG_GLPI;

        $menu = [];
        if (Manager::canManage()) {
            $menu['title'] = self::getMenuName();
            $menu['page'] = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/announcements.php';
        }
        $menu['icon'] = self::getIcon();

        return $menu;
    }

    public static function getIcon(): string
    {
        return 'ti ti-bell-ringing';
    }
}
