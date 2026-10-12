<?php
/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2025 by Lukáš Kraic (lukas.kraic@gmail.com)
 -------------------------------------------------------------------------
 */

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraSync;
use GlpiPlugin\EntraHierarchy\EntraAuth;
use Glpi\Http\Firewall;

define('PLUGIN_ENTRAHIERARCHY_VERSION', '1.4.9');

/**
 * Initialize the plugin
 */
function plugin_init_glpientrahierarchy()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpientrahierarchy'] = true;

    $Plugin = new Plugin();
    if ($Plugin->isActivated('glpientrahierarchy')) {
        Firewall::addPluginStrategyForLegacyScripts(
            'glpientrahierarchy',
            '#^/front/oauth_login\.php#',
            Firewall::STRATEGY_NO_CHECK
        );
        Firewall::addPluginStrategyForLegacyScripts(
            'glpientrahierarchy',
            '#^/front/oauth_callback\.php#',
            Firewall::STRATEGY_NO_CHECK
        );
        Firewall::addPluginStrategyForLegacyScripts(
            'glpientrahierarchy',
            '#^/ajax/.*\.php#',
            Firewall::STRATEGY_AUTHENTICATED
        );
    }

    Plugin::registerClass(EntraConfig::class, ['addtabon' => 'Config']);
    Plugin::registerClass(EntraSync::class);
    Plugin::registerClass(EntraAuth::class);

    $PLUGIN_HOOKS['display_login']['glpientrahierarchy'] = 'plugin_glpientrahierarchy_display_login';

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['glpientrahierarchy'] = 'front/config.form.php';
    }
}

/**
 * Get plugin version
 */
function plugin_version_glpientrahierarchy()
{
    return [
        'name'           => __('Entra Hierarchy Sync', 'glpientrahierarchy'),
        'version'        => PLUGIN_ENTRAHIERARCHY_VERSION,
        'author'         => 'Lukáš Kraic (lukas.kraic@gmail.com)',
        'license'        => 'GPL-3.0+',
        'homepage'       => 'https://github.com/glpi-project/glpientrahierarchy',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0',
                'max' => '13.0',
            ],
            'php' => [
                'min' => '8.2',
            ]
        ]
    ];
}

/**
 * Install the plugin - create tables and cron tasks
 */
function plugin_glpientrahierarchy_install()
{
    global $DB;

    // 1. Config table
    if (!$DB->tableExists('glpi_plugin_entrahierarchy_configs')) {$query = "CREATE TABLE `glpi_plugin_entrahierarchy_configs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `client_id` varchar(255) DEFAULT NULL,
            `client_secret` varchar(255) DEFAULT NULL,
            `tenant_id` varchar(255) DEFAULT NULL,
            `sync_enabled` tinyint NOT NULL DEFAULT '0',
            `sync_interval` int NOT NULL DEFAULT '1800',
            `last_sync` datetime DEFAULT NULL,
            `sync_filter_active_only` tinyint NOT NULL DEFAULT '1',
            `sync_filter_account_enabled` tinyint NOT NULL DEFAULT '1',
            `sync_filter_user_type` varchar(50) DEFAULT 'Member',
            `sync_filter_employee_types` text DEFAULT NULL,
            `sync_filter_require_job_title` tinyint NOT NULL DEFAULT '0',
            `sync_filter_department` varchar(255) DEFAULT NULL,
            `sync_filter_company_name` varchar(255) DEFAULT NULL,
            `sync_filter_office_location` varchar(255) DEFAULT NULL,
            `deleted_users_action` varchar(50) DEFAULT 'keep_active',
            `default_profiles_id` int NOT NULL DEFAULT '1',
            `default_entities_id` int NOT NULL DEFAULT '0',
            `profile_is_recursive` tinyint NOT NULL DEFAULT '1',
            `default_groups_id` int NOT NULL DEFAULT '0',
            `default_locations_id` int NOT NULL DEFAULT '0',
            `default_usercategories_id` int NOT NULL DEFAULT '0',
            `default_language` varchar(10) DEFAULT NULL,
            `automap_department_to_group` tinyint NOT NULL DEFAULT '0',
            `automap_company_to_entity` tinyint NOT NULL DEFAULT '0',
            `automap_office_to_location` tinyint NOT NULL DEFAULT '0',
            `sync_hourmin` int NOT NULL DEFAULT '0',
            `sync_hourmax` int NOT NULL DEFAULT '24',
            `oauth_enabled` tinyint NOT NULL DEFAULT '0',
            `oauth_client_id` varchar(255) DEFAULT NULL,
            `oauth_client_secret` varchar(255) DEFAULT NULL,
            `oauth_tenant_id` varchar(255) DEFAULT NULL,
            `oauth_redirect_uri` varchar(500) DEFAULT NULL,
            `oauth_auto_redirect` varchar(20) NOT NULL DEFAULT 'never',
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query) or die($DB->error());
    } else {
        if (!$DB->fieldExists('glpi_plugin_entrahierarchy_configs', 'sync_filter_office_location')) {$DB->doQuery("ALTER TABLE `glpi_plugin_entrahierarchy_configs` ADD COLUMN `sync_filter_office_location` varchar(255) DEFAULT NULL") or die($DB->error());
        }
    }

    // 2. Sync logs table
    if (!$DB->tableExists('glpi_plugin_entrahierarchy_synclogs')) {$query = "CREATE TABLE `glpi_plugin_entrahierarchy_synclogs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `date` datetime NOT NULL,
            `status` varchar(50) NOT NULL,
            `message` text,
            `users_synced` int NOT NULL DEFAULT '0',
            `users_created` int NOT NULL DEFAULT '0',
            `users_updated` int NOT NULL DEFAULT '0',
            `users_failed` int NOT NULL DEFAULT '0',
            `users_deactivated` int NOT NULL DEFAULT '0',
            `users_deleted` int NOT NULL DEFAULT '0',
            `duration` int DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query) or die($DB->error());
    }

    // 3. User mapping table
    if (!$DB->tableExists('glpi_plugin_entrahierarchy_usermaps')) {$query = "CREATE TABLE `glpi_plugin_entrahierarchy_usermaps` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `users_id` int unsigned NOT NULL,
            `entra_id` varchar(255) NOT NULL,
            `entra_upn` varchar(255) DEFAULT NULL,
            `entra_email` varchar(255) DEFAULT NULL,
            `entra_display_name` varchar(255) DEFAULT NULL,
            `entra_job_title` varchar(255) DEFAULT NULL,
            `entra_department` varchar(255) DEFAULT NULL,
            `entra_company_name` varchar(255) DEFAULT NULL,
            `entra_office_location` varchar(255) DEFAULT NULL,
            `entra_mobile_phone` varchar(100) DEFAULT NULL,
            `entra_business_phones` text DEFAULT NULL,
            `entra_employee_id` varchar(100) DEFAULT NULL,
            `entra_employee_type` varchar(100) DEFAULT NULL,
            `entra_user_type` varchar(50) DEFAULT NULL,
            `entra_account_enabled` tinyint DEFAULT NULL,
            `manual_supervisor` tinyint NOT NULL DEFAULT '0',
            `last_sync` datetime DEFAULT NULL,
            `date_creation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entra_id` (`entra_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $DB->doQuery($query) or die($DB->error());
    }

    if (!$DB->request(['FROM' => 'glpi_plugin_entrahierarchy_configs'])->count()) {$DB->insert('glpi_plugin_entrahierarchy_configs', [
            'sync_enabled' => 0,
            'sync_interval' => 1800,
            'date_creation' => date('Y-m-d H:i:s')
        ]);
    }

    CronTask::Register(
        EntraSync::class,
        'SyncEntraHierarchy',
        30 * MINUTE_TIMESTAMP,
        [
            'comment' => __('Synchronize organizational hierarchy from Microsoft Entra ID', 'glpientrahierarchy'),
            'state'   => CronTask::STATE_WAITING,
            'mode'    => CronTask::MODE_INTERNAL
        ]
    );

    return true;
}

/**
 * Uninstall the plugin
 */
function plugin_glpientrahierarchy_uninstall()
{
    global $DB;
    $tables = [
        'glpi_plugin_entrahierarchy_configs',
        'glpi_plugin_entrahierarchy_synclogs',
        'glpi_plugin_entrahierarchy_usermaps'
    ];

    foreach ($tables as$table) {
        if ($DB->tableExists($table)) {$DB->doQuery("DROP TABLE `$table`") or die($DB->error());
        }
    }

    CronTask::unregister('glpientrahierarchy');
    return true;
}

function plugin_glpientrahierarchy_check_prerequisites() { return true; }
function plugin_glpientrahierarchy_check_config() { return true; }