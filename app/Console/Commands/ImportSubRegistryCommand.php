<?php

namespace App\Console\Commands;

use App\Domain\Import\ImportSubRegistry;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImportSubRegistryCommand extends Command
{
    protected $signature = 'import:sub-registry
                            {--file= : Path to sub_registry JSON (default: database/data/sub_registry_2026.json)}
                            {--user= : Email of user for created_by / movement processed_by}
                            {--dry-run : Validate and report without writing}';

    protected $description = 'One-shot import of 2026 CON sub-sale registry rows (preserves printed refs, bumps CON sequence)';

    public function handle(ImportSubRegistry $importer): int
    {
        $path = $this->option('file') ?: database_path('data/sub_registry_2026.json');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $user = $this->resolveUser();
            $result = $importer->handle($path, $user, $dryRun);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['colliding_refs'] !== []) {
            if ($result['already_imported']) {
                $this->warn('Already imported: all '.count($result['colliding_refs']).' reference numbers already exist. No changes made.');
            } else {
                $this->error('Aborting: '.count($result['colliding_refs']).' reference number(s) already exist. No changes made.');
                foreach (array_slice($result['colliding_refs'], 0, 10) as $ref) {
                    $this->line('  - '.$ref);
                }
                if (count($result['colliding_refs']) > 10) {
                    $this->line('  ... +'.(count($result['colliding_refs']) - 10).' more');
                }
            }

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[dry-run] ' : '').'Sub-registry import');
        $this->table(
            ['Metric', 'Value'],
            [
                ['File', $path],
                ['User', $user->email],
                ['Rows', (string) $result['total']],
                ['Imported', (string) $result['imported']],
                ['Max running no.', (string) $result['max_number']],
                ['CON next_value', $result['next_sequence'] === null ? 'n/a' : (string) $result['next_sequence']],
                ['Case types', json_encode($result['case_types'])],
                ['Unmatched spa_pic', $result['unmatched_pics'] === [] ? '(none)' : implode(', ', $result['unmatched_pics'])],
            ]
        );

        if ($dryRun) {
            $this->comment('Dry run only — no database writes.');
        } else {
            $this->info("Imported {$result['imported']} file(s). CON sequence next_value={$result['next_sequence']}.");
        }

        return self::SUCCESS;
    }

    private function resolveUser(): User
    {
        $email = $this->option('user');
        if (is_string($email) && trim($email) !== '') {
            $user = User::query()->where('email', trim($email))->first();
            if (! $user) {
                throw new RuntimeException("User not found for email: {$email}");
            }

            return $user;
        }

        $user = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('status', User::STATUS_APPROVED)
            ->orderBy('id')
            ->first();

        if (! $user) {
            $user = User::query()->orderBy('id')->first();
        }

        if (! $user) {
            throw new RuntimeException('No user found for created_by / processed_by. Pass --user=email.');
        }

        return $user;
    }
}
