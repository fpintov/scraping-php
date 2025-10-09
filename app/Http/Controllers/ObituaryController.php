<?php

namespace App\Http\Controllers;

use App\Models\Obituary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ObituaryController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->query('date');
        if (!$selectedDate) {
            $selectedDate = Carbon::today()->toDateString();
        }

        $availableDates = Obituary::query()
            ->selectRaw('date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn($d) => (string)$d)
            ->toArray();

        // Si no hay datos para hoy, igual mostramos vacío y el selector con fechas existentes
        $obituaries = Obituary::query()
            //->whereDate('date', $selectedDate)
            ->orderByDesc('date')
            ->orderBy('cemetery')
            ->orderBy('deceased_name')
            ->get();

        return view('obituaries.index', [
            'selectedDate' => $selectedDate,
            'availableDates' => $availableDates,
            'obituaries' => $obituaries,
        ]);
    }

    public function scrapeNow(Request $request)
    {
        $today = Carbon::today()->toDateString();

        // Limpiar toda la tabla antes de scrapear
        Obituary::query()->delete();

        Artisan::call('scrape:obituaries');

        return redirect()->route('obituaries.index', ['date' => $today]);
    }
}


