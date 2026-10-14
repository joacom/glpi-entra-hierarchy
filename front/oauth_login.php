<?php

// Tving SameSite til Lax slik at cookien overlever omdirigeringen fra Microsoft
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');

/*
 -------------------------------------------------------------------------
 Entra Hierarchy plugin for GLPI
 Copyright (C) 2025 by Lukáš Kraic (lukas.kraic@gmail.com)
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Entra Hierarchy.

 Entra Hierarchy is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 Entra Hierarchy is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Entra Hierarchy. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraAuth;

// Tell GLPI this is a public endpoint (no authentication required)
define('GLPI_PUBLIC', true);

// Load GLPI bootstrap
include ('../../../inc/includes.php');

// Access global GLPI configuration
global $CFG_GLPI;

// Explicitly load plugin classes
require_once(Plugin::getPhpDir('glpientrahierarchy') . '/src/EntraConfig.php');
require_once(Plugin::getPhpDir('glpientrahierarchy') . '/src/EntraAuth.php');

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("oauth_login.php - Starting OAuth 2.0 login flow");

// Load OAuth configuration
$config = EntraConfig::getConfig();

if (!$config || !isset($config['oauth_enabled']) || !$config['oauth_enabled']) {
    error_log("oauth_login.php - OAuth not configured or disabled");
    Session::addMessageAfterRedirect(__('OAuth authentication is not available.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

// Get authorization URL (this also generates and stores state and code_verifier in $_SESSION)
$authUrl = EntraAuth::getAuthorizationUrl($config);

if (!$authUrl) {
    error_log("oauth_login.php - Failed to generate authorization URL");
    Session::addMessageAfterRedirect(__('Failed to initialize authentication. Please try again.', 'glpientrahierarchy'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/');
}

error_log("oauth_login.php - Redirecting to authorization URL");
error_log("oauth_login.php - Redirect URI: " . ($config['oauth_redirect_uri'] ?? 'NOT SET'));

// FIX: Force write and close session data to disk/cookie BEFORE redirecting,
// ensuring the session cookie is fully persisted in the browser for the callback.
session_write_close();

// Redirect user to Microsoft Entra ID login page
header("Location: {$authUrl}");
exit;