<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->unsignedSmallInteger('api_ssl_port')->default(8729);
            $table->boolean('use_ssl')->default(false);
            $table->string('username');
            $table->text('password');
            $table->string('location')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('routeros_version')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['host', 'api_port']);
        });

        Schema::create('router_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->decimal('memory_percent', 5, 2)->nullable();
            $table->decimal('storage_percent', 5, 2)->nullable();
            $table->decimal('temperature_celsius', 6, 2)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->unsignedBigInteger('download_bps')->default(0);
            $table->unsignedBigInteger('upload_bps')->default(0);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            $table->index(['router_id', 'recorded_at']);
        });

        Schema::create('router_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('running')->default(false);
            $table->boolean('disabled')->default(false);
            $table->unsignedBigInteger('rx_bps')->default(0);
            $table->unsignedBigInteger('tx_bps')->default(0);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
            $table->unique(['router_id', 'external_id']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('download_kbps');
            $table->unsignedInteger('upload_kbps');
            $table->string('burst_limit')->nullable();
            $table->string('burst_threshold')->nullable();
            $table->string('burst_time')->nullable();
            $table->unsignedTinyInteger('priority')->default(8);
            $table->decimal('price', 14, 2)->default(0);
            $table->string('mikrotik_profile')->nullable();
            $table->string('radius_group')->nullable();
            $table->unsignedInteger('validity_days')->default(30);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('vendor');
            $table->string('model')->nullable();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('protocol')->default('mock');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('snmp_version')->nullable();
            $table->text('snmp_credential')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['host', 'port']);
        });

        Schema::create('olt_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->string('board_type')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamps();
            $table->unique(['olt_id', 'slot']);
        });

        Schema::create('olt_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('olt_board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('pon_index')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamps();
            $table->unique(['olt_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('customer_number')->unique();
            $table->string('name')->index();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('installation_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('odp_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pon')->nullable();
            $table->string('ont_identifier')->nullable();
            $table->string('pppoe_username')->nullable()->unique();
            $table->text('pppoe_password')->nullable();
            $table->string('hotspot_username')->nullable();
            $table->ipAddress('static_ip')->nullable();
            $table->string('status')->default('prospek')->index();
            $table->date('installed_at')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type');
            $table->string('status')->default('active');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('pppoe_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->unique();
            $table->text('password');
            $table->string('profile')->nullable();
            $table->ipAddress('local_address')->nullable();
            $table->ipAddress('remote_address')->nullable();
            $table->string('auth_source')->default('local');
            $table->boolean('disabled')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pppoe_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->index();
            $table->string('caller_id')->nullable();
            $table->ipAddress('address')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->string('disconnect_reason')->nullable();
            $table->unsignedBigInteger('input_octets')->default(0);
            $table->unsignedBigInteger('output_octets')->default(0);
            $table->timestamps();
        });

        Schema::create('hotspot_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('interface')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['router_id', 'name']);
        });

        Schema::create('hotspot_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('rate_limit')->nullable();
            $table->unsignedInteger('validity_minutes')->nullable();
            $table->unsignedInteger('limit_uptime_minutes')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('hotspot_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotspot_server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotspot_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->unique();
            $table->text('password');
            $table->string('mac_address')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('disabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hotspot_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotspot_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->index();
            $table->ipAddress('address')->nullable();
            $table->string('mac_address')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('radius_nas', function (Blueprint $table) {
            $table->id();
            $table->string('nasname')->unique();
            $table->string('shortname')->nullable();
            $table->string('type')->default('other');
            $table->unsignedSmallInteger('ports')->nullable();
            $table->text('secret');
            $table->string('server')->nullable();
            $table->string('community')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('radius_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pppoe_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('status');
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('genieacs_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('serial_number')->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('product_class')->nullable();
            $table->string('software_version')->nullable();
            $table->ipAddress('wan_ip')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->timestamp('last_inform_at')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();
        });

        Schema::create('genieacs_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('genieacs_device_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('task_type');
            $table->string('status')->default('queued');
            $table->json('payload')->nullable();
            $table->text('fault')->nullable();
            $table->timestamps();
        });

        Schema::create('genieacs_parameter_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('vendor');
            $table->string('product_class')->nullable();
            $table->string('software_version')->nullable();
            $table->string('logical_name');
            $table->string('parameter_path');
            $table->timestamps();
            $table->unique(['vendor', 'product_class', 'software_version', 'logical_name'], 'genieacs_mapping_unique');
        });

        Schema::create('ont_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('olt_port_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('onu_id')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->decimal('optical_rx', 7, 2)->nullable();
            $table->decimal('optical_tx', 7, 2)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->string('description')->nullable();
            $table->string('vlan')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ont_optical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ont_device_id')->constrained()->cascadeOnDelete();
            $table->decimal('rx', 7, 2)->nullable();
            $table->decimal('tx', 7, 2)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });

        Schema::create('vpn_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('interface_name')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('address_pool')->nullable();
            $table->text('private_key')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('vpn_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('assigned_ip')->nullable();
            $table->text('public_key')->nullable();
            $table->text('private_key')->nullable();
            $table->text('preshared_key')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_handshake_at')->nullable();
            $table->unsignedBigInteger('transfer_rx')->default(0);
            $table->unsignedBigInteger('transfer_tx')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->json('conditions');
            $table->unsignedInteger('cooldown_seconds')->default(900);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity')->default('warning')->index();
            $table->string('status')->default('open')->index();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('fingerprint')->nullable()->index();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_settings', function (Blueprint $table) {
            $table->id();
            $table->text('bot_token')->nullable();
            $table->string('chat_id')->nullable();
            $table->json('alert_types')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('module')->index();
            $table->string('action')->index();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('status')->default('success');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_type');
            $table->unsignedBigInteger('device_id');
            $table->string('command');
            $table->json('request')->nullable();
            $table->json('result')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('dry_run')->default(true);
            $table->timestamps();
        });

        Schema::create('jobs_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('job_type')->index();
            $table->string('status')->index();
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'backups', 'system_settings', 'jobs_history', 'device_commands', 'audit_logs',
            'telegram_settings', 'alerts', 'alert_rules', 'vpn_clients', 'vpn_servers',
            'ont_optical_histories', 'ont_devices', 'genieacs_parameter_mappings',
            'genieacs_tasks', 'genieacs_devices', 'radius_sync_logs', 'radius_nas',
            'hotspot_sessions', 'hotspot_users', 'hotspot_profiles', 'hotspot_servers',
            'pppoe_sessions', 'pppoe_accounts', 'customer_services', 'customers',
            'olt_ports', 'olt_boards', 'olts', 'packages', 'router_interfaces',
            'router_metrics', 'routers', 'odps', 'pops', 'areas',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
