<?php

namespace App\Console\Commands;

use App\Models\Obituary;
use Illuminate\Console\Command;

class RemoveExcludedParks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'obituaries:remove-excluded-parks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina registros de Parque del Sendero con parques excluidos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando eliminación de registros con parques excluidos...');
        
        // Parques a excluir (mismos que en el scraping)
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
        
        // Obtener registros de Parque del Sendero con parques excluidos
        $obituaries = Obituary::where('cemetery', 'Parque del Sendero')
            ->where(function($query) use ($parquesExcluidos) {
                foreach ($parquesExcluidos as $parque) {
                    $query->orWhere('park', 'like', '%' . $parque . '%');
                }
            })
            ->get();
        
        $this->info("Encontrados {$obituaries->count()} registros con parques excluidos");
        
        if ($obituaries->count() === 0) {
            $this->info('No hay registros para eliminar.');
            return self::SUCCESS;
        }
        
        // Mostrar algunos ejemplos antes de eliminar
        $this->info('Ejemplos de registros a eliminar:');
        $obituaries->take(5)->each(function($obituary) {
            $this->line("- {$obituary->deceased_name} ({$obituary->park})");
        });
        
        if ($obituaries->count() > 5) {
            $this->line("... y " . ($obituaries->count() - 5) . " más");
        }
        
        // Confirmar eliminación
        if ($this->confirm('¿Deseas proceder con la eliminación?')) {
            $deleted = 0;
            foreach ($obituaries as $obituary) {
                $this->line("Eliminando: {$obituary->deceased_name} ({$obituary->park})");
                $obituary->delete();
                $deleted++;
            }
            
            $this->info("Eliminación completada. {$deleted} registros eliminados.");
        } else {
            $this->info('Operación cancelada.');
        }
        
        return self::SUCCESS;
    }
}
