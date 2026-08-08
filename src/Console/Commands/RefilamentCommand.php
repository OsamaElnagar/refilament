<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;

class RefilamentCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'refilament:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package refilament.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Refilament placeholder command executed.');

        return self::SUCCESS;
    }
}
