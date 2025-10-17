<?php

namespace App\Console\Commands;

use App\Models\Obituary;
use Illuminate\Console\Command;

class CleanObituaryNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'obituaries:clean-names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia los prefijos Sr., Sra., Srta. de los nombres de fallecidos existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpieza de prefijos en nombres de fallecidos...');
        
        // Obtener todos los obituarios que tienen prefijos
        $obituaries = Obituary::where('deceased_name', 'like', 'Sr.%')
            ->orWhere('deceased_name', 'like', 'Sra.%')
            ->orWhere('deceased_name', 'like', 'Srta.%')
            ->get();
        
        $this->info("Encontrados {$obituaries->count()} registros con prefijos");
        
        $updated = 0;
        $duplicates = 0;
        
        foreach ($obituaries as $obituary) {
            $originalName = $obituary->deceased_name;
            
            // Quitar prefijos "Sr.", "Sra." y "Srta." del nombre
            $cleanName = preg_replace('/^(Sr\.|Sra\.|Srta\.)\s*/i', '', $originalName);
            
            // Solo actualizar si el nombre cambió
            if ($cleanName !== $originalName) {
                // Verificar si ya existe un registro con el nombre limpio
                $existingRecord = Obituary::where('date', $obituary->date)
                    ->where('cemetery', $obituary->cemetery)
                    ->where('deceased_name', $cleanName)
                    ->where('id', '!=', $obituary->id)
                    ->first();
                
                if ($existingRecord) {
                    // Si existe un duplicado, eliminar el registro con prefijo
                    $this->line("Duplicado encontrado: '{$originalName}' → Eliminando registro con prefijo");
                    $obituary->delete();
                    $duplicates++;
                } else {
                    // Si no hay duplicado, actualizar el nombre
                    $obituary->update(['deceased_name' => $cleanName]);
                    $updated++;
                    $this->line("Actualizado: '{$originalName}' → '{$cleanName}'");
                }
            }
        }
        
        $this->info("Limpieza completada. {$updated} registros actualizados, {$duplicates} duplicados eliminados.");
        
        return self::SUCCESS;
    }
}
