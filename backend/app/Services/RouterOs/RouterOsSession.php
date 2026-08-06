<?php

namespace App\Services\RouterOs;

use App\Contracts\RouterOsSessionInterface;
use App\Exceptions\MikroTikTrapException;
use App\Services\SensitiveDataSanitizer;
use RouterOS\Client;
use RouterOS\Query;

class RouterOsSession implements RouterOsSessionInterface
{
    private const SENSITIVE_PROPERTIES = ['password', 'secret', 'private-key', 'preshared-key', 'community', 'token'];

    private const READ_ONLY_COMMANDS = [
        '/system/resource/print', '/system/identity/print', '/system/routerboard/print', '/system/health/print',
        '/interface/print', '/ppp/active/print', '/ppp/secret/print', '/ppp/profile/print',
        '/interface/pppoe-server/print', '/interface/pppoe-server/server/print',
        '/ip/hotspot/active/print', '/ip/hotspot/user/print', '/ip/hotspot/user/profile/print',
        '/ip/hotspot/host/print', '/ip/hotspot/ip-binding/print', '/ip/hotspot/print',
        '/queue/simple/print', '/queue/tree/print', '/queue/type/print',
        '/ip/dhcp-server/lease/print', '/ip/dhcp-server/print', '/ip/address/print', '/ip/route/print',
    ];

    public function __construct(
        private ?Client $client,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    public function query(string $command, array $properties): array
    {
        if (! in_array($command, self::READ_ONLY_COMMANDS, true)) {
            throw new \LogicException('Perintah RouterOS tidak termasuk allowlist read-only.');
        }

        if ($this->client === null) {
            throw new \LogicException('Sesi RouterOS sudah ditutup.');
        }

        $properties = array_values(array_unique(array_filter($properties, fn ($property) => is_string($property) && $property !== '')));
        foreach ($properties as $property) {
            $normalized = strtolower(str_replace(['_', '.'], '-', $property));
            foreach (self::SENSITIVE_PROPERTIES as $sensitive) {
                if (str_contains($normalized, $sensitive)) {
                    throw new \LogicException('Properti sensitif tidak boleh dibaca dari RouterOS.');
                }
            }
        }
        $query = new Query($command);
        if ($properties !== []) {
            $query->equal('.proplist', implode(',', $properties));
        }

        $raw = $this->client->query($query)->readRAW();
        if (in_array('!trap', $raw, true) || in_array('!fatal', $raw, true)) {
            throw new MikroTikTrapException($this->sanitizer->sanitizeText($this->trapMessage($raw)) ?? 'RouterOS menolak query.');
        }

        $parsed = $this->client->parseResponse($raw);
        $rows = [];
        foreach ($parsed as $key => $row) {
            if (is_int($key) && is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function close(): void
    {
        $this->client = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function trapMessage(array $raw): string
    {
        foreach ($raw as $word) {
            if (is_string($word) && str_starts_with($word, '=message=')) {
                return substr($word, 9);
            }
        }

        return 'RouterOS mengembalikan trap atau fatal error.';
    }
}
