<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StampService
{
    public function add(Customer $customer, int $change, string $note, ?Order $order = null, ?User $user = null): Customer
    {
        return DB::transaction(function () use ($customer, $change, $note, $order, $user) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $customer->stamps = max(0, $customer->stamps + $change);
            $customer->save();

            $customer->stampTransactions()->create([
                'order_id' => $order?->id,
                'user_id' => $user?->id,
                'change' => $change,
                'balance_after' => $customer->stamps,
                'note' => $note,
            ]);

            return $customer;
        });
    }

    /** Tukar stempel dengan hadiah. */
    public function redeem(Customer $customer, ?User $user = null): Customer
    {
        $target = (int) config('laundry.stamp_target');

        if (! $customer->canRedeem()) {
            throw ValidationException::withMessages([
                'stamps' => 'Stempel belum cukup. Butuh '.$target.' stempel.',
            ]);
        }

        return $this->add($customer, -$target, 'Tukar hadiah: '.config('laundry.stamp_reward'), null, $user);
    }
}
