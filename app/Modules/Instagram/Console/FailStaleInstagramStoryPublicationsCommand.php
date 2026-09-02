<?php

namespace App\Modules\Instagram\Console;

use App\Modules\Instagram\Actions\FailStaleInstagramStoryPublications;
use Illuminate\Console\Command;

final class FailStaleInstagramStoryPublicationsCommand extends Command
{
    protected $signature = 'instagram:fail-stale-publications';

    protected $description = 'Marcar como fallidas para revisión manual las publicaciones ambiguas de historias de Instagram';

    public function handle(FailStaleInstagramStoryPublications $fail): int
    {
        $this->info($fail->execute().' stale publication(s) marked for review.');

        return self::SUCCESS;
    }
}
