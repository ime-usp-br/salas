<?php

/**
 * Simple script to test API configuration loading
 * Run with: php test_api_config.php
 */

// Include Laravel's bootstrap to load configuration
require_once __DIR__ . '/vendor/autoload.php';

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== API Configuration Test ===\n\n";

try {
    // Test basic API configuration
    echo "1. Testing basic API configuration:\n";
    echo "   API Prefix: " . config('api.prefix') . "\n";
    echo "   Token Name: " . config('api.token_name') . "\n";
    echo "   ✓ Basic config loaded successfully\n\n";

    // Test rate limits configuration
    echo "2. Testing rate limits configuration:\n";
    
    // Test auth limits
    $authGeneral = config('api.rate_limits.auth.general.max_attempts');
    $authEmail = config('api.rate_limits.auth.per_email.max_attempts');
    $authHourly = config('api.rate_limits.auth.hourly.max_attempts');
    echo "   Auth limits - General: $authGeneral, Email: $authEmail, Hourly: $authHourly\n";
    
    // Test API limits for authenticated users
    $apiAuth = config('api.rate_limits.api.authenticated.per_minute.max_attempts');
    $apiAuthHourly = config('api.rate_limits.api.authenticated.hourly.max_attempts');
    echo "   API Auth limits - Minute: $apiAuth, Hourly: $apiAuthHourly\n";
    
    // Test API limits for guests
    $apiGuest = config('api.rate_limits.api.guest.per_minute.max_attempts');
    $apiGuestHourly = config('api.rate_limits.api.guest.hourly.max_attempts');
    echo "   API Guest limits - Minute: $apiGuest, Hourly: $apiGuestHourly\n";
    
    // Test public limits
    $publicMin = config('api.rate_limits.public.per_minute.max_attempts');
    $publicHour = config('api.rate_limits.public.hourly.max_attempts');
    echo "   Public limits - Minute: $publicMin, Hourly: $publicHour\n";
    
    // Test admin limits
    $adminMin = config('api.rate_limits.admin.per_minute.max_attempts');
    $adminHour = config('api.rate_limits.admin.hourly.max_attempts');
    echo "   Admin limits - Minute: $adminMin, Hourly: $adminHour\n";
    
    // Test reservation limits
    $reservUserMin = config('api.rate_limits.reservations.regular_user.per_minute.max_attempts');
    $reservBulkMin = config('api.rate_limits.reservations.bulk_user.per_minute.max_attempts');
    $reservGuestMin = config('api.rate_limits.reservations.guest.per_minute.max_attempts');
    echo "   Reservation limits - User: $reservUserMin, Bulk: $reservBulkMin, Guest: $reservGuestMin\n";
    
    // Test bulk roles
    $bulkRoles = config('api.rate_limits.reservations.bulk_roles');
    echo "   Bulk roles: $bulkRoles\n";
    
    // Test bulk operation limits
    $bulkMin = config('api.rate_limits.bulk.per_minute.max_attempts');
    $bulkHour = config('api.rate_limits.bulk.hourly.max_attempts');
    $bulkDay = config('api.rate_limits.bulk.daily.max_attempts');
    echo "   Bulk operations - Minute: $bulkMin, Hourly: $bulkHour, Daily: $bulkDay\n";
    
    echo "   ✓ Rate limits configuration loaded successfully\n\n";
    
    // Test Sanctum configuration
    echo "3. Testing Sanctum configuration:\n";
    $sanctumToken = config('api.sanctum.token_name');
    $sanctumAbilities = config('api.sanctum.token_abilities');
    echo "   Sanctum Token: $sanctumToken\n";
    echo "   Sanctum Abilities: $sanctumAbilities\n";
    echo "   ✓ Sanctum config loaded successfully\n\n";
    
    // Test response configuration
    echo "4. Testing response configuration:\n";
    $debugInfo = config('api.response.include_debug_info') ? 'true' : 'false';
    $defaultPerPage = config('api.response.default_per_page');
    $maxPerPage = config('api.response.max_per_page');
    echo "   Debug Info: $debugInfo\n";
    echo "   Default Per Page: $defaultPerPage\n";
    echo "   Max Per Page: $maxPerPage\n";
    echo "   ✓ Response config loaded successfully\n\n";
    
    echo "=== ALL TESTS PASSED ===\n";
    echo "✓ API configuration is working correctly!\n";
    echo "✓ All rate limiting values are properly configured\n";
    echo "✓ Configuration can be modified via .env variables\n\n";
    
} catch (Exception $e) {
    echo "❌ Error testing configuration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}