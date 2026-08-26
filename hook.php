<?php

use GlpiPlugin\Contractnotice\AnnouncementRepository;

function plugin_contractnotice_install(): bool
{
    global $DB;

    $charset = \DBConnection::getDefaultCharset();
    $collation = \DBConnection::getDefaultCollation();
    $migration = new \Migration(PLUGIN_CONTRACTNOTICE_VERSION);
    $announcementsTable = AnnouncementRepository::getAnnouncementsTable();
    $targetsTable = AnnouncementRepository::getTargetsTable();
    $acknowledgementsTable = AnnouncementRepository::getAcknowledgementsTable();

    if (!$DB->tableExists($announcementsTable)) {
        $DB->doQuery("CREATE TABLE `$announcementsTable` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `content` TEXT NOT NULL,
            `target_type` VARCHAR(16) NOT NULL,
            `delivery_mode` VARCHAR(16) NOT NULL,
            `start_at` DATETIME NOT NULL,
            `end_at` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT '1',
            `users_id` INT UNSIGNED NOT NULL DEFAULT '0',
            `date_creation` DATETIME NOT NULL,
            `date_mod` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `active_schedule` (`is_active`, `start_at`, `end_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    }

    if (!$DB->tableExists($targetsTable)) {
        $DB->doQuery("CREATE TABLE `$targetsTable` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_contractnotice_announcements_id` INT UNSIGNED NOT NULL,
            `target_type` VARCHAR(16) NOT NULL,
            `targets_id` INT UNSIGNED NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `announcement` (`plugin_contractnotice_announcements_id`),
            KEY `target` (`target_type`, `targets_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    }

    if (!$DB->tableExists($acknowledgementsTable)) {
        $DB->doQuery("CREATE TABLE `$acknowledgementsTable` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_contractnotice_announcements_id` INT UNSIGNED NOT NULL,
            `users_id` INT UNSIGNED NOT NULL,
            `acknowledged_day` DATE NOT NULL,
            `announcement_date_mod` DATETIME NOT NULL,
            `date_creation` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `daily_acknowledgement` (`plugin_contractnotice_announcements_id`, `users_id`, `acknowledged_day`, `announcement_date_mod`),
            KEY `user_day` (`users_id`, `acknowledged_day`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    }

    $migration->executeMigration();

    return true;
}

function plugin_contractnotice_uninstall(): bool
{
    global $DB;

    foreach ([
        AnnouncementRepository::getAcknowledgementsTable(),
        AnnouncementRepository::getTargetsTable(),
        AnnouncementRepository::getAnnouncementsTable(),
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}
