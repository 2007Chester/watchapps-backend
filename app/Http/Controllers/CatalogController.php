<?php

namespace App\Http\Controllers;

use App\Models\Watchface;
use App\Models\Category;

class CatalogController extends Controller
{
    /**
     * Топ продаж (позже добавим сортировку по покупкам)
     */
    public function top()
    {
        return Watchface::where('status', 'published')
            ->orderBy('price', 'desc')
            ->take(20)
            ->get();
    }

    /**
     * Новейшие публикации
     */
    public function new()
    {
        return Watchface::where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
    }

    /**
     * Скидки
     */
    public function discounts()
    {
        return Watchface::where('status', 'published')
            ->where('price', '<', 1000) // временная логика, позже добавим discount_price
            ->get();
    }

    /**
     * По категории
     */
    public function byCategory($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return $category->watchfaces()
            ->where('status', 'published')
            ->get();
    }

    /**
     * Страница товара
     */
    public function show($slug)
    {
        return Watchface::where('slug', $slug)
            ->with(['files', 'categories'])
            ->firstOrFail();
    }
}
