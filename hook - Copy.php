<?php
/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2025 by Lukáš Kraic (lukas.kraic@gmail.com)
 -------------------------------------------------------------------------
 */

use GlpiPlugin\EntraHierarchy\EntraConfig;

function plugin_glpientrahierarchy_display_login()
{
    require_once(Plugin::getPhpDir('glpientrahierarchy') . '/src/EntraConfig.php');
    $config = \GlpiPlugin\EntraHierarchy\EntraConfig::getConfig();

    if (empty($config['oauth_enabled']) || $config['oauth_enabled'] != 1) {
        return;
    }

    $oauthLoginUrl = Plugin::getWebDir('glpientrahierarchy') . '/front/oauth_login.php';
    
    echo '<div id="microsoft-sso-wrapper" style="text-align: center; margin-bottom: 15px;">';
    echo '<a href="' . $oauthLoginUrl . '" class="btn btn-secondary" style="background: #2b2b2b; color: #fff; width: 100%; padding: 10px;">Sign in with Microsoft</a>';
    echo '</div>';
}