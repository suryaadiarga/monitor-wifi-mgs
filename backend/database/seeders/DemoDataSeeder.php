<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\GenieAcsDevice;
use App\Models\InternetPackage;
use App\Models\Olt;
use App\Models\OntDevice;
use App\Models\PppoeAccount;
use App\Models\Router;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Models\VpnClient;
use App\Models\VpnServer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('DEMO_ADMIN_PASSWORD');
        if (! is_string($adminPassword) || strlen($adminPassword) < 12) {
            throw new \RuntimeException('DEMO_ADMIN_PASSWORD minimal 12 karakter wajib diisi saat DEMO_DATA_ENABLED=true.');
        }

        $admin = User::updateOrCreate(['email' => env('DEMO_ADMIN_EMAIL', 'admin@isp.test')], ['name' => 'Super Admin Demo', 'password' => $adminPassword]);
        $admin->syncRoles(['Super Admin']);

        DB::table('areas')->updateOrInsert(['code' => 'DEMO'], ['name' => 'Area Demo', 'updated_at' => now(), 'created_at' => now()]);
        $areaId = DB::table('areas')->where('code', 'DEMO')->value('id');
        DB::table('pops')->updateOrInsert(['code' => 'POP-DEMO'], ['area_id' => $areaId, 'name' => 'POP Utama Demo', 'updated_at' => now(), 'created_at' => now()]);
        $popId = DB::table('pops')->where('code', 'POP-DEMO')->value('id');
        $routers = collect([
            Router::updateOrCreate(['host' => '192.0.2.1', 'api_port' => 8728], ['area_id' => $areaId, 'pop_id' => $popId, 'name' => 'Core Router Demo', 'username' => 'demo-readonly', 'password' => Str::password(32), 'status' => 'online', 'last_seen_at' => now()]),
            Router::updateOrCreate(['host' => '192.0.2.2', 'api_port' => 8728], ['area_id' => $areaId, 'pop_id' => $popId, 'name' => 'Edge Router Demo', 'username' => 'demo-readonly', 'password' => Str::password(32), 'status' => 'offline']),
        ]);
        $routers->each(fn (Router $router) => $router->metrics()->updateOrCreate(['recorded_at' => now()->startOfMinute()], ['cpu_percent' => fake()->numberBetween(12, 60), 'memory_percent' => fake()->numberBetween(25, 70), 'storage_percent' => fake()->numberBetween(20, 55), 'download_bps' => fake()->numberBetween(10_000_000, 90_000_000), 'upload_bps' => fake()->numberBetween(2_000_000, 25_000_000)]));

        $packages = collect([
            ['name' => 'Home 10 Mbps', 'download_kbps' => 10000, 'upload_kbps' => 3000, 'price' => 150000],
            ['name' => 'Home 20 Mbps', 'download_kbps' => 20000, 'upload_kbps' => 5000, 'price' => 225000],
            ['name' => 'Home 50 Mbps', 'download_kbps' => 50000, 'upload_kbps' => 10000, 'price' => 375000],
            ['name' => 'Bisnis 100 Mbps', 'download_kbps' => 100000, 'upload_kbps' => 50000, 'price' => 950000],
            ['name' => 'Dedicated 50 Mbps', 'download_kbps' => 50000, 'upload_kbps' => 50000, 'price' => 2500000],
        ])->map(fn (array $data) => InternetPackage::updateOrCreate(['name' => $data['name']], $data + ['priority' => 8, 'validity_days' => 30, 'enabled' => true]));

        for ($i = 1; $i <= 20; $i++) {
            Customer::updateOrCreate(['customer_number' => sprintf('CUST-DEMO-%04d', $i)], [
                'uuid' => (string) Str::uuid(),
                'name' => fake('id_ID')->name(),
                'phone' => fake('id_ID')->phoneNumber(),
                'installation_address' => fake('id_ID')->address(),
                'area_id' => $areaId,
                'pop_id' => $popId,
                'router_id' => $routers[$i % 2]->id,
                'package_id' => $packages[$i % 5]->id,
                'pppoe_username' => "demo{$i}@area",
                'pppoe_password' => Str::password(20),
                'status' => $i <= 16 ? 'aktif' : ($i <= 18 ? 'isolir' : 'prospek'),
                'installed_at' => now()->subDays($i * 3),
                'due_day' => (($i - 1) % 28) + 1,
            ]);
        }

        $olt = Olt::updateOrCreate(['host' => '198.51.100.10', 'port' => 22], ['area_id' => $areaId, 'pop_id' => $popId, 'name' => 'OLT Huawei Mock', 'vendor' => 'huawei', 'protocol' => 'mock', 'status' => 'online', 'last_seen_at' => now()]);
        Olt::updateOrCreate(['host' => '198.51.100.11', 'port' => 22], ['area_id' => $areaId, 'pop_id' => $popId, 'name' => 'OLT ZTE Mock', 'vendor' => 'zte', 'protocol' => 'mock', 'status' => 'offline']);
        foreach (['online', 'offline', 'los'] as $index => $status) {
            OntDevice::updateOrCreate(['serial_number' => 'MOCKONT0000'.($index + 1)], ['olt_id' => $olt->id, 'onu_id' => '0/1/1:'.($index + 1), 'status' => $status, 'optical_rx' => [-18.5, -28.4, -34.0][$index], 'last_seen_at' => now()->subMinutes($index * 10)]);
        }
        Alert::updateOrCreate(['fingerprint' => 'demo-router-offline', 'status' => 'open'], ['severity' => 'critical', 'source_type' => Router::class, 'source_id' => $routers[1]->id, 'title' => 'Router edge offline', 'message' => 'Data demo: router edge tidak merespons polling.', 'started_at' => now()->subMinutes(12)]);

        $activeCustomer = Customer::where('customer_number', 'CUST-DEMO-0001')->firstOrFail();
        $offlineCustomer = Customer::where('customer_number', 'CUST-DEMO-0017')->firstOrFail();
        $activePppoe = PppoeAccount::updateOrCreate(['username' => $activeCustomer->pppoe_username], ['customer_id' => $activeCustomer->id, 'router_id' => $routers[0]->id, 'password' => Str::password(20), 'profile' => 'demo-home', 'auth_source' => 'radius', 'disabled' => false]);
        PppoeAccount::updateOrCreate(['username' => $offlineCustomer->pppoe_username], ['customer_id' => $offlineCustomer->id, 'router_id' => $routers[1]->id, 'password' => Str::password(20), 'profile' => 'demo-home', 'auth_source' => 'radius', 'disabled' => true]);
        DB::table('pppoe_sessions')->updateOrInsert(['pppoe_account_id' => $activePppoe->id, 'ended_at' => null], ['router_id' => $routers[0]->id, 'username' => $activePppoe->username, 'caller_id' => 'AA:BB:CC:00:DE:01', 'address' => '10.10.0.21', 'started_at' => now()->subHours(4), 'input_octets' => 120000000, 'output_octets' => 840000000, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('hotspot_servers')->updateOrInsert(['router_id' => $routers[0]->id, 'name' => 'hotspot-demo'], ['interface' => 'bridge-hotspot', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $hotspotServerId = DB::table('hotspot_servers')->where('router_id', $routers[0]->id)->where('name', 'hotspot-demo')->value('id');
        DB::table('hotspot_profiles')->updateOrInsert(['name' => 'voucher-2jam-demo'], ['rate_limit' => '5M/2M', 'validity_minutes' => 120, 'limit_uptime_minutes' => 120, 'price' => 5000, 'created_at' => now(), 'updated_at' => now()]);
        $hotspotProfileId = DB::table('hotspot_profiles')->where('name', 'voucher-2jam-demo')->value('id');
        DB::table('hotspot_users')->updateOrInsert(['username' => 'VOUCHER-DEMO-001'], ['hotspot_server_id' => $hotspotServerId, 'hotspot_profile_id' => $hotspotProfileId, 'password' => encrypt(Str::password(16)), 'expires_at' => now()->addDay(), 'disabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        $hotspotUserId = DB::table('hotspot_users')->where('username', 'VOUCHER-DEMO-001')->value('id');
        DB::table('hotspot_sessions')->updateOrInsert(['hotspot_user_id' => $hotspotUserId, 'ended_at' => null], ['username' => 'VOUCHER-DEMO-001', 'address' => '10.20.0.15', 'mac_address' => 'AA:BB:CC:00:DE:02', 'started_at' => now()->subMinutes(35), 'created_at' => now(), 'updated_at' => now()]);

        GenieAcsDevice::updateOrCreate(['device_id' => 'mock-genieacs-001'], ['serial_number' => 'MOCKONT00001', 'manufacturer' => 'Mock Vendor', 'product_class' => 'ONT-Demo', 'software_version' => '1.0-demo', 'wan_ip' => '192.0.2.101', 'status' => 'online', 'last_inform_at' => now(), 'parameters' => ['ssid' => 'Demo-WiFi', 'password' => Str::password(20)]]);
        GenieAcsDevice::updateOrCreate(['device_id' => 'mock-genieacs-002'], ['serial_number' => 'MOCKONT00002', 'manufacturer' => 'Mock Vendor', 'product_class' => 'ONT-Demo', 'software_version' => '1.0-demo', 'status' => 'offline', 'last_inform_at' => now()->subHours(2), 'parameters' => []]);

        $vpnServer = VpnServer::updateOrCreate(['name' => 'WireGuard Demo'], ['type' => 'wireguard', 'interface_name' => 'wg-demo', 'endpoint' => 'vpn.example.test:51820', 'address_pool' => '10.88.0.0/24', 'private_key' => Str::password(44), 'enabled' => true]);
        VpnClient::updateOrCreate(['vpn_server_id' => $vpnServer->id, 'name' => 'Teknisi Demo'], ['user_id' => $admin->id, 'assigned_ip' => '10.88.0.2', 'public_key' => Str::password(44), 'private_key' => Str::password(44), 'preshared_key' => Str::password(44), 'last_handshake_at' => now()->subMinutes(5), 'transfer_rx' => 1000000, 'transfer_tx' => 500000, 'enabled' => true]);
        TelegramSetting::updateOrCreate(['chat_id' => 'demo-chat-id'], ['bot_token' => Str::password(48), 'alert_types' => ['router_offline', 'backup_failed'], 'enabled' => false]);
        AuditLog::updateOrCreate(['correlation_id' => '00000000-0000-4000-8000-000000000001'], ['user_id' => $admin->id, 'module' => 'demo', 'action' => 'seed', 'status' => 'success', 'after' => ['message' => 'Data demo berhasil disiapkan']]);
    }
}
