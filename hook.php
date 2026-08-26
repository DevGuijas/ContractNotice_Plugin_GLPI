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

    $migration->executeMigration();
    AnnouncementRepository::ensureInitialAnnouncement();

    return true;
}

function plugin_contractnotice_uninstall(): bool
{
    global $DB;

    foreach ([
        AnnouncementRepository::getTargetsTable(),
        AnnouncementRepository::getAnnouncementsTable(),
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}
