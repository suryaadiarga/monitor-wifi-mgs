<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('post') ? 'customers.create' : 'customers.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('customer')?->id;

        return [
            'customer_number' => ['required', 'string', 'max:50', Rule::unique('customers')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:3000'],
            'installation_address' => ['nullable', 'string', 'max:3000'],
            'router_id' => ['nullable', 'exists:routers,id'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'reseller_id' => ['nullable', 'exists:users,id'],
            'pppoe_username' => ['nullable', 'string', 'max:150', Rule::unique('customers')->ignore($id)],
            'pppoe_password' => ['nullable', 'string', 'min:8', 'max:1000'],
            'status' => ['required', Rule::in(['prospek', 'aktif', 'isolir', 'suspend', 'berhenti'])],
            'installed_at' => ['nullable', 'date'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
