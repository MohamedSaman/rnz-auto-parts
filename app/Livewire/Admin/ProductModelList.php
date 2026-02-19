<?php

namespace App\Livewire\Admin;

use Exception;
use Livewire\Component;
use App\Models\ProductModel;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title("Product Model List")]
class ProductModelList extends Component
{
    use WithDynamicLayout;

    public $modelName  = '';
    public $editModelId;
    public $editModelName = '';
    public $deleteId;

    public function render()
    {
        $models = ProductModel::orderBy('id', 'desc')->get();
        return view('livewire.admin.Product-modellist', [
            'models' => $models,
        ])->layout($this->layout);
    }

    public function createModel()
    {
        $this->reset(['modelName']);
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('create-model-modal');
    }

    public function saveModel()
    {
        $this->validate([
            'modelName' => 'required|unique:product_models,model_name',
        ], [
            'modelName.required' => 'Model name is required.',
            'modelName.unique'   => 'This model name already exists.',
        ]);

        try {
            ProductModel::create(['model_name' => $this->modelName]);
            $this->reset(['modelName']);
            $this->dispatch('models-updated');
            $this->js("Swal.fire('Success!', 'Model Created Successfully', 'success')");
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . addslashes($e->getMessage()) . "', 'error')");
        }
    }

    public function editModel($id)
    {
        $model = ProductModel::find($id);
        if (!$model) {
            $this->js("Swal.fire('Error!', 'Model not found', 'error')");
            return;
        }

        $this->editModelId   = $model->id;
        $this->editModelName = $model->model_name;
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('edit-model');
    }

    public function updateModel()
    {
        $this->validate([
            'editModelName' => 'required|unique:product_models,model_name,' . $this->editModelId,
        ], [
            'editModelName.required' => 'Model name is required.',
            'editModelName.unique'   => 'This model name already exists.',
        ]);

        try {
            ProductModel::where('id', $this->editModelId)->update([
                'model_name' => $this->editModelName,
            ]);
            $this->reset(['editModelName', 'editModelId']);
            $this->dispatch('models-updated');
            $this->js("Swal.fire('Success!', 'Model Updated Successfully', 'success')");
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . addslashes($e->getMessage()) . "', 'error')");
        }
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->dispatch('confirm-delete-model');
    }

    #[On('confirmDeleteModel')]
    public function deleteModel()
    {
        try {
            ProductModel::where('id', $this->deleteId)->delete();
            $this->js("Swal.fire('Deleted!', 'Model deleted successfully', 'success')");
            $this->reset(['deleteId']);
            $this->dispatch('models-updated');
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . addslashes($e->getMessage()) . "', 'error')");
        }
    }
}
