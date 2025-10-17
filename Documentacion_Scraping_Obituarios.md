# Documentación del Sistema de Scraping de Obituarios

## 📋 Índice
1. [Introducción](#introducción)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Proceso de Scraping](#proceso-de-scraping)
4. [Fuentes de Datos](#fuentes-de-datos)
5. [Manejo de Errores](#manejo-de-errores)
6. [Configuración y Ejecución](#configuración-y-ejecución)
7. [Estructura de Datos](#estructura-de-datos)
8. [Consideraciones Técnicas](#consideraciones-técnicas)

---

## 🎯 Introducción

El sistema de scraping de obituarios es una aplicación Laravel que extrae información de obituarios de tres cementerios diferentes en Chile y los almacena en una base de datos para su consulta y visualización web.

### Objetivos
- Automatizar la recolección de datos de obituarios
- Centralizar información de múltiples cementerios
- Proporcionar una interfaz web para consultar los datos
- Evitar duplicados y mantener integridad de datos

---

## 🏗️ Arquitectura del Sistema

### Componentes Principales

```
ScrapeObituaries.php (Comando Artisan)
├── scrapeParqueDelMar()    # Web scraping con DOMDocument
├── scrapeSendero()         # API REST
├── scrapeParqueDeAuco()    # Web scraping con DOMDocument
└── Métodos auxiliares      # Procesamiento y validación
```

### Flujo de Datos
```
Fuentes Web → Scraping → Validación → Base de Datos → Interfaz Web
```

---

## 🔄 Proceso de Scraping

### 1. Inicialización
```php
public function handle(): int
{
    $allResults = [];
    // Recopila datos de las tres fuentes
    $allResults = array_merge($allResults, $this->scrapeParqueDelMar());
    $allResults = array_merge($allResults, $this->scrapeSendero());
    $allResults = array_merge($allResults, $this->scrapeParqueDeAuco());
}
```

### 2. Procesamiento de Datos
```php
foreach ($allResults as $item) {
    // Validación de campos requeridos
    if (!isset($item['date'], $item['cemetery'], $item['deceased_name'])) {
        continue;
    }
    
    // Verificación de duplicados
    $existing = Obituary::whereDate('date', $date)
        ->where('cemetery', $cemetery)
        ->where('deceased_name', $deceasedName)
        ->first();
    
    // Inserción solo si no existe
    if (!$existing) {
        Obituary::create([...]);
    }
}
```

### 3. Finalización
- Reporte de registros procesados
- Logging de resultados
- Retorno de estado de éxito/error

---

## 🌐 Fuentes de Datos

### 1. Parque del Mar
**URL:** `https://www.parquedelmar.cl/webpdm/obituario.aspx`
**Método:** Web Scraping con DOMDocument y XPath
**Ubicación:** Concón, Valparaíso

#### Proceso de Extracción:
1. **Obtención del HTML:** Petición HTTP GET
2. **Parsing del DOM:** DOMDocument + XPath
3. **Extracción de fechas:** Regex para formato "7 octubre 2025"
4. **Normalización:** Conversión a formato ISO (YYYY-MM-DD)
5. **Extracción de datos:** Nombres, actividades, horarios
6. **Filtrado:** Solo actividades relacionadas con "parque del mar"

#### Selectores XPath:
```php
// Fechas
$dateNodes = $xpath->query('//div[contains(@id,"u909-4")]/p');

// Horas
$horas = $xpath->query('.//div[contains(@id,"u916")]/p', $group);

// Nombres
$nombres = $xpath->query('.//div[contains(@id,"u918")]/p', $group);

// Actividades
$actividades = $xpath->query('.//div[contains(@id,"u920")]/p', $group);
```

### 2. Parque del Sendero
**URL:** `https://sucursalvirtual-sendero-api.gux.cl/api/web/Obituario`
**Método:** API REST (JSON)
**Ubicación:** Varias ubicaciones

#### Proceso de Extracción:
1. **Petición API:** HTTP GET a endpoint JSON
2. **Procesamiento directo:** Sin parsing HTML
3. **Mapeo de campos:** Conversión de estructura API a formato estándar
4. **Extracción de parque:** Separación de ubicación física

#### Mapeo de Campos:
```php
return [
    'date' => $fechaHora[0],                    // FECHA_SERVICIO
    'cemetery' => 'Parque del Sendero',         // Fijo
    'deceased_name' => $item['NOMBRE_FALLECIDO'], // NOMBRE_FALLECIDO
    'park' => $parque,                          // PARQUE_FISICO (procesado)
];
```

### 3. Parque de Auco
**URL:** `https://parquedeauco.cl/obituario/`
**Método:** Web Scraping con DOMDocument
**Ubicación:** Rinconada, Valparaíso

#### Proceso de Extracción:
1. **Obtención del HTML:** Petición HTTP GET
2. **Parsing del DOM:** DOMDocument + XPath
3. **Extracción de tabla:** Filas de tabla HTML
4. **Normalización de fechas:** Formato "octubre 8, 2025" → "2025-10-08"
5. **Validación de datos:** Verificación de campos requeridos

#### Selectores XPath:
```php
// Filas de tabla de obituarios
$rows = $xpath->query('//div[@class="boxObituario"]//table//tr[td]');

// Columnas de datos
$cols = $xpath->query('.//td', $row);
```

---

## ⚠️ Manejo de Errores

### 1. Errores de Conexión
```php
if ($response->failed()) {
    Log::error("Error al acceder a $url", ['status' => $response->status()]);
    return [];
}
```

### 2. Errores de Parsing
```php
try {
    // Proceso de scraping
} catch (\Exception $e) {
    Log::error('Error en scrapeParqueDelMar: ' . $e->getMessage());
    return [];
}
```

### 3. Validación de Datos
```php
// Campos requeridos
if (!isset($item['date'], $item['cemetery'], $item['deceased_name'])) {
    continue;
}

// Validación de fechas
if (!$fecha) {
    Log::warning('No se pudo parsear la fecha', ['valor' => $fechaTexto]);
    continue;
}
```

### 4. Prevención de Duplicados
```php
$existing = Obituary::whereDate('date', $date)
    ->where('cemetery', $cemetery)
    ->where('deceased_name', $deceasedName)
    ->first();

if (!$existing) {
    // Solo crear si no existe
}
```

---

## ⚙️ Configuración y Ejecución

### Comando Artisan
```bash
php artisan scrape:obituaries
```

### Programación Automática
```php
// app/Console/Kernel.php
$schedule->command('scrape:obituaries')->dailyAt('07:00');
```

### Ejecución Manual
- Botón "Ejecutar ahora" en la interfaz web
- Comando directo desde terminal
- Programación con cron jobs

---

## 📊 Estructura de Datos

### Modelo Obituary
```php
protected $fillable = [
    'date',           // Fecha del obituario (YYYY-MM-DD)
    'cemetery',       // Nombre del cementerio
    'park',           // Ubicación específica del parque
    'deceased_name',  // Nombre del fallecido
];
```

### Restricciones de Base de Datos
```sql
UNIQUE KEY unique_obituary (date, cemetery, deceased_name)
```

### Ejemplo de Registro
```json
{
    "date": "2025-10-16",
    "cemetery": "Parque del Mar",
    "deceased_name": "Sra. MERCEDES DE ANDRADE CACHO",
    "park": "Concón, Valparaíso"
}
```

---

## 🔧 Consideraciones Técnicas

### 1. Headers HTTP
```php
Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
])
```

### 2. Manejo de Fechas
- **Entrada:** Múltiples formatos (español, ISO, etc.)
- **Normalización:** Conversión a formato ISO (YYYY-MM-DD)
- **Almacenamiento:** Campo `date` en base de datos
- **Comparación:** Uso de `whereDate()` para consultas

### 3. Procesamiento de Texto
- **Encoding:** UTF-8 para caracteres especiales
- **Limpieza:** `trim()` y normalización de espacios
- **Regex:** Patrones para extraer fechas y nombres
- **Filtrado:** Eliminación de datos irrelevantes

### 4. Logging
```php
Log::info('Parque del Mar: registros extraídos', ['total' => count($results)]);
Log::error('Error al acceder a Sendero API', ['status' => $response->status()]);
Log::warning('No se pudo parsear la fecha', ['valor' => $fechaTexto]);
```

### 5. Rendimiento
- **Procesamiento secuencial:** Una fuente a la vez
- **Validación temprana:** Filtrado antes de procesamiento
- **Deduplicación:** Verificación antes de inserción
- **Manejo de memoria:** Procesamiento por lotes pequeños

---

## 📈 Métricas y Monitoreo

### Logs Generados
- Número de registros extraídos por fuente
- Errores de conexión y parsing
- Advertencias de datos inválidos
- Tiempo de ejecución del comando

### Indicadores de Éxito
- Comando retorna `self::SUCCESS`
- Número de registros procesados > 0
- Sin errores críticos en logs
- Datos válidos en base de datos

---

## 🚀 Mejoras Futuras

### Posibles Optimizaciones
1. **Procesamiento paralelo:** Scraping simultáneo de múltiples fuentes
2. **Cache de respuestas:** Evitar peticiones repetidas
3. **Retry automático:** Reintentos en caso de fallos
4. **Monitoreo en tiempo real:** Dashboard de estado del sistema
5. **Notificaciones:** Alertas por errores o cambios significativos

### Mantenimiento
- **Actualización de selectores:** Adaptación a cambios en sitios web
- **Validación de datos:** Mejora de patrones de extracción
- **Optimización de consultas:** Mejora del rendimiento de base de datos
- **Documentación:** Actualización de esta documentación

---

## 📞 Soporte

Para consultas técnicas o problemas con el sistema de scraping, revisar:
1. Logs de Laravel en `storage/logs/`
2. Documentación de Laravel
3. Código fuente del comando `ScrapeObituaries.php`
4. Esta documentación

---

*Documento generado automáticamente - Sistema de Scraping de Obituarios v1.0*
