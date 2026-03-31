<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

if (!function_exists('request_parse_body')) {
    function request_parse_body(?array $allowed_methods = null): array {
        
        
        $data = [];
        
        
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($content_type, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if ($input) {
                $data = json_decode($input, true) ?? [];
            }
        } elseif (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
            
            $data = $_POST;
        } elseif (strpos($content_type, 'multipart/form-data') !== false) {
            
            $data = $_POST;
        }
        
        return $data;
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
