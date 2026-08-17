<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:consolidate-duplicate-teams')]
#[Description('Merge team rows that pair the same two players into one')]
class ConsolidateDuplicateTeamsCommand extends Command
{
    public function handle(): int
    {
        $merged = Team::consolidateAllDuplicates();

        $this->info("Merged {$merged} duplicate team row(s).");

        $remaining = Team::duplicatePairs();

        if ($remaining->isNotEmpty()) {
            $this->warn("{$remaining->count()} pair(s) could not be merged automatically - both teams are already registered for the same tournament. Resolve manually:");

            foreach ($remaining as $group) {
                $ids = $group->pluck('id')->implode(', ');
                $this->line("  {$group->first()} (ids: {$ids})");
            }
        }

        return self::SUCCESS;
    }
}
