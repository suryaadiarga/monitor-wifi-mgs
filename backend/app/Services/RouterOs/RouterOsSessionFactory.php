<?php

namespace App\Services\RouterOs;

use App\Contracts\RouterOsSessionFactoryInterface;
use App\Contracts\RouterOsSessionInterface;
use App\Exceptions\MikroTikAuthenticationException;
use App\Exceptions\MikroTikConnectionException;
use App\Exceptions\MikroTikTimeoutException;
use App\Models\Router;
use App\Services\SensitiveDataSanitizer;
use RouterOS\Client;
use RouterOS\Exceptions\BadCredentialsException;
use RouterOS\Exceptions\ClientException;

class RouterOsSessionFactory implements RouterOsSessionFactoryInterface
{
    public function __construct(private readonly SensitiveDataSanitizer $sanitizer) {}

    public function connect(Router $router): RouterOsSessionInterface
    {
        $password = $router->password;
        if (! is_string($password) || $password === '') {
            throw new MikroTikAuthenticationException('Credential router belum dikonfigurasi.');
        }

        $config = [
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $password,
            'port' => $router->use_ssl ? ($router->api_ssl_port ?: 8729) : ($router->api_port ?: 8728),
            'ssl' => (bool) $router->use_ssl,
            'timeout' => (int) config('isp.mikrotik.connect_timeout', 5),
            'socket_timeout' => (int) config('isp.mikrotik.read_timeout', 8),
            'attempts' => (int) config('isp.mikrotik.connect_attempts', 2),
            'delay' => (int) config('isp.mikrotik.retry_delay_seconds', 1),
        ];

        if ($router->use_ssl) {
            $config['ssl_options'] = $this->sslOptions($router);
        }

        try {
            $client = new Client($config);
        } catch (BadCredentialsException $exception) {
            throw new MikroTikAuthenticationException('Autentikasi RouterOS ditolak.', previous: $exception);
        } catch (ClientException $exception) {
            $message = $this->sanitizer->sanitizeText($exception->getMessage()) ?? 'Koneksi RouterOS gagal.';
            if (str_contains(strtolower($message), 'timeout')) {
                throw new MikroTikTimeoutException('Koneksi RouterOS mencapai batas waktu.', previous: $exception);
            }
            throw new MikroTikConnectionException($message, previous: $exception);
        } finally {
            unset($config['pass'], $password);
        }

        return new RouterOsSession($client, $this->sanitizer);
    }

    private function sslOptions(Router $router): array
    {
        $verify = (bool) $router->verify_tls;
        $options = [
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => false,
        ];

        if ($router->ca_certificate_path) {
            if (! is_readable($router->ca_certificate_path)) {
                throw new MikroTikConnectionException('File CA RouterOS tidak dapat dibaca.');
            }
            $options['cafile'] = $router->ca_certificate_path;
        }

        if ($router->certificate_fingerprint) {
            $fingerprint = strtolower(str_replace(':', '', $router->certificate_fingerprint));
            if (! preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
                throw new MikroTikConnectionException('Fingerprint sertifikat RouterOS tidak valid.');
            }
            $options['peer_fingerprint'] = ['sha256' => $fingerprint];
        }

        return $options;
    }
}
