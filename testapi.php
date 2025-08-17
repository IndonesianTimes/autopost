<?php
require 'vendor/autoload.php';
require 'src/Helpers/Config.php';

use App\Helpers\Config;

$apiToken = Config::get('API_TOKEN', 'N/A');

header('Content-Type: application/json');
echo json_encode([
    'api_token' => $apiToken,
    'status' => $apiToken === 'magauto' ? 'OK' : 'MISMATCH'
], JSON_PRETTY_PRINT);
