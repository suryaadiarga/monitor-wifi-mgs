<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\RouterSyncService;
use App\Services\SensitiveDataSanitizer;
use Illuminate\Console\Command;

class SyncRouter extends Command
{
    protected $signature = 'isp:router:sync
        {router : ID, nama, atau host router}
        {--scope=full : status, resources, active, network, config, atau full}
        {--force : Abaikan status enabled/maintenance aplikasi}
        {--wait : Tunggu lock router maksimal 45 detik}';

    protected $description = 'Jalankan satu sinkronisasi RouterOS read-only secara sinkron';

    public function __construct(
        private readonly RouterSyncService $sync,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $needle = (string) $this->argument('router');
        $router = Router::query()->where(function ($query) use ($needle) {
            if (ctype_digit($needle)) {
                $query->orWhereKey((int) $needle);
            }
            $query->orWhere('name', $needle)->orWhere('host', $needle);
        })->first();

        if (! $router) {
            $this->error('Router tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $this->option('force') && (! $router->enabled || $router->maintenance_mode)) {
            $this->error('Router tidak aktif atau sedang maintenance. Gunakan --force bila memang diperlukan.');

            return self::FAILURE;
        }

        try {
            $run = $this->sync->sync($router, (string) $this->option('scope'), (bool) $this->option('wait'));
        } catch (\Throwable $exception) {
            $this->error('Sinkronisasi gagal: '.($this->sanitizer->sanitizeText($exception->getMessage()) ?? 'Kesalahan tidak diketahui.'));

            return self::FAILURE;
        }

        if (! $run) {
            $this->warn('Sinkronisasi dilewati karena router sedang diproses oleh job lain.');

            return self::FAILURE;
        }

        $this->info('Sinkronisasi read-only berhasil.');
        $this->table(['Correlation ID', 'Scope', 'Durasi (ms)', 'Jumlah'], [[
            $run->correlation_id,
            $run->scope,
            $run->duration_ms,
            json_encode($run->counts ?? [], JSON_UNESCAPED_SLASHES),
        ]]);

        return self::SUCCESS;
    }
}
