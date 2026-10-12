<?php

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/var/www/glpi');
}
include_once (GLPI_ROOT . "/inc/includes.php");

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

// Inkluder nødvendige klasser eksplisitt
$baseDir = dirname(__DIR__);
require_once($baseDir . '/src/EntraConfig.php');
require_once($baseDir . '/src/GraphApiClient.php');

use GlpiPlugin\EntraHierarchy\GraphApiClient;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    try {
        $client = new GraphApiClient(
            $_POST['client_id'] ?? '',
            $_POST['client_secret'] ?? '',
            $_POST['tenant_id'] ?? ''
        );

        if ($client->testConnection()) {
            echo json_encode([
                'success' => true,
                'message' => __('Connection successful!', 'glpientrahierarchy')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => __('Connection failed. Please check your credentials.', 'glpientrahierarchy')
            ]);
        }
    } catch (Exception $e) {
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