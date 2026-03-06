<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title('Home — RNZ Auto Parts')]
class AdminHome extends Component
{
    use WithDynamicLayout;

    public function render()
    {
        return view('livewire.admin.admin-home')->layout($this->layout);
    }
}
