<?php

namespace App\Console\Commands;

use App\Domain\Import\ImportMasterRegistry;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImportMasterRegistryCommand extends Command
{
    protected $signature = 'import:master-registry
                            {--file= : Path to master registry JSON (default: database/data/master_registry_2026.json)}
                            {--user= : Email of user for created_by / movement processed_by}
                            {--dry-run : Validate and report without writing}';

    protected $description = 'One-shot import of 2026 master loan/project registry (preserves printed refs, creates bank/project categories)';

    public function handle(ImportMasterRegistry $importer): int
    {
        $path = $this->option('file') ?: database_path('data/master_registry_2026.json');
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

        $categoryLines = [];
        foreach ($result['categories'] as $cat) {
            $flag = $cat['created'] ? 'create' : 'reuse';
            $categoryLines[] = "{$cat['original']} -> {$cat['code']} ({$flag})";
        }

        $seqLines = [];
        foreach ($result['sequences'] as $code => $next) {
            $seqLines[] = "{$code}={$next}";
        }

        $this->info(($dryRun ? '[dry-run] ' : '').'Master-registry import');
        $this->table(
            ['Metric', 'Value'],
            [
                ['File', $path],
                ['User', $user->email],
                ['Rows', (string) $result['total']],
                ['Imported', (string) $result['imported']],
                ['Registry types', json_encode($result['registry_types'])],
                ['Categories', $categoryLines === [] ? '(none)' : implode('; ', $categoryLines)],
                ['Sequences next', $seqLines === [] ? '(none)' : implode(', ', $seqLines)],
                ['Links resolved', (string) $result['linked']],
                ['Links unresolved', $result['unresolved_links'] === [] ? '(none)' : implode(', ', array_slice($result['unresolved_links'], 0, 20))],
                ['Unmatched PIC', $result['unmatched_pics'] === [] ? '(none)' : implode(', ', $result['unmatched_pics'])],
            ]
        );

        if ($dryRun) {
            $this->comment('Dry run only — no database writes.');
        } else {
            $this->info("Imported {$result['imported']} file(s). Printed reference numbers were preserved.");
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
