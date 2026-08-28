<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $order = null;
        if ($request->filled(['order_number', 'phone'])) {
            $validated = $request->validate([
                'order_number' => ['required', 'string', 'max:40'],
                'phone' => ['required', 'string', 'max:32'],
            ]);

            $order = Order::query()
                ->whereRaw('UPPER(order_number) = ?', [strtoupper(trim($validated['order_number']))])
                ->where('customer_phone', trim($validated['phone']))
                ->with('items.product:id,name,slug')
                ->first();
        }

        return view('storefront.account.index', [
            'order' => $order,
            'searched' => $request->filled(['order_number', 'phone']),
        ]);
    }
}
