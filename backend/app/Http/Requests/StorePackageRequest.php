<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('post') ? 'packages.create' : 'packages.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('packages')->ignore($this->route('package')?->id)],
            'download_kbps' => ['required', 'integer', 'min:1'],
            'upload_kbps' => ['required', 'integer', 'min:1'],
            'priority' => ['integer', 'between:1,8'],
            'price' => ['required', 'numeric', 'min:0'],
            'mikrotik_profile' => ['nullable', 'string', 'max:150'],
            'radius_group' => ['nullable', 'string', 'max:150'],
            'validity_days' => ['integer', 'min:1'],
            'enabled' => ['boolean'],
        ];
    }
}
