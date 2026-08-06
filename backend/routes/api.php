<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GenieAcsController;
use App\Http\Controllers\Api\V1\HotspotController;
use App\Http\Controllers\Api\V1\OltController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\PppoeController;
use App\Http\Controllers\Api\V1\RadiusDiagnosticController;
use App\Http\Controllers\Api\V1\RouterController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Http\Controllers\Api\V1\TelegramController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Controllers\Api\V1\VpnController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);
        Route::get('auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('auth/sessions/{token}', [AuthController::class, 'revokeSession']);

        Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');
        Route::get('system/health', SystemHealthController::class)->middleware('permission:system.health.view');

        Route::get('routers', [RouterController::class, 'index'])->middleware('permission:routers.view');
        Route::post('routers', [RouterController::class, 'store'])->middleware('permission:routers.create');
        Route::get('routers/{router}', [RouterController::class, 'show'])->middleware('permission:routers.view');
        Route::put('routers/{router}', [RouterController::class, 'update'])->middleware('permission:routers.update');
        Route::delete('routers/{router}', [RouterController::class, 'destroy'])->middleware('permission:routers.delete');
        Route::post('routers/{router}/test-connection', [RouterController::class, 'testConnection'])->middleware(['permission:routers.test', 'throttle:router-connection-test']);
        Route::post('routers/{router}/sync', [RouterController::class, 'sync'])->middleware('permission:routers.sync');
        Route::get('routers/{router}/interfaces', [RouterController::class, 'interfaces'])->middleware('permission:routers.view');
        Route::get('routers/{router}/pppoe-active', [RouterController::class, 'pppoeActive'])->middleware('permission:pppoe.view');
        Route::get('routers/{router}/ppp-secrets', [RouterController::class, 'pppSecrets'])->middleware('permission:pppoe.view');
        Route::get('routers/{router}/ppp-profiles', [RouterController::class, 'pppProfiles'])->middleware('permission:pppoe.view');
        Route::get('routers/{router}/hotspot-active', [RouterController::class, 'hotspotActive'])->middleware('permission:hotspot.view');
        Route::get('routers/{router}/hotspot-users', [RouterController::class, 'hotspotUsers'])->middleware('permission:hotspot.view');
        Route::get('routers/{router}/dhcp-leases', [RouterController::class, 'dhcpLeases'])->middleware('permission:routers.view');
        Route::get('routers/{router}/queues', [RouterController::class, 'queues'])->middleware('permission:routers.view');
        Route::get('routers/{router}/sync-history', [RouterController::class, 'syncHistory'])->middleware('permission:routers.view');
        Route::get('routers/{router}/audit-logs', [RouterController::class, 'auditLogs'])->middleware('permission:audit.view');
        Route::get('pppoe/accounts', [PppoeController::class, 'accounts'])->middleware('permission:pppoe.view');
        Route::get('pppoe/sessions', [PppoeController::class, 'sessions'])->middleware('permission:pppoe.view');
        Route::get('pppoe/reconciliation', [PppoeController::class, 'reconciliation'])->middleware('permission:pppoe.view');
        Route::get('hotspot/users', [HotspotController::class, 'users'])->middleware('permission:hotspot.view');
        Route::get('hotspot/sessions', [HotspotController::class, 'sessions'])->middleware('permission:hotspot.view');

        Route::apiResource('customers', CustomerController::class)->middlewareFor(['index', 'show'], 'permission:customers.view')->middlewareFor('store', 'permission:customers.create')->middlewareFor('update', 'permission:customers.update')->middlewareFor('destroy', 'permission:customers.delete');
        Route::apiResource('packages', PackageController::class)->middlewareFor(['index', 'show'], 'permission:packages.view')->middlewareFor('store', 'permission:packages.create')->middlewareFor('update', 'permission:packages.update')->middlewareFor('destroy', 'permission:packages.delete');

        Route::get('olts', [OltController::class, 'index'])->middleware('permission:olts.view');
        Route::post('olts/{olt}/connection-tests', [OltController::class, 'testConnection'])->middleware('permission:olts.manage');
        Route::get('olts/{olt}/onts', [OltController::class, 'onts'])->middleware('permission:olts.view');
        Route::get('genieacs/devices', [GenieAcsController::class, 'index'])->middleware('permission:genieacs.view');
        Route::get('genieacs/devices/{device}', [GenieAcsController::class, 'show'])->middleware('permission:genieacs.view');
        Route::get('radius/health', [RadiusDiagnosticController::class, 'health'])->middleware('permission:radius.view');
        Route::get('radius/accounting', [RadiusDiagnosticController::class, 'accounting'])->middleware('permission:radius.view');
        Route::post('radius/authentication-tests', [RadiusDiagnosticController::class, 'testAuthentication'])->middleware('permission:radius.manage');
        Route::post('vpn/servers/{vpnServer}/clients/preview', [VpnController::class, 'previewClient'])->middleware('permission:vpn.manage');
        Route::post('vpn/servers/{vpnServer}/clients', [VpnController::class, 'provisionClient'])->middleware('permission:vpn.manage');
        Route::get('vpn/servers', [VpnController::class, 'servers'])->middleware('permission:vpn.view');
        Route::get('vpn/clients', [VpnController::class, 'clients'])->middleware('permission:vpn.view');
        Route::get('telegram-settings', [TelegramController::class, 'index'])->middleware('permission:settings.manage');
        Route::post('telegram-settings/{telegramSetting}/tests', [TelegramController::class, 'test'])->middleware('permission:settings.manage');
        Route::get('alerts', [AlertController::class, 'index'])->middleware('permission:alerts.view');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

        Route::get('users', [UserManagementController::class, 'index'])->middleware('permission:users.manage');
        Route::post('users', [UserManagementController::class, 'store'])->middleware('permission:users.manage');
        Route::get('roles', [UserManagementController::class, 'roles'])->middleware('permission:roles.manage');
    });
});
