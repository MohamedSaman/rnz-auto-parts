<div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-cpu text-crimson me-2"></i> Product Model Management
            </h3>
            <p class="text-muted mb-0">Manage and organize your product models efficiently</p>
        </div>
        <div>
            <button class="btn btn-crimson" wire:click="createModel">
                <i class="bi bi-plus-lg me-2"></i> Add Model
            </button>
        </div>
    </div>

    <!-- Model List Table -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-list-ul text-crimson me-2"></i> Model List
                        </h5>
                        <p class="text-muted small mb-0">View and manage all product models</p>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No</th>
                                    <th>Model Name</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($models->count() > 0)
                                @foreach ($models as $model)
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-medium text-dark">{{ $loop->iteration }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $model->model_name }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $model->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                            {{ ucfirst($model->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                    type="button"
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false">
                                                <i class="bi bi-gear-fill"></i> Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button class="dropdown-item"
                                                            wire:click="editModel({{ $model->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="editModel({{ $model->id }})">
                                                        <span wire:loading wire:target="editModel({{ $model->id }})">
                                                            <i class="spinner-border spinner-border-sm me-2"></i> Loading...
                                                        </span>
                                                        <span wire:loading.remove wire:target="editModel({{ $model->id }})">
                                                            <i class="bi bi-pencil-square text-primary me-2"></i> Edit
                                                        </span>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item"
                                                            wire:click="confirmDelete({{ $model->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="confirmDelete({{ $model->id }})">
                                                        <span wire:loading wire:target="confirmDelete({{ $model->id }})">
                                                            <i class="spinner-border spinner-border-sm me-2"></i> Loading...
                                                        </span>
                                                        <span wire:loading.remove wire:target="confirmDelete({{ $model->id }})">
                                                            <i class="bi bi-trash text-danger me-2"></i> Delete
                                                        </span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="alert alert-primary bg-opacity-10">
                                            <i class="bi bi-info-circle me-2"></i> No product models found.
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Model Modal -->
    <div wire:ignore.self class="modal fade" id="createModelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden">
                <div class="modal-header text-white" style="background: linear-gradient(135deg,#1e293b 0%,#0f172a 100%);">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="bi bi-cpu-fill me-2"></i> Add Product Model
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form wire:submit.prevent="saveModel">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Model Name</label>
                            <input type="text" class="form-control" wire:model="modelName"
                                placeholder="e.g. Toyota Corolla 2020" autofocus>
                            @error('modelName')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Save Model
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Model Modal -->
    <div wire:ignore.self class="modal fade" id="editModelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden">
                <div class="modal-header text-white" style="background: linear-gradient(135deg,#1e293b 0%,#0f172a 100%);">
                    <h5 class="modal-title fw-bold d-flex align-items-center">
                        <i class="bi bi-pencil-square me-2"></i> Edit Product Model
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form wire:submit.prevent="updateModel">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Model Name</label>
                            <input type="text" class="form-control" wire:model="editModelName"
                                placeholder="Enter model name">
                            @error('editModelName')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Update Model
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }
    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 12px 12px 0 0 !important;
        padding: 1.25rem 1.5rem;
    }
    .table td { vertical-align: middle; }
    .btn-crimson {
        background-color: var(--primary);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    .btn-crimson:hover {
        background-color: var(--primary-600);
        color: white;
        transform: translateY(-2px);
    }
    .text-crimson { color: var(--primary) !important; }
    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
    }
    .btn-primary:hover {
        background-color: var(--primary-600);
        border-color: var(--primary-600);
    }
</style>
@endpush

@push('scripts')
<script>
    window.addEventListener('confirm-delete-model', () => {
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                Livewire.dispatch('confirmDeleteModel');
            }
        });
    });

    window.addEventListener('edit-model', () => {
        const modal = new bootstrap.Modal(document.getElementById('editModelModal'));
        modal.show();
    });

    window.addEventListener('create-model-modal', () => {
        const modal = new bootstrap.Modal(document.getElementById('createModelModal'));
        modal.show();
    });

    Livewire.on('models-updated', () => {
        const createModal = bootstrap.Modal.getInstance(document.getElementById('createModelModal'));
        const editModal   = bootstrap.Modal.getInstance(document.getElementById('editModelModal'));
        if (createModal) createModal.hide();
        if (editModal) editModal.hide();
    });
</script>
@endpush
