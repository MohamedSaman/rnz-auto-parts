<?php

namespace App\Livewire\DeliveryMan;

use App\Models\Sale;
use App\Models\Payment;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Title('Delivery Man Dashboard')]
#[Layout('components.layouts.delivery-man')]
class DeliveryManDashboard extends Component
{
    public $pendingDeliveries = 0;
    public $completedDeliveries = 0;
    public $todaysDeliveries = 0;
    public $pendingPayments = 0;
    public $collectedAmount = 0;
    public $recentDeliveries = [];

    public function mount()
    {
        $userId = Auth::id();

        // Get delivery statistics
        $this->pendingDeliveries = Sale::where('status', 'confirm')
            ->where('delivery_status', 'pending')
            ->count();

        $this->completedDeliveries = Sale::where('delivered_by', $userId)
            ->where('delivery_status', 'delivered')
            ->count();

        $this->todaysDeliveries = Sale::where('delivered_by', $userId)
            ->where('delivery_status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        // Payment statistics
        $this->pendingPayments = Payment::where('collected_by', $userId)
            ->where('status', 'pending')
            ->count();

        $this->collectedAmount = Payment::where('collected_by', $userId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('collected_at', today())
            ->sum('amount');

        // Recent deliveries
        $this->recentDeliveries = Sale::where('status', 'confirm')
            ->where(function ($q) use ($userId) {
                $q->where('delivery_status', 'pending')
                    ->orWhere('delivered_by', $userId);
            })
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.delivery-man.delivery-man-dashboard');
    }
}
