<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InternetPackage;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request)
    {
        $packages = InternetPackage::when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))->orderBy('price')->paginate(min($request->integer('per_page', 15), 100));

        return $this->success($packages->items(), 'Daftar paket berhasil dimuat', ['current_page' => $packages->currentPage(), 'last_page' => $packages->lastPage(), 'total' => $packages->total()]);
    }

    public function store(StorePackageRequest $request)
    {
        $package = InternetPackage::create($request->validated());
        $this->audit->record($request, 'packages', 'create', $package, after: $package->toArray());

        return $this->success($package, 'Paket berhasil ditambahkan', status: 201);
    }

    public function show(InternetPackage $package)
    {
        return $this->success($package);
    }

    public function update(StorePackageRequest $request, InternetPackage $package)
    {
        $before = $package->toArray();
        $package->update($request->validated());
        $this->audit->record($request, 'packages', 'update_definition_only', $package, $before, $package->fresh()->toArray());

        return $this->success($package->fresh(), 'Definisi paket diperbarui; pelanggan tidak diubah otomatis');
    }

    public function destroy(Request $request, InternetPackage $package)
    {
        $before = $package->toArray();
        $package->delete();
        $this->audit->record($request, 'packages', 'delete', $package, $before);

        return $this->success(null, 'Paket berhasil diarsipkan');
    }
}
