<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Purchase Return Modify')]
#[Layout('components.layouts.admin')]
class PurchaseReturnModify extends PurchaseReturnList
{
	public string $pageMode = 'modify';

	public function render()
	{
		return view('livewire.admin.purchase-return-modify', $this->getListData());
	}
}
