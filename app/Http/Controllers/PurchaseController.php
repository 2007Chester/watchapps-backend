<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Watchface;
use App\Models\WatchfaceSale;

class PurchaseController extends Controller
{
    /**
     * Фиксируем покупку циферблата.
     */
    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'watchface_id' => 'required|exists:watchfaces,id',
        ]);

        $watchface = Watchface::findOrFail($validated['watchface_id']);

        // Цена и валюта — берем из модели watchface
        $price = $watchface->price;
        $currency = "USD"; // позже добавим многовалютность

        // Запись продажи
        $sale = WatchfaceSale::create([
            'watchface_id' => $watchface->id,
            'user_id'      => $request->user()->id,
            'price'        => $price,
            'currency'     => $currency,
        ]);

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
        ]);
    }
}
