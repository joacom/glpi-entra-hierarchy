<?php

define('GLPI_ROOT', '/var/www/glpi');
require_once(GLPI_ROOT . '/inc/includes.php');

header('Content-Type: application/json');

try {
    Session::checkRight('config', UPDATE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: ' . $e->getMessage()
    ]);
    exit;
}

// Manually include plugin classes
$pluginRoot = dirname(__DIR__);

require_once($pluginRoot . '/src/EntraConfig.php');
require_once($pluginRoot . '/src/GraphApiClient.php');
require_once($pluginRoot . '/src/EntraSync.php');

error_log('DEBUG FILE: ' . __FILE__);

error_log(
    'DEBUG CLASS EXISTS: ' .
    (class_exists('\GlpiPlugin\EntraHierarchy\EntraSync')
        ? 'YES'
        : 'NO')
);

use GlpiPlugin\EntraHierarchy\EntraConfig;
use GlpiPlugin\EntraHierarchy\EntraSync;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_sync'])) {
    try {
        error_log('manual_sync.php - Starting manual sync');

        $config = EntraConfig::getConfig();

        if (!$config) {
            echo json_encode([
                'success' => false,
                'message' => __('Plugin is not configured. Please configure credentials first.', 'glpientrahierarchy')
            ]);
            exit;
        }

        $result = EntraSync::manualSync();

        error_log(
            'manual_sync.php - Sync completed: ' .
            ($result['success'] ? 'SUCCESS' : 'FAILED')
        );

        echo json_encode($result);

    } catch (Exception $e) {

        error_log('manual_sync.php - Exception: ' . $e->getMessage());

        error_log(
            'manual_sync.php - Stack trace: ' .
            $e->getTraceAsString()
        );

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}