<?php

namespace App\Console\Commands;

use App\Models\Obituary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScrapeObituaries extends Command
{
    protected $signature = 'scrape:obituaries';
    protected $description = 'Scrapea obituarios de cementerios y guarda en BD';

    public function handle(): int
    {
        $allResults = [];
        $allResults = array_merge($allResults, $this->scrapeParqueDelMar());
        $allResults = array_merge($allResults, $this->scrapeSendero());
        $allResults = array_merge($allResults, $this->scrapeParqueDeAuco());

        $numInserted = 0;
        foreach ($allResults as $item) {
            if (!isset($item['date'], $item['cemetery'], $item['deceased_name'])) {
                // salta ítems incompletos
                continue;
            }
            
            $date = Carbon::parse($item['date'])->toDateString();
            $cemetery = $item['cemetery'];
            $deceasedName = $item['deceased_name'];
            $park = $item['park'] ?? null;
            
            // Verificar si ya existe el registro
            $existing = Obituary::whereDate('date', $date)
                ->where('cemetery', $cemetery)
                ->where('deceased_name', $deceasedName)
                ->first();
            
            if (!$existing) {
                // Solo crear si no existe
                Obituary::create([
                    'date' => $date,
                    'cemetery' => $cemetery,
                    'deceased_name' => $deceasedName,
                    'park' => $park,
                ]);
                $numInserted++;
            }
        }

        $this->info("Scraping completado. Registros procesados: {$numInserted}");
        return self::SUCCESS;
    }

    private function scrapeParqueDelMar(): array
    {
        $url = 'https://www.parquedelmar.cl/webpdm/obituario.aspx';
        $cemetery = 'Parque del Mar';
        $results = [];

        try {
            $response = Http::get($url);
            if ($response->failed()) {
                Log::error("Error al acceder a $url", ['status' => $response->status()]);
                return [];
            }

            $html = $response->body();
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);

            // Extraer bloques de fecha (ej: "Con Cón, 7 octubre 2025")
            $dateNodes = $xpath->query('//div[contains(@id,"u909-4")]/p');

            foreach ($dateNodes as $dateNode) {
                $dateText = trim($dateNode->textContent);
                if (!preg_match('/(\d{1,2})\s+([a-záéíóú]+)\s+(\d{4})/iu', $dateText, $m)) {
                    continue;
                }

                // Normalizar formato "7 octubre 2025" → "2025-10-07"
                $meses = [
                    'enero'=>'01','febrero'=>'02','marzo'=>'03','abril'=>'04','mayo'=>'05','junio'=>'06',
                    'julio'=>'07','agosto'=>'08','septiembre'=>'09','octubre'=>'10','noviembre'=>'11','diciembre'=>'12'
                ];
                $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mes = strtolower($m[2]);
                $anio = $m[3];
                $fecha = "$anio-{$meses[$mes]}-$dia";

                // Buscar los tres contenedores <div> inmediatamente después del bloque de fecha
                $group = $dateNode->parentNode->nextSibling;
                while ($group && ($group->nodeType !== XML_ELEMENT_NODE || strpos($group->getAttribute('id'), 'pu916-10') === false)) {
                    $group = $group->nextSibling;
                }

                if (!$group) continue;

                $horas = $xpath->query('.//div[contains(@id,"u916")]/p', $group);
                $nombres = $xpath->query('.//div[contains(@id,"u918")]/p', $group);
                $actividades = $xpath->query('.//div[contains(@id,"u920")]/p', $group);

                $max = min($horas->length, $nombres->length, $actividades->length);
                for ($i = 0; $i < $max; $i++) {
                    $nombre = trim($nombres->item($i)->textContent);
                    $actividad = trim($actividades->item($i)->textContent);

                    if ($nombre === '' || stripos($actividad, 'parque del mar') === false) {
                        continue;
                    }

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


    private function scrapeSendero()
    {
        try {
            $url = 'https://sucursalvirtual-sendero-api.gux.cl/api/web/Obituario';
            $response = Http::get($url);
    
            if ($response->failed()) {
                Log::error('Error al acceder a Sendero API', ['status' => $response->status()]);
                return collect();
            }
    
            $data = $response->json();

            return collect($data)->map(function ($item) {
                $fechaHora = explode(' ', $item['FECHA_SERVICIO'] ?? '');
                $fecha = $fechaHora[0] ?? null;
                $parque = isset($item['PARQUE_FISICO'])
                    ? (explode(' - ', $item['PARQUE_FISICO'])[1] ?? $item['PARQUE_FISICO'])
                    : null;

                return [
                    'date' => $fecha,
                    'cemetery' => 'Parque del Sendero',
                    'deceased_name' => $item['NOMBRE_FALLECIDO'] ?? null,
                    'park' => $parque,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Error en scrapeSendero: ' . $e->getMessage());
            return [];
        }
    }

    private function scrapeParqueDeAuco()
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
    
                $fecha = null;
                if (preg_match('/([a-záéíóú]+)\s+(\d{1,2}),\s*(\d{4})/iu', $fechaTexto, $m)) {
                    $mes = strtolower($m[1]);
                    if (isset($meses[$mes])) {
                        $dia = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                        $anio = $m[3];
                        $fecha = "$anio-{$meses[$mes]}-$dia";
                    }
                }
    
                // Si no logra parsear, ignora la fila
                if (!$fecha) {
                    Log::warning('No se pudo parsear la fecha en Parque de Auco', ['valor' => $fechaTexto]);
                    continue;
                }
    
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
}


