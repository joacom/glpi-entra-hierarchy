<?php

// Inkluder GLPI-kjerne
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/var/www/glpi');
}
include_once (GLPI_ROOT . "/inc/includes.php");

header('Content-Type: application/json');

try {
    // CSRF er validert av rammeverket i forkant.
    Session::checkRight('config', UPDATE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: ' . $e->getMessage()
    ]);
    exit;
}

// Inkluder klasse dynamisk uavhengig av om modulen står i plugins/ eller marketplace/
$pluginDir = Plugin::getPhpDir('glpientrahierarchy');
if (file_exists($pluginDir . '/src/EntraConfig.php')) {
    include_once ($pluginDir . '/src/EntraConfig.php');
}

use GlpiPlugin\EntraHierarchy\EntraConfig;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: logg POST-data
        error_log('AJAX save_config POST data: ' . print_r($_POST, true));

        $result = EntraConfig::saveConfig($_POST);

        error_log('AJAX save_config result: ' . ($result ? 'SUCCESS' : 'FAILED'));

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => __('Configuration saved successfully', 'glpientrahierarchy')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => __('Failed to save configuration', 'glpientrahierarchy')
            ]);
        }
    } catch (Exception $e) {
        error_log('AJAX save_config exception: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method: ' . $_SERVER['REQUEST_METHOD']
    ]);
}