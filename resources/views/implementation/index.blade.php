<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Implementación - {{ config('app.name', 'Laravel') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        .container{max-width:960px;margin:0 auto;padding:20px}
        .topnav{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:24px}
        .btn{display:inline-block;padding:8px 14px;border:1px solid #1f2937;border-radius:6px;text-decoration:none;color:#1f2937;background:#fff}
        .btn:hover{background:#111827;color:#fff;border-color:#111827}
        .btn-active{background:#111827;color:#fff;border-color:#111827}
        .brand{font-size:28px;font-weight:700}
        h1{font-size:26px;margin:10px 0}
        h2{font-size:22px;margin:18px 0 8px}
        h3{font-size:18px;margin:12px 0 6px}
        code{background:#e5e7eb;color:#111827;padding:2px 6px;border-radius:4px}
        pre{background:#f3f4f6;color:#111827;padding:14px;border-radius:6px;overflow:auto;border:1px solid #e5e7eb}
        pre, code, pre code{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
        .muted{color:#6b7280}
        ul{margin:0 0 12px 20px}
        li{margin:6px 0}
        .index a{color:#1f2937;text-decoration:none}
        .index a:hover{text-decoration:underline}
        .highlight{background:#fef3c7;padding:8px 12px;border-radius:8px;margin:12px 0}
    </style>
</head>
<body class="bg-[#FDFDFC] text-[#1b1b18]">
<div class="container">
    <div class="topnav">
        <div class="brand">Obituarios</div>
        <div>
            <a href="/" class="btn">Obituarios</a>
            <a href="/implementation" class="btn btn-active">Implementación</a>
        </div>
    </div>

    <h1>Implementación del sistema de scraping y visualización</h1>
    <p class="muted">Este documento combina la descripción general del funcionamiento del sistema con la guía técnica detallada para desarrolladores.</p>

    <h2>Índice</h2>
    <ul class="index">
        <li><a href="#explicacion-general">1. Explicación general del sistema</a></li>
        <li><a href="#dependencias">2. Dependencias y entorno</a></li>
        <li><a href="#modelo">3. Modelo y migración</a></li>
        <li><a href="#comando">4. Comando de scraping</a></li>
        <li><a href="#scheduler">5. Scheduler (cron)</a></li>
        <li><a href="#interfaz">6. Interfaz web</a></li>
        <li><a href="#front">7. Dependencias front (opcional)</a></li>
        <li><a href="#ajustes">8. Ajustes y mejoras futuras</a></li>
        <li><a href="#estructura">9. Estructura de archivos</a></li>
        <li><a href="#troubleshooting">10. Troubleshooting</a></li>
    </ul>

    <h2 id="explicacion-general">1) Explicación general del sistema</h2>

    <p>El sistema de <strong>scraping de obituarios</strong> en PHP/Laravel tiene como propósito obtener automáticamente los nombres de personas fallecidas publicados en los sitios de los principales cementerios y mostrarlos en una interfaz web.</p>

    <div class="highlight">
        <strong>Flujo completo:</strong>  
        <br>1. Se ejecuta el comando <code>scrape:obituaries</code> (manualmente o vía cron).  
        <br>2. El sistema descarga y analiza el HTML de cada sitio.  
        <br>3. Se guardan copias del HTML (snapshots) y los datos extraídos en la base de datos.  
        <br>4. La interfaz web muestra los registros filtrados por fecha.
    </div>

    <p>El proceso está completamente automatizado mediante el <strong>scheduler de Laravel</strong>, y puede ejecutarse diariamente a una hora determinada (por defecto, 07:00 AM).</p>

    <p>El campo <code>park</code> sirve para identificar internamente a qué parque específico pertenece un registro (por ejemplo, dentro de “Parque del Recuerdo” puede haber varios parques físicos).</p>

    <h2 id="dependencias">2) Dependencias y entorno</h2>
    <ul>
        <li>Laravel 10+</li>
        <li>PHP ≥ 8.2 con extensiones <code>mbstring</code>, <code>dom</code>, <code>curl</code>, <code>xml</code></li>
        <li>Composer</li>
        <li>Node.js 20.19+ (para estilos con Vite/Tailwind, opcional)</li>
    </ul>

    <p>Dependencias de scraping:</p>
    <pre><code>composer require symfony/dom-crawler symfony/css-selector guzzlehttp/guzzle</code></pre>

    <h2 id="modelo">3) Modelo y migración</h2>

    <p>El modelo <code>Obituary</code> representa los registros almacenados en la base de datos. Contiene los campos:</p>
    <ul>
        <li><code>date</code></li>
        <li><code>cemetery</code></li>
        <li><code>deceased_name</code></li>
        <li><code>park</code></li>
    </ul>

    <pre><code class="language-php">class Obituary extends Model
{
    protected $fillable = ['date', 'cemetery', 'deceased_name', 'park'];
}</code></pre>

    <p>Migración asociada:</p>
    <pre><code class="language-php">Schema::create('obituaries', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('cemetery');
    $table->string('deceased_name');
    $table->string('park')->nullable();
    $table->timestamps();
    $table->unique(['date', 'cemetery', 'deceased_name', 'park']);
});</code></pre>

    <pre><code>php artisan migrate --force</code></pre>

    <h2 id="comando">4) Comando de scraping</h2>

    <p>El comando <code>ScrapeObituaries.php</code> ejecuta el proceso de scraping. Se ubica en <code>app/Console/Commands/</code> y descarga, analiza y almacena los obituarios.</p>

    <pre><code class="language-php">public function handle()
{
    $sites = ['Parque del Recuerdo', 'Parque del Mar', 'Nuestros Parques'];

    foreach ($sites as $site) {
        $this->info("Scrapeando: {$site}");
        $results = $this->scraperService->scrape($site);
        foreach ($results as $r) {
            Obituary::updateOrCreate([
                'date' => $r['date'],
                'cemetery' => $r['cemetery'],
                'deceased_name' => $r['deceased_name'],
                'park' => $r['park'] ?? null,
            ]);
        }
    }

    $this->info('Scraping completado.');
}</code></pre>

    <p>Ejecutar manualmente:</p>
    <pre><code>php artisan scrape:obituaries</code></pre>

    <h2 id="scheduler">5) Scheduler (cron)</h2>

    <p>Laravel ejecuta automáticamente el comando cada día mediante el scheduler definido en <code>Kernel.php</code>:</p>

    <pre><code class="language-php">protected function schedule(Schedule $schedule)
{
    $schedule->command('scrape:obituaries')->dailyAt('07:00');
}</code></pre>

    <p>En el servidor, el cron del sistema debe ejecutar el scheduler cada minuto:</p>
    <pre><code>* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1</code></pre>

    <h2 id="interfaz">6) Interfaz web</h2>

    <p>La interfaz permite visualizar los datos almacenados:</p>
    <ul>
        <li>Controlador: <code>app/Http/Controllers/ObituaryController.php</code></li>
        <li>Vista: <code>resources/views/obituaries/index.blade.php</code></li>
    </ul>

    <p>El controlador carga los registros por fecha:</p>
    <pre><code class="language-php">public function index(Request $request)
{
    $dates = Obituary::select('date')->distinct()->orderByDesc('date')->pluck('date');
    $selectedDate = $request->input('date', $dates->first());
    $obituaries = Obituary::where('date', $selectedDate)->orderBy('cemetery')->get();
    return view('obituaries.index', compact('dates', 'selectedDate', 'obituaries'));
}</code></pre>

    <p>Rutas asociadas:</p>
    <pre><code class="language-php">Route::get('/', [ObituaryController::class, 'index'])->name('obituaries.index');
Route::get('/implementation', fn() => view('implementation.index'));</code></pre>

    <h2 id="front">7) Dependencias front (opcional)</h2>
    <p>Para aplicar estilos con Tailwind y Vite:</p>
    <pre><code>npm ci
npm run dev   # o npm run build</code></pre>

    <h2 id="ajustes">8) Ajustes y mejoras futuras</h2>
    <ul>
        <li>Delimitar el scraping a secciones principales (evitar header/footer).</li>
        <li>Definir selectores específicos por sitio.</li>
        <li>Ignorar enlaces no relevantes (“Pago Rápido”, “Mi Sendero”).</li>
        <li>Registrar logs por sitio en <code>storage/logs/scraping.log</code>.</li>
    </ul>

    <h2 id="estructura">9) Estructura de archivos</h2>
    <pre><code>app/
  Console/
    Commands/ScrapeObituaries.php
    Kernel.php
  Http/Controllers/ObituaryController.php
  Models/Obituary.php
resources/views/
  obituaries/index.blade.php
  implementation/index.blade.php
routes/web.php
storage/app/private/scraping/   # snapshots HTML
database/migrations/2025_10_07_000000_create_obituaries_table.php</code></pre>

    <h2 id="troubleshooting">10) Troubleshooting</h2>
    <ul>
        <li><strong>Vite manifest not found:</strong> No es necesario Vite, la vista ya lo valida.</li>
        <li><strong>Sin registros:</strong> Ejecutar <code>php artisan scrape:obituaries</code> y verificar la tabla <code>obituaries</code>.</li>
        <li><strong>Error HTTP:</strong> Revisar certificados o permisos de red.</li>
        <li><strong>Node/Vite:</strong> Usar Node 20.19+ y reinstalar dependencias con <code>npm ci</code>.</li>
    </ul>

    <p class="muted">📘 Última actualización: 7 de octubre de 2025</p>
</div>
</body>
</html>
