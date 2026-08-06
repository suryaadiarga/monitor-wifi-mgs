<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('post') ? 'routers.create' : 'routers.update';

        return $this->user()?->can($permission) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'host' => ['required', 'string', 'max:255', Rule::unique('routers', 'host')->ignore($this->route('router')?->id)->whereNull('deleted_at')],
            'api_port' => ['required', 'integer', 'between:1,65535'],
            'api_ssl_port' => ['nullable', 'integer', 'between:1,65535'],
            'use_ssl' => ['boolean'],
            'verify_tls' => ['boolean'],
            'ca_certificate_path' => ['nullable', 'string', 'max:500', 'regex:#^/etc/isp-manager/router-certs/(?!.*\.\.)[A-Za-z0-9._/-]+$#'],
            'certificate_fingerprint' => ['nullable', 'string', 'max:95', 'regex:/^(?:[A-Fa-f0-9]{2}:){31}[A-Fa-f0-9]{2}$|^[A-Fa-f0-9]{64}$/'],
            'username' => ['required', 'string', 'max:150'],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:8', 'max:1000'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'pop_id' => ['nullable', 'exists:pops,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'enabled' => ['boolean'],
            'maintenance_mode' => ['boolean'],
            'status' => ['nullable', Rule::in(['unknown', 'online', 'offline'])],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->boolean('use_ssl') && ! $this->boolean('verify_tls', true)) {
                $validator->errors()->add('verify_tls', 'Validasi TLS wajib aktif untuk API-SSL.');
            }
        }];
    }
}
