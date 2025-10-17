<?php

namespace App\Console\Commands;

use App\Models\Obituary;
use Carbon\Carbon;
// Importa la clase Command para manejar las consola de Laravel.
use Illuminate\Console\Command;
// Importa la clase Http para manejar las peticiones HTTP.
use Illuminate\Support\Facades\Http;
// Importa la clase Log para manejar los logs de Laravel.
use Illuminate\Support\Facades\Log;
// Importa la clase Storage para manejar el almacenamiento de Laravel.
use Illuminate\Support\Facades\Storage;

// Define la clase ScrapeObituaries que extiende de Command.
class ScrapeObituaries extends Command
{
    // Define la firma del comando para ejecutar el scraping de los obituarios.
    protected $signature = 'scrape:obituaries';
    // Define la descripción del comando para ejecutar el scraping de los obituarios.
    protected $description = 'Scrapea obituarios de cementerios y guarda en BD';
    
    // Define el método handle() que ejecuta el scraping de los obituarios.
    public function handle(): int
    {
        // Define el array de resultados de los scraping de los obituarios.
        $allResults = [];
        // Agrega los resultados del scraping del parque del mar a los resultados.
        $allResults = array_merge($allResults, $this->scrapeParqueDelMar());
        // Agrega los resultados del scraping del sendero a los resultados.
        $allResults = array_merge($allResults, $this->scrapeSendero());
        // Agrega los resultados del scraping del parque de auco a los resultados.
        $allResults = array_merge($allResults, $this->scrapeParqueDeAuco());

        // Define el número de registros insertados.
        $numInserted = 0;
        // Recorre los resultados de los scraping de los obituarios.
        foreach ($allResults as $item) {
            if (!isset($item['date'], $item['cemetery'], $item['deceased_name'])) {
                // Si el ítem no tiene fecha, cementerio o nombre de fallecido, salta al siguiente ítem.
                continue;
            }
            
            // Define la fecha, cementerio y nombre de fallecido del ítem.
            $date = Carbon::parse($item['date'])->toDateString();
            // Define el cementerio del ítem.
            $cemetery = $item['cemetery'];
            // Define el nombre de fallecido del ítem.
            $deceasedName = $item['deceased_name'];
            $park = $item['park'] ?? null;
            
            // Verifica si ya existe el registro.
            $existing = Obituary::whereDate('date', $date)
                ->where('cemetery', $cemetery)
                ->where('deceased_name', $deceasedName)
                ->first();
            
            // Si el registro no existe, crea el registro.
            if (!$existing) {
                // Crea el registro.
                Obituary::create([
                    'date' => $date,
                    'cemetery' => $cemetery,
                    'deceased_name' => $deceasedName,
                    'park' => $park,
                ]);
                // Incrementa el número de registros insertados.
                $numInserted++;
            }
        }

        // Limpiar archivos HTML temporales de la carpeta scraping
        $this->cleanScrapingFiles();

        // Muestra el número de registros insertados.
        $this->info("Scraping completado. Registros procesados: {$numInserted}");
        // Retorna el éxito.
        return self::SUCCESS;
    }

    // Define el método scrapeParqueDelMar() que scrapea los obituarios del parque del mar.
    // Retorna un array de resultados.
    private function scrapeParqueDelMar(): array
    {
        // Define la URL del parque del mar.
        $url = 'https://www.parquedelmar.cl/webpdm/obituario.aspx';
        // Define el cementerio del parque del mar.
        $cemetery = 'Parque del Mar';
        // Define el array de resultados.
        $results = [];

        try {
            // Define la respuesta de la petición HTTP.
            $response = Http::get($url);
            // Si la respuesta es fallida, registra el error.
            if ($response->failed()) {
                Log::error("Error al acceder a $url", ['status' => $response->status()]);
                return [];
            }

            // Define el HTML de la respuesta.
            $html = $response->body();
            // Define el DOMDocument.
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            // Limpia los errores de libxml.
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);
            
            // Extraer bloques de fecha (ej: "Con Cón, 7 octubre 2025")
            $dateNodes = $xpath->query('//div[contains(@id,"u909-4")]/p');

            // Recorre los nodos de fecha.
            foreach ($dateNodes as $dateNode) {
                // Define el texto del nodo de fecha.   
                $dateText = trim($dateNode->textContent);
                // Si el texto no coincide con el formato de fecha, salta al siguiente nodo.
                if (!preg_match('/(\d{1,2})\s+([a-záéíóú]+)\s+(\d{4})/iu', $dateText, $m)) {
                    continue;
                }

                // Normaliza el formato de fecha "7 octubre 2025" → "2025-10-07"
                $meses = [
                    'enero'=>'01','febrero'=>'02','marzo'=>'03','abril'=>'04','mayo'=>'05','junio'=>'06',
                    'julio'=>'07','agosto'=>'08','septiembre'=>'09','octubre'=>'10','noviembre'=>'11','diciembre'=>'12'
                ];
                // Define el día de la fecha.
                $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                // Define el mes de la fecha.
                $mes = strtolower($m[2]);
                // Define el año de la fecha.
                $anio = $m[3];
                // Define la fecha.
                $fecha = "$anio-{$meses[$mes]}-$dia";

                // Busca los tres contenedores <div> inmediatamente después del bloque de fecha.
                $group = $dateNode->parentNode->nextSibling;
                while ($group && ($group->nodeType !== XML_ELEMENT_NODE || strpos($group->getAttribute('id'), 'pu916-10') === false)) {
                    $group = $group->nextSibling;
                }

                // Si el grupo no existe, salta al siguiente nodo.
                if (!$group) continue;
                // Define los nodos de horas, nombres y actividades.

                // Define los nodos de horas.
                $horas = $xpath->query('.//div[contains(@id,"u916")]/p', $group);
                // Define los nodos de nombres.
                $nombres = $xpath->query('.//div[contains(@id,"u918")]/p', $group);
                // Define los nodos de actividades.
                $actividades = $xpath->query('.//div[contains(@id,"u920")]/p', $group);

                // Define el número máximo de nodos.
                $max = min($horas->length, $nombres->length, $actividades->length);
                // Recorre los nodos.
                for ($i = 0; $i < $max; $i++) {
                    // Define el nombre del fallecido.
                    $nombre = trim($nombres->item($i)->textContent);
                    // Define la actividad del fallecido.
                    $actividad = trim($actividades->item($i)->textContent);

                    // Si el nombre es vacío o la actividad no contiene "parque del mar", salta al siguiente nodo.
                    if ($nombre === '' || stripos($actividad, 'parque del mar') === false) {
                        continue;
                    }

                    // Quitar prefijos "Sr." y "Sra." del nombre del fallecido
                    $nombre = preg_replace('/^(Sr\.|Sra\.|Srta\.)\s*/i', '', $nombre);

                    // Agrega el resultado al array de resultados.
                    $results[] = [
                        'date' => $fecha,
                        'cemetery' => $cemetery,
                        'deceased_name' => $nombre,
                        'park' => 'Concón, Valparaíso',
                    ];
                }
            }

            Log::info('Parque del Mar: registros extraídos', ['total' => count($results)]);
            return $results;

        } catch (\Exception $e) {
            Log::error('Error en scrapeParqueDelMar: ' . $e->getMessage());
            return [];
        }
    }


    // private function scrapeSendero()
    // {
    //     try {
    //         $url = 'https://sucursalvirtual-sendero-api.gux.cl/api/web/Obituario';
    //         $response = Http::get($url);
    
    //         // Si la respuesta es fallida, registra el error.
    //         if ($response->failed()) {
    //             Log::error('Error al acceder a Sendero API', ['status' => $response->status()]);
    //             return collect();
    //         }
    
    //         // Define los datos de la respuesta.
    //         $data = $response->json();

    //         // Parques a excluir del scraping
    //         $parquesExcluidos = [
    //             'SAN BERNARDO',
    //             'MAIPU',
    //             'PADRE HURTADO',
    //             'CONCEPCION',
    //             'RANCAGUA',
    //             'BALMACEDA',
    //             'ARICA',
    //             'IQUIQUE',
    //             'TEMUCO',
    //             'SAN ANTONIO'
    //         ];

    //         // Recorre los datos de la respuesta.
    //         return collect($data)->filter(function ($item) use ($parquesExcluidos) {
    //             // Define el parque físico.
    //             $parque = isset($item['PARQUE_FISICO'])
    //                 ? (explode(' - ', $item['PARQUE_FISICO'])[1] ?? $item['PARQUE_FISICO'])
    //                 : null;

    //             // Excluir si el parque está en la lista de excluidos
    //             if ($parque && in_array(strtoupper(trim($parque)), $parquesExcluidos)) {
    //                 return false;
    //             }

    //             return true;
    //         })->map(function ($item) {
    //             // Define la fecha y hora de la muerte.
    //             $fechaHora = explode(' ', $item['FECHA_SERVICIO'] ?? '');
    //             $fecha = $fechaHora[0] ?? null;
    //             // Define el parque físico.
    //             $parque = isset($item['PARQUE_FISICO'])
    //                 ? (explode(' - ', $item['PARQUE_FISICO'])[1] ?? $item['PARQUE_FISICO'])
    //                 : null;

    //             // Agrega el resultado al array de resultados.
    //             return [
    //                 'date' => $fecha,
    //                 'cemetery' => 'Parque del Sendero',
    //                 'deceased_name' => $item['NOMBRE_FALLECIDO'] ?? null,
    //                 'park' => $parque,
    //             ];
    //         })->toArray();
    //     } catch (\Exception $e) {
    //         // Si hay un error, registra el error.
    //         Log::error('Error en scrapeSendero: ' . $e->getMessage());
    //         return [];
    //     }
    // }
    private function scrapeSendero()
{
    try {
        $url = 'https://sucursalvirtual-sendero-api.gux.cl/api/web/Obituario';
        $response = Http::get($url);

        // Si la respuesta es fallida, registra el error.
        if ($response->failed()) {
            Log::error('Error al acceder a Sendero API', ['status' => $response->status()]);
            return collect();
        }

        // Define los datos de la respuesta.
        $data = $response->json();

        // Parques a excluir del scraping
        $parquesExcluidos = [
            'SAN BERNARDO',
            'MAIPU',
            'PADRE HURTADO',
            'CONCEPCION',
            'RANCAGUA',
            'BALMACEDA',
            'ARICA',
            'IQUIQUE',
            'TEMUCO',
            'SAN ANTONIO'
        ];

        // Recorre los datos de la respuesta.
        return collect($data)->filter(function ($item) use ($parquesExcluidos) {
            // Define el parque físico.
            $parque = isset($item['PARQUE_FISICO'])
                ? (explode(' - ', $item['PARQUE_FISICO'])[1] ?? $item['PARQUE_FISICO'])
                : null;

            // Excluir si el parque está en la lista de excluidos
            if ($parque && in_array(strtoupper(trim($parque)), $parquesExcluidos)) {
                return false;
            }

            return true;
        })->map(function ($item) {
            // Define la fecha y hora de la muerte.
            $fechaHora = explode(' ', $item['FECHA_SERVICIO'] ?? '');
            $fecha = $fechaHora[0] ?? null;
            // Define el parque físico.
            $parque = isset($item['PARQUE_FISICO'])
                ? (explode(' - ', $item['PARQUE_FISICO'])[1] ?? $item['PARQUE_FISICO'])
                : null;

            // Eliminar " Q.E.P.D" del nombre del fallecido
            $deceasedName = $item['NOMBRE_FALLECIDO'] ?? null;
            if ($deceasedName) {
                $deceasedName = str_replace(' Q.E.P.D', '', $deceasedName);
            }

            // Agrega el resultado al array de resultados.
            return [
                'date' => $fecha,
                'cemetery' => 'Parque del Sendero',
                'deceased_name' => $deceasedName,
                'park' => $parque,
            ];
        })->toArray();
    } catch (\Exception $e) {
        // Si hay un error, registra el error.
        Log::error('Error en scrapeSendero: ' . $e->getMessage());
        return [];
    }
}

    // Define el método scrapeParqueDeAuco() que scrapea los obituarios del parque de auco.
    // Retorna un array de resultados.
    private function scrapeParqueDeAuco(): array
    {
        try {
            $url = 'https://parquedeauco.cl/obituario/';
            $response = Http::get($url);
    
            if ($response->failed()) {
                Log::error('Error al acceder a Parque de Auco', ['status' => $response->status()]);
                return [];
            }
    
            $html = $response->body();
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);
    
            // Solo filas dentro de la tabla de obituarios
            $rows = $xpath->query('//div[@class="boxObituario"]//table//tr[td]');
    
            $results = [];
            foreach ($rows as $row) {
                $cols = $xpath->query('.//td', $row);
                if ($cols->length < 2) {
                    continue;
                }
    
                $nombre = trim($cols->item(0)->textContent);
                $fechaTexto = trim($cols->item(1)->textContent);

                Log::info('Nombre del fallecido', ['valor' => [$nombre]]);
    
                // Normalizar el formato de fecha "octubre 8, 2025" → "2025-10-08"
                $meses = [
                    'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
                    'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
                    'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
                ];
    
                // Define la fecha.
                $fecha = null;
                // Si la fecha coincide con el formato de fecha, define la fecha.
                if (preg_match('/([a-záéíóú]+)\s+(\d{1,2}),\s*(\d{4})/iu', $fechaTexto, $m)) {
                    // Define el mes de la fecha.
                    $mes = strtolower($m[1]);
                    // Si el mes existe, define la fecha.
                    if (isset($meses[$mes])) {
                        // Define el día de la fecha.
                        $dia = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                        // Define el año de la fecha.
                        $anio = $m[3];
                        $fecha = "$anio-{$meses[$mes]}-$dia";
                    }
                }
    
                // Si no logra parsear la fecha, ignora la fila.
                if (!$fecha) {
                    Log::warning('No se pudo parsear la fecha en Parque de Auco', ['valor' => $fechaTexto]);
                    continue;
                }
    
                // Agrega el resultado al array de resultados.
                $results[] = [
                    'date' => $fecha,
                    'cemetery' => 'Parque de Auco',
                    'deceased_name' => $nombre,
                    'park' => 'Rinconada, Valparaíso',
                ];
            }
    
            // Eliminar duplicados exactos (nombre + fecha)
            // $results = collect($results)
            //     ->unique(fn($i) => $i['deceased_name'].'|'.$i['date'])
            //     ->values()
            //     ->all();
    
            return $results;
    
        } catch (\Exception $e) {
            Log::error('Error en scrapeParqueDeAuco: '.$e->getMessage());
            return [];
        }
    }
    
    private function httpGet(string $url)
    {
        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->get($url);
    }

    private function saveSnapshot(string $slug, string $html): void
    {
        $dir = 'scraping';
        $timestamp = Carbon::now()->format('Ymd_His');
        $filename = $dir . '/' . $slug . '_' . $timestamp . '.html';
        Storage::disk('local')->put($filename, $html);
    }

    private function parseWithHeuristics(string $html, string $cemetery): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $candidates = [
            // Tarjetas comunes
            "//div[contains(@class,'obituario') or contains(@class,'obituary') or contains(@class,'card') or contains(@class,'item')]",
            // Filas de tabla
            "//table//tr",
            // Listas
            "//ul/li",
        ];

        $results = [];
        foreach ($candidates as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $text = trim($this->nodeText($node));
                if ($text === '') {
                    continue;
                }

                $name = $this->extractName($text);
                if ($name === null) {
                    continue;
                }

                $date = $this->extractDate($text) ?: Carbon::today();
                $results[] = [
                    'date' => Carbon::parse($date)->toDateString(),
                    'cemetery' => $cemetery,
                    'deceased_name' => $name,
                    'park' => 'Rinconada, Valparaíso',
                ];
            }

            if (!empty($results)) {
                break; // Primera coincidencia razonable
            }
        }

        // Deduplicar dentro del set
        $unique = [];
        $seen = [];
        foreach ($results as $r) {
            $key = $r['date'] . '|' . mb_strtolower($r['cemetery']) . '|' . mb_strtolower($r['deceased_name']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $r;
            }
        }
        return $unique;
    }

    private function nodeText(\DOMNode $node): string
    {
        $text = '';
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->nodeValue;
        }
        foreach ($node->childNodes as $child) {
            $text .= ' ' . $this->nodeText($child);
        }
        return preg_replace('/\s+/u', ' ', trim($text));
    }

    private function extractName(string $text): ?string
    {
        // Heurística: buscar secuencias de letras con posibles tildes y espacios (2-5 palabras)
        if (preg_match('/([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){1,4})/u', $text, $m)) {
            $candidate = trim($m[1]);
            // Filtrar textos genéricos
            $banned = ['Obituario', 'Obituarios', 'Funeral', 'Parque', 'Cementerio'];
            foreach ($banned as $b) {
                if (mb_stripos($candidate, $b) !== false) {
                    return null;
                }
            }
            return $candidate;
        }
        return null;
    }

    private function extractDate(string $text): ?string
    {
        // dd-mm-yyyy o dd/mm/yyyy
        if (preg_match('/(\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b)/', $text, $m)) {
            return Carbon::parse(str_replace('/', '-', $m[1]))->toDateString();
        }
        // yyyy-mm-dd
        if (preg_match('/(\b\d{4}-\d{2}-\d{2}\b)/', $text, $m)) {
            return Carbon::parse($m[1])->toDateString();
        }
        // Español "12 de marzo de 2024"
        if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+de\s+(\d{4})/iu', $text, $m)) {
            $months = [
                'enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
                'julio'=>7,'agosto'=>8,'septiembre'=>9,'setiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12,
            ];
            $day = (int)$m[1];
            $mon = $months[mb_strtolower($m[2])] ?? null;
            $year = (int)$m[3];
            if ($mon) {
                return Carbon::create($year, $mon, $day)->toDateString();
            }
        }
        return null;
    }

    /**
     * Limpia los archivos HTML temporales de la carpeta scraping
     */
    private function cleanScrapingFiles(): void
    {
        try {
            $scrapingDir = 'scraping';
            
            // Verificar si la carpeta existe
            if (Storage::disk('local')->exists($scrapingDir)) {
                // Obtener todos los archivos de la carpeta scraping
                $files = Storage::disk('local')->files($scrapingDir);
                
                // Eliminar cada archivo
                foreach ($files as $file) {
                    Storage::disk('local')->delete($file);
                }
                
                $this->info("Archivos temporales eliminados: " . count($files) . " archivos");
                Log::info('Archivos de scraping eliminados', ['archivos' => count($files)]);
            } else {
                $this->info("Carpeta de scraping no existe, no hay archivos que eliminar");
            }
        } catch (\Exception $e) {
            $this->error("Error al limpiar archivos de scraping: " . $e->getMessage());
            Log::error('Error al limpiar archivos de scraping: ' . $e->getMessage());
        }
    }
}


