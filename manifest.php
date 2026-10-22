<?php
header('Content-Type: application/manifest+json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

// Dynamic base path detection
$basePath = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? '/farmogram' : '';

echo json_encode([
    "name" => "Farmogram - Digital Agriculture",
    "short_name" => "Farmogram",
    "description" => "Connecting farmers and buyers through a modern, intelligent digital marketplace. Direct market, fair pricing, zero middlemen.",
    "start_url" => "$basePath/auth/login.php",
    "scope" => "$basePath/",
    "id" => "$basePath/",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => "#10b981",
    "orientation" => "portrait-primary",
    "categories" => ["business", "food", "shopping", "lifestyle"],
    "prefer_related_applications" => false,
    "shortcuts" => [
        [
            "name" => "Login to Dashboard",
            "short_name" => "Login",
            "description" => "Access your Farmogram dashboard",
            "url" => "$basePath/auth/login.php",
            "icons" => [["src" => "$basePath/assets/images/logo-light.png", "sizes" => "192x192"]]
        ],
        [
            "name" => "Create Account",
            "short_name" => "Register",
            "description" => "Join Farmogram marketplace",
            "url" => "$basePath/auth/register.php",
            "icons" => [["src" => "$basePath/assets/images/logo-light.png", "sizes" => "192x192"]]
        ],
        [
            "name" => "About Farmogram",
            "short_name" => "About",
            "description" => "Learn about our platform",
            "url" => "$basePath/about.php",
            "icons" => [["src" => "$basePath/assets/images/logo-light.png", "sizes" => "192x192"]]
        ]
    ],
    "icons" => [
        ["src" => "$basePath/assets/images/logo-light.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
        ["src" => "$basePath/assets/images/logo-light.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
        ["src" => "$basePath/assets/images/logo-light.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "maskable"],
        ["src" => "$basePath/assets/images/logo-light.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
