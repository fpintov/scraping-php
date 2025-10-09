<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Obituarios - {{ config('app.name', 'Laravel') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <style>
            .container{max-width:980px;margin:0 auto;padding:20px}
            .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
            .card{background:#fff;border:1px solid #e5e7eb;border-radius:8px}
            .table{width:100%;border-collapse:collapse}
            .table th,.table td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
            .muted{color:#6b7280}
            .footer{margin-top:24px;color:#6b7280;font-size:12px}
            .topnav{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:24px}
            .btn{display:inline-block;padding:8px 14px;border:1px solid #1f2937;border-radius:6px;text-decoration:none;color:#1f2937;background:#fff}
            .btn:hover{background:#111827;color:#fff;border-color:#111827}
            .btn-active{background:#111827;color:#fff;border-color:#111827}
            .brand{font-size:28px;font-weight:700}
            .btn-primary{background:#111827;color:#fff;border-color:#111827}
            .btn-primary:hover{opacity:.9}
            .loader{display:none;align-items:center;gap:8px}
            .loader.show{display:inline-flex}
            .spinner{width:14px;height:14px;border:2px solid #111827;border-top-color:transparent;border-radius:50%;animation:spin .8s linear infinite}
            @keyframes spin{to{transform:rotate(360deg)}}
        </style>
    </head>
    <body class="bg-[#FDFDFC] text-[#1b1b18]">
        <div class="container">
            <div class="topnav">
                <div class="brand">Obituarios</div>
                <div>
                    <a href="/" class="btn btn-active">Obituarios</a>
                    <a href="/implementation" class="btn">Implementación</a>
                </div>
            </div>
            <div class="header">
                <h1 class="text-2xl font-semibold">Obituarios</h1>
                <form method="POST" action="{{ route('obituaries.scrape_now') }}" onsubmit="document.getElementById('loader').classList.add('show'); this.querySelector('button').disabled=true;">
                    @csrf
                    <div class="loader" id="loader"><span class="spinner"></span><span class="muted">Trabajando...</span></div>
                    <button type="submit" class="btn btn-primary">Ejecutar ahora</button>
                </form>
                <form method="GET" action="/">
                    <label for="date" class="muted">Fecha:</label>
                    <select id="date" name="date" onchange="this.form.submit()">
                        @if(empty($availableDates))
                            <option value="{{ $selectedDate }}">{{ $selectedDate }}</option>
                        @else
                            @foreach($availableDates as $date)
                                <option value="{{ $date }}" @selected($date===$selectedDate)>{{ $date }}</option>
                            @endforeach
                        @endif
                    </select>
                </form>
            </div>

            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cementerio</th>
                            <th>Parque</th>
                            <th>Nombre fallecido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($obituaries as $o)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($o->date)->toDateString() }}</td>
                                <td>{{ $o->cemetery }}</td>
                                <td>{{ $o->park }}</td>
                                <td>{{ $o->deceased_name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">No hay resultados para la fecha seleccionada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="footer">
                Última actualización: {{ now()->format('Y-m-d H:i') }}
            </div>
        </div>
    </body>
    </html>


