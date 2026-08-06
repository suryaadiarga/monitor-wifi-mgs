<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->boolean('verify_tls')->default(true)->after('use_ssl');
            $table->string('ca_certificate_path')->nullable()->after('verify_tls');
            $table->string('certificate_fingerprint')->nullable()->after('ca_certificate_path');
            $table->string('identity')->nullable()->after('location');
            $table->string('architecture_name')->nullable()->after('routeros_version');
            $table->string('board_name')->nullable()->after('architecture_name');
            $table->unsignedBigInteger('uptime_seconds')->nullable()->after('board_name');
            $table->unsignedInteger('connection_latency_ms')->nullable()->after('status');
            $table->timestamp('last_checked_at')->nullable()->index()->after('last_seen_at');
            $table->timestamp('last_connected_at')->nullable()->index()->after('last_checked_at');
            $table->timestamp('last_successful_sync_at')->nullable()->index()->after('last_connected_at');
            $table->text('last_error')->nullable()->after('last_successful_sync_at');
            $table->json('capabilities')->nullable()->after('last_error');
        });

        Schema::table('router_metrics', function (Blueprint $table) {
            $table->unsignedSmallInteger('cpu_count')->nullable()->after('cpu_percent');
            $table->unsignedBigInteger('total_memory_bytes')->nullable()->after('memory_percent');
            $table->unsignedBigInteger('free_memory_bytes')->nullable()->after('total_memory_bytes');
            $table->unsignedBigInteger('total_storage_bytes')->nullable()->after('storage_percent');
            $table->unsignedBigInteger('free_storage_bytes')->nullable()->after('total_storage_bytes');
            $table->decimal('voltage_volts', 8, 3)->nullable()->after('temperature_celsius');
        });

        Schema::table('router_interfaces', function (Blueprint $table) {
            $table->unsignedInteger('mtu')->nullable()->after('disabled');
            $table->unsignedInteger('actual_mtu')->nullable()->after('mtu');
            $table->string('mac_address')->nullable()->after('actual_mtu');
            $table->text('comment')->nullable()->after('mac_address');
            $table->boolean('dynamic')->default(false)->after('comment');
            $table->unsignedBigInteger('rx_bytes')->default(0)->after('dynamic');
            $table->unsignedBigInteger('tx_bytes')->default(0)->after('rx_bytes');
            $table->unsignedBigInteger('rx_packets')->default(0)->after('tx_bytes');
            $table->unsignedBigInteger('tx_packets')->default(0)->after('rx_packets');
            $table->unsignedInteger('link_downs')->default(0)->after('tx_packets');
            $table->timestamp('last_link_up_at')->nullable()->after('link_downs');
            $table->timestamp('last_link_down_at')->nullable()->after('last_link_up_at');
            $table->timestamp('last_seen_at')->nullable()->index()->after('last_polled_at');
            $table->timestamp('missing_since')->nullable()->index()->after('last_seen_at');
            $table->index(['router_id', 'name']);
            $table->index(['router_id', 'running']);
        });

        Schema::create('router_interface_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_interface_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rx_bytes')->default(0);
            $table->unsignedBigInteger('tx_bytes')->default(0);
            $table->unsignedBigInteger('rx_packets')->default(0);
            $table->unsignedBigInteger('tx_packets')->default(0);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            $table->index(['router_id', 'recorded_at']);
            $table->index(['router_interface_id', 'recorded_at']);
        });

        Schema::create('router_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->uuid('correlation_id')->unique();
            $table->string('scope')->index();
            $table->string('status')->default('running')->index();
            $table->json('capabilities')->nullable();
            $table->json('counts')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['router_id', 'scope', 'started_at']);
        });

        Schema::create('router_pppoe_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('username')->index();
            $table->string('service')->nullable();
            $table->string('caller_id')->nullable();
            $table->string('address')->nullable();
            $table->string('uptime')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->string('encoding')->nullable();
            $table->string('session_id')->nullable();
            $table->string('interface')->nullable()->index();
            $table->string('profile')->nullable()->index();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_pppoe_active_external_unique');
            $table->index(['router_id', 'username']);
        });

        Schema::create('router_ppp_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('username')->index();
            $table->string('service')->nullable();
            $table->string('profile')->nullable()->index();
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->boolean('disabled')->default(false)->index();
            $table->text('comment')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_ppp_secret_external_unique');
            $table->index(['router_id', 'username']);
        });

        Schema::create('router_ppp_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name')->index();
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->string('rate_limit')->nullable();
            $table->string('only_one')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_ppp_profile_external_unique');
            $table->index(['router_id', 'name'], 'router_ppp_profile_name_index');
        });

        Schema::create('router_hotspot_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('username')->index();
            $table->string('server')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('login_by')->nullable();
            $table->string('uptime')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_hotspot_active_external_unique');
            $table->index(['router_id', 'username']);
        });

        Schema::create('router_hotspot_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('username')->index();
            $table->string('server')->nullable();
            $table->string('profile')->nullable()->index();
            $table->string('mac_address')->nullable();
            $table->boolean('disabled')->default(false)->index();
            $table->string('limit_uptime')->nullable();
            $table->string('limit_bytes_total')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_hotspot_user_external_unique');
            $table->index(['router_id', 'username']);
        });

        Schema::create('router_hotspot_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name')->index();
            $table->string('rate_limit')->nullable();
            $table->string('shared_users')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_hotspot_profile_external_unique');
            $table->index(['router_id', 'name'], 'router_hotspot_profile_name_index');
        });

        Schema::create('router_dhcp_leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('address')->nullable()->index();
            $table->string('mac_address')->nullable()->index();
            $table->string('host_name')->nullable();
            $table->string('server')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->boolean('dynamic')->default(false);
            $table->boolean('disabled')->default(false);
            $table->string('expires_after')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'external_id'], 'router_dhcp_lease_external_unique');
        });

        Schema::create('router_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->index();
            $table->string('external_id');
            $table->string('name')->index();
            $table->string('target')->nullable();
            $table->string('parent')->nullable();
            $table->string('queue_type')->nullable();
            $table->string('max_limit')->nullable();
            $table->string('rate')->nullable();
            $table->boolean('disabled')->default(false)->index();
            $table->text('comment')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'kind', 'external_id'], 'router_queue_external_unique');
            $table->index(['router_id', 'name']);
        });

        Schema::create('router_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->index();
            $table->string('external_id');
            $table->string('name')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->boolean('disabled')->default(false);
            $table->json('properties')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('missing_since')->nullable()->index();
            $table->timestamps();
            $table->unique(['router_id', 'kind', 'external_id'], 'router_inventory_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_inventory_items');
        Schema::dropIfExists('router_queues');
        Schema::dropIfExists('router_dhcp_leases');
        Schema::dropIfExists('router_hotspot_profiles');
        Schema::dropIfExists('router_hotspot_users');
        Schema::dropIfExists('router_hotspot_active_sessions');
        Schema::dropIfExists('router_ppp_profiles');
        Schema::dropIfExists('router_ppp_secrets');
        Schema::dropIfExists('router_pppoe_active_sessions');
        Schema::dropIfExists('router_sync_runs');
        Schema::dropIfExists('router_interface_metrics');

        Schema::table('router_interfaces', function (Blueprint $table) {
            $table->dropIndex(['router_id', 'name']);
            $table->dropIndex(['router_id', 'running']);
            $table->dropColumn([
                'mtu', 'actual_mtu', 'mac_address', 'comment', 'dynamic', 'rx_bytes', 'tx_bytes',
                'rx_packets', 'tx_packets', 'link_downs', 'last_link_up_at', 'last_link_down_at',
                'last_seen_at', 'missing_since',
            ]);
        });

        Schema::table('router_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'cpu_count', 'total_memory_bytes', 'free_memory_bytes', 'total_storage_bytes',
                'free_storage_bytes', 'voltage_volts',
            ]);
        });

        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'verify_tls', 'ca_certificate_path', 'certificate_fingerprint', 'identity',
                'architecture_name', 'board_name', 'uptime_seconds', 'connection_latency_ms',
                'last_checked_at', 'last_connected_at', 'last_successful_sync_at', 'last_error',
                'capabilities',
            ]);
        });
    }
};
