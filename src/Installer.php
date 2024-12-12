<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace CorepulseBundle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Installer extends SettingsStoreAwareInstaller
{
    private array $tablesToInstall = [
        'corepulse_settings' => 'CREATE TABLE `corepulse_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` varchar(50) NOT NULL,
            `config` longtext DEFAULT NULL,
            `createAt` timestamp NULL DEFAULT current_timestamp(),
            `updateAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `type` (`type`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_users' => 'CREATE TABLE `corepulse_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `avatar` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `role` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `permission` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `defaultAdmin` tinyint(1) DEFAULT 0,
            `admin` tinyint(1) DEFAULT 0,
            `active` tinyint(1) DEFAULT 0,
            `authToken` longtext DEFAULT NULL,
            `createAt` timestamp NULL DEFAULT current_timestamp(),
            `updateAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_role' => 'CREATE TABLE `corepulse_role` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
            `permission` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `createAt` timestamp NULL DEFAULT current_timestamp(),
            `updateAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_plausible' => 'CREATE TABLE `corepulse_plausible` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `siteId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `apiKey` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_notification' => 'CREATE TABLE `corepulse_notification` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `user` int(11) NOT NULL,
            `sender` int(11) NOT NULL,
            `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `actionType` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `active` tinyint(1) DEFAULT 0,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_indexing' => 'CREATE TABLE `corepulse_indexing` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `internalType` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `internalValue` int(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `response` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `result` longtext DEFAULT NULL,
            `language` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_order_timeline' => 'CREATE TABLE `corepulse_order_timeline` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `orderId` int(11) NOT NULL,
            `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `createAt` timestamp DEFAULT current_timestamp(),
            `updateAt` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_translations' => 'CREATE TABLE `corepulse_translations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `language` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `creationDate` timestamp DEFAULT current_timestamp(),
            `modifictionDate` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_class' => 'CREATE TABLE `corepulse_class` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `className` varchar(255) NOT NULL,
            `visibleFields` LONGTEXT  DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `className` (`className`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        'corepulse_search_history' => 'CREATE TABLE `corepulse_search_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `userId` int(11) NOT NULL,
            `data` longtext DEFAULT NULL,
            `createAt` timestamp NULL DEFAULT current_timestamp(),
            `updateAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `userId` (`userId`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    ];



    protected ?Schema $schema = null;

    public function __construct(
        protected BundleInterface $bundle,
        protected Connection $db
    ) {
        parent::__construct($bundle);
    }

    protected function addPermissions(): void
    {
        // $db = \Pimcore\Db::get();

        // foreach (self::USER_PERMISSIONS as $permission) {
        //     $db->insert('users_permission_definitions', [
        //         $db->quoteIdentifier('key') => $permission,
        //         $db->quoteIdentifier('category') => self::USER_PERMISSIONS_CATEGORY,
        //     ]);
        // }
    }

    protected function removePermissions(): void
    {
        // $db = \Pimcore\Db::get();

        // foreach (self::USER_PERMISSIONS as $permission) {
        //     $db->delete('users_permission_definitions', [
        //         $db->quoteIdentifier('key') => $permission,
        //     ]);
        // }
    }

    public function install(): void
    {
        $this->addPermissions();
        $this->installTables();
        parent::install();
    }

    private function installTables(): void
    {
        foreach ($this->tablesToInstall as $name => $statement) {
            if ($this->getSchema()->hasTable($name)) {
                $this->output->write(sprintf(
                    '     <comment>WARNING:</comment> Skipping table "%s" as it already exists',
                    $name
                ));

                continue;
            }

            $this->db->executeQuery($statement);
        }
    }

    private function uninstallTables(): void
    {
        foreach (array_keys($this->tablesToInstall) as $table) {
            if (!$this->getSchema()->hasTable($table)) {
                $this->output->write(sprintf(
                    '     <comment>WARNING:</comment> Not dropping table "%s" as it doesn\'t exist',
                    $table
                ));

                continue;
            }

            $this->db->executeQuery("DROP TABLE IF EXISTS $table");
        }
    }

    public function uninstall(): void
    {
        $this->removePermissions();
        $this->uninstallTables();

        parent::uninstall();
    }

    protected function getSchema(): Schema
    {
        return $this->schema ??= $this->db->createSchemaManager()->introspectSchema();
    }
}
