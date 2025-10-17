<?php

namespace App\Http\Controllers;

// Importa el modelo Obituary que representa la tabla de obituarios en la base de datos.
use App\Models\Obituary;
// Importa la clase Carbon para manejar fechas.
use Carbon\Carbon;
// Importa la clase Request para manejar las peticiones HTTP.
use Illuminate\Http\Request;
// Importa la clase Artisan para ejecutar comandos de consola Artisan desde el código PHP de Laravel.
use Illuminate\Support\Facades\Artisan;

class ObituaryController extends Controller
{
    // Método para mostrar la página de inicio con los obituarios
    public function index(Request $request)
    {
        // Obtener la fecha seleccionada desde la URL
        $selectedDate = $request->query('date');
        // Si no hay fecha seleccionada, usar la fecha actual
        if (!$selectedDate) {
            $selectedDate = Carbon::today()->toDateString();
        }

        // Obtener el cementerio seleccionado desde la URL
        $selectedCemetery = $request->query('cemetery');
        // Si no hay cementerio seleccionado, usar "all"
        if (!$selectedCemetery) {
            $selectedCemetery = 'all';
        }

        // Obtener las fechas disponibles de los obituarios
        $availableDates = Obituary::query()
            ->selectRaw('date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        // Agregar opción "Mostrar todas" al inicio del array
        array_unshift($availableDates, 'all');

        // Obtener los cementerios disponibles
        $availableCemeteries = Obituary::query()
            ->selectRaw('cemetery')
            ->distinct()
            ->orderBy('cemetery')
            ->pluck('cemetery')
            ->toArray();

        // Agregar opción "Todos los cementerios" al inicio del array
        array_unshift($availableCemeteries, 'all');

        // Filtrar obituarios según la selección
        $obituaries = Obituary::query();
        
        // Si no se seleccionó "Mostrar todas", filtrar por fecha
        if ($selectedDate !== 'all') {
            $obituaries->whereDate('date', $selectedDate);
        }
        
        // Si no se seleccionó "Todos los cementerios", filtrar por cementerio
        if ($selectedCemetery !== 'all') {
            $obituaries->where('cemetery', $selectedCemetery);
        }
        
        $obituaries = $obituaries->orderByDesc('date')
            ->orderBy('cemetery')
            ->orderBy('deceased_name')
            ->get();

        return view('obituaries.index', [
            'selectedDate' => $selectedDate,
            'availableDates' => $availableDates,
            'selectedCemetery' => $selectedCemetery,
            'availableCemeteries' => $availableCemeteries,
            'obituaries' => $obituaries,
        ]);
    }

    // Método para ejecutar el scraping de los obituarios
    public function scrapeNow(Request $request)
    {
        // Obtener la fecha actual
        $today = Carbon::today()->toDateString();

        // Ejecutar el comando de scraping de los obituarios
        // (sin eliminar registros existentes - solo agrega nuevos)
        Artisan::call('scrape:obituaries');

        // Redirigir a la página de inicio con la fecha actual
        return redirect()->route('obituaries.index', ['date' => $today]);
    }
}


