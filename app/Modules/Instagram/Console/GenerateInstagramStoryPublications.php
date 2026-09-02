<?php

namespace App\Modules\Instagram\Console;

use App\Modules\Instagram\Actions\GenerateDueInstagramStoryPublications;
use Illuminate\Console\Command;

final class GenerateInstagramStoryPublications extends Command
{
    protected $signature = 'instagram:generate-publications';

    protected $description = 'Crear publicaciones pendientes e idempotentes de historias de Instagram que estén programadas';

    public function handle(GenerateDueInstagramStoryPublications $generate): int
    {
        $this->info($generate->execute().' publicación(es) creada(s).');

        return self::SUCCESS;
    }
}
