<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file now serves as a compatibility layer that includes
| the new versioned API routes. The structure separates:
| - Central app routes (system/tenant management)
| - Tenant app routes (multi-tenant operations)
|
*/

// Include versioned API routes
require __DIR__ . '/api/v1/central.php';  // Central app management
require __DIR__ . '/api/v1/tenant.php';   // Tenant-specific operations
