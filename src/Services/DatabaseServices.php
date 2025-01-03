<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;

class DatabaseServices
{
    const COREPULSE_INDEXING_TABLE = 'corepulse_indexing';
    const COREPULSE_NOTIFICATION_TABLE = 'corepulse_notification';
    const COREPULSE_PLAUSIBLE_TABLE = 'corepulse_plausible';
    const COREPULSE_ORDER_TIMELINE_TABLE = 'corepulse_order_timeline';
    const COREPULSE_CLASS_TABLE = 'corepulse_class';
    const COREPULSE_TRANSLATION_TABLE = 'corepulse_translations';
    const COREPULSE_USER_TABLE = 'corepulse_users';
    const COREPULSE_SEARCH_HISTORY = 'corepulse_search_history';
    const COREPULSE_ROLE_TABLE = 'corepulse_role';

    public static function createTables()
    {
        self::createCorepulseIndexing();
        self::createCorepulseNotification();
        self::createCorepulseOrderTimeline();
        self::createCorepulseClass();
        self::createCorepulseTranslation();
        self::initUserDefault();
        self::createCorepulseSearchHistory();
        self::createCorepulseRole();
    }

    public static function initUserDefault()
    {
        $password = '$2y$12$ZtZ.pFfXxlDRuH9ivcjlRO80XKiKeHBCtVc7n1skI8IxAvwebDo9W';

        $query = " INSERT INTO " . self::COREPULSE_USER_TABLE . " (`id`, `username`, `password`,`defaultAdmin`, `admin`, `active`)
                SELECT 1, 'admin', '$password', 1, 1, 1
                WHERE NOT EXISTS (
                    SELECT 1 FROM `corepulse_users` WHERE `id` = 1
                )";

        Db::get()->executeQuery($query);
    }

    public static function updateTables()
    {
        self::createTables();
        self::updateCorepulseIndexing();
        self::updateCorepulseUser();
        self::updateCorepulsePlausible();
        self::updateCorepulseOrderTimeline();
        self::updateCorepulseRole();
    }

    public static function createCorepulseIndexing()
    {
        $query = "CREATE TABLE IF NOT EXISTS " . self::COREPULSE_INDEXING_TABLE . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `url` varchar(255) DEFAULT NULL,
            `type` varchar(255) DEFAULT NULL,
            `response` varchar(255) DEFAULT NULL,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        Db::get()->executeQuery($query);
    }

    public static function createCorepulseNotification()
    {
        $query = " CREATE TABLE IF NOT EXISTS " . self::COREPULSE_NOTIFICATION_TABLE . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255)  DEFAULT NULL,
            `description` varchar(255)  DEFAULT NULL,
            `user` int(11) NOT NULL,
            `sender` int(11) NOT NULL,
            `type` varchar(255)  DEFAULT NULL,
            `action` varchar(255)  DEFAULT NULL,
            `actionType` varchar(255)  DEFAULT NULL,
            `active` tinyint(1) DEFAULT 0,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        COMMIT;";

        Db::get()->executeQuery($query);
    }

    public static function updateCorepulseIndexing()
    {
        $query = " ALTER TABLE " . self::COREPULSE_INDEXING_TABLE . "
            ADD COLUMN IF NOT EXISTS `id` int(11) NOT NULL AUTO_INCREMENT,
            ADD COLUMN IF NOT EXISTS `url` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `type` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `time` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `response` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `internalType` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `internalValue` int(11) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `status` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `result` longtext DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `language` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `createAt` timestamp DEFAULT current_timestamp(),
            ADD COLUMN IF NOT EXISTS `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp();
        ";

        Db::get()->executeQuery($query);
    }

    public static function updateCorepulseUser()
    {
        $query = "ALTER TABLE `corepulse_users`
            ADD COLUMN IF NOT EXISTS `authToken` longtext DEFAULT NULL";

        Db::get()->executeQuery($query);
    }

    public static function updateCorepulsePlausible()
    {
        $query = " ALTER TABLE " . self::COREPULSE_PLAUSIBLE_TABLE . "
            ADD COLUMN IF NOT EXISTS `id` int(11) NOT NULL AUTO_INCREMENT,
            ADD COLUMN IF NOT EXISTS `domain` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `siteId` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `apiKey` varchar(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `username` varchar(190) NOT NULL,
            ADD COLUMN IF NOT EXISTS `password` varchar(255) NOT NULL,
            ADD COLUMN IF NOT EXISTS `link` varchar(255) DEFAULT NULL;
        ";

        Db::get()->executeQuery($query);
    }

    public static function createCorepulseOrderTimeline()
    {
        $query = " CREATE TABLE IF NOT EXISTS " . self::COREPULSE_ORDER_TIMELINE_TABLE . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `orderId` int(11) NOT NULL,
            `title` varchar(255)  DEFAULT NULL,
            `description` varchar(255)  DEFAULT NULL,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        COMMIT;";

        Db::get()->executeQuery($query);
    }

    public static function updateCorepulseOrderTimeline()
    {
        $query = " ALTER TABLE " . self::COREPULSE_ORDER_TIMELINE_TABLE . "
            ADD COLUMN IF NOT EXISTS `id` int(11) NOT NULL AUTO_INCREMENT,
            ADD COLUMN IF NOT EXISTS `orderId` int(11) NOT NULL,
            ADD COLUMN IF NOT EXISTS `title` varchar(255)  DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `description` varchar(255)  DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `createAt` timestamp DEFAULT current_timestamp(),
            ADD COLUMN IF NOT EXISTS `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp();
        ";

        Db::get()->executeQuery($query);
    }

    public static function createCorepulseClass()
    {
        $query = " CREATE TABLE IF NOT EXISTS " . self::COREPULSE_CLASS_TABLE . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `className` varchar(255) NOT NULL,
            `visibleFields` LONGTEXT  DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        COMMIT;";

        Db::get()->executeQuery($query);
    }

    public static function createCorepulseTranslation()
    {
        $query = " CREATE TABLE IF NOT EXISTS " . self::COREPULSE_TRANSLATION_TABLE . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `language` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `creationDate` timestamp DEFAULT current_timestamp(),
            `modifictionDate` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        COMMIT;";

        Db::get()->executeQuery($query);
    }

    public static function createCorepulseSearchHistory()
    {
        $query = " CREATE TABLE IF NOT EXISTS " . self::COREPULSE_SEARCH_HISTORY . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `userId` int(11) NOT NULL,
            `data` longtext DEFAULT NULL,
            `createAt` timestamp NULL DEFAULT current_timestamp(),
            `updateAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `userId` (`userId`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        COMMIT;";

        Db::get()->executeQuery($query);
    }

    public static function createCorepulseRole()
    {
        $query = " CREATE TABLE IF NOT EXISTS " . self::COREPULSE_ROLE_TABLE . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255)  DEFAULT NULL,
            `permission` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        COMMIT;";

        Db::get()->executeQuery($query);
    }

    public static function updateCorepulseRole()
    {
        $query = " ALTER TABLE " . self::COREPULSE_ROLE_TABLE . "
            ADD COLUMN IF NOT EXISTS `id` int(11) NOT NULL AUTO_INCREMENT,
            ADD COLUMN IF NOT EXISTS `orderId` int(11) NOT NULL,
            ADD COLUMN IF NOT EXISTS `name` varchar(255)  DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `permission` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS `createAt` timestamp DEFAULT current_timestamp(),
            ADD COLUMN IF NOT EXISTS `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            ADD UNIQUE KEY IF NOT EXISTS `name` (`name`);";

        Db::get()->executeQuery($query);
    }
}
