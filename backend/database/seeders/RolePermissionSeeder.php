<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view', 'routers.view', 'routers.create', 'routers.update', 'routers.delete', 'routers.test', 'routers.sync',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'packages.view', 'packages.create', 'packages.update', 'packages.delete',
            'pppoe.view', 'pppoe.manage', 'hotspot.view', 'hotspot.manage', 'radius.view', 'radius.manage',
            'genieacs.view', 'genieacs.manage', 'genieacs.factory-reset', 'olts.view', 'olts.manage',
            'vpn.view', 'vpn.manage', 'alerts.view', 'audit.view', 'finance.view',
            'users.manage', 'roles.manage', 'settings.manage', 'system.health.view', 'backups.manage',
        ];
        collect($permissions)->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));

        $all = Permission::all();
        Role::findOrCreate('Super Admin')->syncPermissions($all);
        Role::findOrCreate('Administrator')->syncPermissions($all->reject(fn ($permission) => in_array($permission->name, ['users.manage', 'roles.manage'], true)));
        Role::findOrCreate('NOC')->syncPermissions(Permission::whereIn('name', ['dashboard.view', 'routers.view', 'routers.test', 'routers.sync', 'pppoe.view', 'hotspot.view', 'radius.view', 'genieacs.view', 'olts.view', 'vpn.view', 'alerts.view', 'system.health.view'])->get());
        Role::findOrCreate('Teknisi')->syncPermissions(Permission::whereIn('name', ['dashboard.view', 'customers.view', 'genieacs.view', 'olts.view', 'alerts.view'])->get());
        Role::findOrCreate('Finance')->syncPermissions(Permission::whereIn('name', ['dashboard.view', 'customers.view', 'packages.view', 'finance.view'])->get());
        Role::findOrCreate('Reseller')->syncPermissions(Permission::whereIn('name', ['dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'packages.view'])->get());
    }
}
