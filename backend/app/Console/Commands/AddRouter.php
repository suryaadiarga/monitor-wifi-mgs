<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\AuditLogService;
use App\Services\MikroTikService;
use App\Services\SensitiveDataSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AddRouter extends Command
{
    protected $signature = 'isp:router:add
        {--name= : Nama router}
        {--host= : Host atau IP router}
        {--port= : Port API non-TLS}
        {--username= : Username API read-only}
        {--ssl : Gunakan API-SSL}
        {--ssl-port= : Port API-SSL}
        {--ca= : Path CA certificate}
        {--fingerprint= : SHA-256 fingerprint certificate}
        {--password-stdin : Baca satu baris password dari STDIN tanpa echo}
        {--inactive : Simpan router dalam keadaan nonaktif}';

    protected $description = 'Tambah atau perbarui router dengan credential terenkripsi melalui input rahasia';

    public function __construct(
        private readonly MikroTikService $mikroTik,
        private readonly SensitiveDataSanitizer $sanitizer,
        private readonly AuditLogService $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->valueOrAsk('name', 'Nama router');
        $host = $this->valueOrAsk('host', 'Host/IP router');
        $port = (int) ($this->option('port') ?: $this->ask('Port API', '8728'));
        $username = $this->valueOrAsk('username', 'Username API');
        $useSsl = (bool) $this->option('ssl');
        $sslPort = (int) ($this->option('ssl-port') ?: 8729);
        $password = $this->readPassword();

        $attributes = [
            'name' => $name,
            'host' => $host,
            'api_port' => $port,
            'api_ssl_port' => $sslPort,
            'use_ssl' => $useSsl,
            'verify_tls' => true,
            'ca_certificate_path' => $this->option('ca'),
            'certificate_fingerprint' => $this->option('fingerprint'),
            'username' => $username,
            'password' => $password,
            'enabled' => ! $this->option('inactive'),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:150'],
            'host' => ['required', 'string', 'max:255'],
            'api_port' => ['required', 'integer', 'between:1,65535'],
            'api_ssl_port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:1000'],
            'ca_certificate_path' => ['nullable', 'string', 'max:1000'],
            'certificate_fingerprint' => ['nullable', 'string', 'regex:/^(?:[A-Fa-f0-9]{2}:){31}[A-Fa-f0-9]{2}$|^[A-Fa-f0-9]{64}$/'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            unset($password, $attributes['password']);

            return self::FAILURE;
        }

        $router = Router::withTrashed()->where('host', $host)->first();
        $created = $router === null;
        if ($router) {
            $router->restore();
            $router->fill($attributes)->save();
        } else {
            $router = Router::create($attributes);
        }
        unset($password, $attributes['password']);

        $this->audit->recordSystem('routers', $created ? 'create' : 'credential_update', $router, [
            'name' => $router->name,
            'host' => $router->host,
            'credential_configured' => true,
        ]);

        $result = $this->mikroTik->testConnection($router);
        $checkedAt = now();
        $router->update([
            'status' => $result['success'] ? 'online' : 'offline',
            'last_checked_at' => $checkedAt,
            'last_connected_at' => $result['success'] ? $checkedAt : $router->last_connected_at,
            'last_seen_at' => $result['success'] ? $checkedAt : $router->last_seen_at,
            'connection_latency_ms' => $result['latency_ms'] ?? null,
            'identity' => $result['identity'] ?? $router->identity,
            'routeros_version' => $result['routeros_version'] ?? $router->routeros_version,
            'model' => $result['model'] ?? $router->model,
            'serial_number' => $result['serial_number'] ?? $router->serial_number,
            'uptime_seconds' => $result['uptime_seconds'] ?? $router->uptime_seconds,
            'last_error' => $result['success'] ? null : $this->sanitizer->sanitizeText($result['message'] ?? 'Koneksi gagal.'),
            'capabilities' => array_replace($router->capabilities ?? [], $this->connectionState($result, $checkedAt)),
        ]);
        $this->audit->recordSystem('routers', 'test_connection', $router, [
            'success' => $result['success'],
            'latency_ms' => $result['latency_ms'] ?? null,
            'error_code' => $result['error_code'] ?? null,
        ], $result['success'] ? 'success' : 'failed', $result['success'] ? null : ($result['message'] ?? null));

        if (! $result['success']) {
            $this->error('Router tersimpan, tetapi connection test gagal: '.$this->sanitizer->sanitizeText($result['message'] ?? 'Koneksi gagal.'));

            return self::FAILURE;
        }

        $this->info(($created ? 'Router ditambahkan' : 'Router diperbarui').' dan connection test berhasil.');
        $this->line('Identity: '.($result['identity'] ?? '-'));
        $this->line('RouterOS: '.($result['routeros_version'] ?? '-'));
        $this->line('Latency: '.($result['latency_ms'] ?? '-').' ms');

        return self::SUCCESS;
    }

    private function valueOrAsk(string $option, string $question): string
    {
        return (string) ($this->option($option) ?: $this->ask($question));
    }

    private function readPassword(): string
    {
        if ($this->option('password-stdin')) {
            $line = fgets(STDIN);

            return $line === false ? '' : rtrim($line, "\r\n");
        }

        return (string) $this->secret('Password API');
    }

    private function connectionState(array $result, \DateTimeInterface $checkedAt): array
    {
        $code = $result['error_code'] ?? null;

        return [
            'tcp_ok' => $result['success'] || $code === 'authentication_failed' || $code === 'routeros_trap',
            'auth_ok' => $result['success'] || $code === 'routeros_trap' ? true : ($code === 'authentication_failed' ? false : null),
            'checked_at' => $checkedAt->format(DATE_ATOM),
            'last_error_code' => $code,
        ];
    }
}
