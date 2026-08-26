<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Contractnotice\Manager;
use GlpiPlugin\Contractnotice\Menu;

define('PLUGIN_CONTRACTNOTICE_VERSION', '2.0.8');
define('PLUGIN_CONTRACTNOTICE_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_CONTRACTNOTICE_MAX_GLPI_VERSION', '11.1.0');
define('PLUGIN_CONTRACTNOTICE_MANAGER_PROFILE', 'X GERENTE GLPI');

/**
 * Initialise the Contract Notice plugin.
 */
function plugin_init_contractnotice(): void
{
    global $PLUGIN_HOOKS;

    if (!Plugin::isPluginActive('contractnotice')) {
        return;
    }

    if (Session::getLoginUserID() > 0) {
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['contractnotice'] = [
            'js/contract-notice-2.0.4.js',
        ];

        if (Manager::canManage()) {
            $PLUGIN_HOOKS[Hooks::MENU_TOADD]['contractnotice'] = [
                'admin' => Menu::class,
            ];
        }
    }
}

/**
 * Declare plugin metadata and GLPI compatibility.
 *
 * @return array<string, mixed>
 */
function plugin_version_contractnotice(): array
{
    return [
        'name'         => 'Aviso de Contratos',
        'version'      => PLUGIN_CONTRACTNOTICE_VERSION,
        'author'       => 'Equipe TI CSC',
        'license'      => 'MIT',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CONTRACTNOTICE_MIN_GLPI_VERSION,
                'max' => PLUGIN_CONTRACTNOTICE_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

function plugin_contractnotice_check_prerequisites(): bool
{
    return version_compare(GLPI_VERSION, PLUGIN_CONTRACTNOTICE_MIN_GLPI_VERSION, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_CONTRACTNOTICE_MAX_GLPI_VERSION, '<');
}

function plugin_contractnotice_check_config(bool $verbose = false): bool
{
    return true;
}
