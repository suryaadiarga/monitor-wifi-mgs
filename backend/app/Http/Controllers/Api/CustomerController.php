<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request)
    {
        $customers = Customer::with(['package:id,name', 'router:id,name'])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('customer_number', 'like', '%'.$request->string('search').'%')->orWhere('pppoe_username', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->user()->hasRole('Reseller'), fn ($query) => $query->where('reseller_id', $request->user()->id))
            ->latest()->paginate(min($request->integer('per_page', 15), 100));

        return $this->success($customers->items(), 'Daftar pelanggan berhasil dimuat', ['current_page' => $customers->currentPage(), 'last_page' => $customers->lastPage(), 'total' => $customers->total()]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create(['uuid' => (string) Str::uuid()] + $request->validated());
        $this->audit->record($request, 'customers', 'create', $customer, after: $customer->toArray());

        return $this->success($customer, 'Pelanggan berhasil ditambahkan', status: 201);
    }

    public function show(Request $request, Customer $customer)
    {
        abort_if($request->user()->hasRole('Reseller') && $customer->reseller_id !== $request->user()->id, 403);

        return $this->success($customer->load(['package', 'router']));
    }

    public function update(StoreCustomerRequest $request, Customer $customer)
    {
        abort_if($request->user()->hasRole('Reseller') && $customer->reseller_id !== $request->user()->id, 403);
        $before = $customer->toArray();
        $attributes = $request->validated();
        if (blank($attributes['pppoe_password'] ?? null)) {
            unset($attributes['pppoe_password']);
        }
        $customer->update($attributes);
        $this->audit->record($request, 'customers', 'update', $customer, $before, $customer->fresh()->toArray());

        return $this->success($customer->fresh(), 'Pelanggan berhasil diperbarui');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $before = $customer->toArray();
        $customer->delete();
        $this->audit->record($request, 'customers', 'delete', $customer, $before);

        return $this->success(null, 'Pelanggan berhasil diarsipkan');
    }
}
