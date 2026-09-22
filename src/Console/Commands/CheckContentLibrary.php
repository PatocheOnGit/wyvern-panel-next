<?php

namespace Wyvern\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;
use Wyvern\Content\ContentInstaller;
use Wyvern\Content\ContentLibrary;
use Wyvern\Content\ContentType;
use Wyvern\Content\ServerProfile;
use Wyvern\Minecraft\InstallRecords;

/**
 * Searches the content libraries as a given server would, and optionally installs the
 * top result onto it. This is how the source implementations are verified: against the
 * live APIs and a live node, not fixtures.
 */
class CheckContentLibrary extends Command
{
    protected $signature = 'wyvern:content:check
                            {server : Server id or uuid}
                            {--query= : Search term}
                            {--type=mod : plugin, mod or modpack}
                            {--install : Install the first result}';

    protected $description = 'Search mods, plugins and modpacks for a server, and optionally install one';

    public function handle(ContentLibrary $library, ContentInstaller $installer, InstallRecords $records): int
    {
        $server = Server::query()
            ->with('egg', 'variables')
            ->where('id', $this->argument('server'))
            ->orWhere('uuid', $this->argument('server'))
            ->first();

        if (!$server) {
            $this->error('No such server.');

            return self::FAILURE;
        }

        $profile = ServerProfile::of($server);
        $type = ContentType::from($this->option('type'));

        $this->line("  server:  {$server->name}");
        $this->line('  loader:  ' . ($profile->loader?->label() ?? 'unknown'));
        $this->line('  version: ' . ($profile->gameVersion($records) ?? 'any'));
        $this->line('  accepts: ' . (collect($profile->installableTypes())->map(fn ($t) => $t->value)->implode(', ') ?: 'nothing'));
        $this->newLine();

        $sources = $library->available();
        $this->line('  sources: ' . collect($sources)->map(fn ($s) => $s->label())->implode(', '));
        $this->newLine();

        foreach ($sources as $source) {
            $results = $source->search(
                (string) $this->option('query'),
                $type,
                $profile->loader,
                $profile->gameVersion($records),
                6,
            );

            $this->line("  ── {$source->label()}: " . count($results) . ' result(s)');

            foreach ($results as $project) {
                $this->line(sprintf(
                    '     %-28s %8s dl  %s',
                    mb_strimwidth($project->title, 0, 28, '…'),
                    $project->downloadsForHumans(),
                    mb_strimwidth($project->summary, 0, 60, '…'),
                ));
            }

            if ($results === []) {
                continue;
            }

            $first = $results[0];
            $files = $source->files($first->id, $profile->loader, $profile->gameVersion($records));
            $this->line('     files for ' . $first->title . ': ' . count($files));

            foreach (array_slice($files, 0, 3) as $file) {
                $this->line(sprintf(
                    '       %-42s %-9s %-26s %s',
                    mb_strimwidth($file->filename, 0, 42, '…'),
                    $file->sizeForHumans() ?? '?',
                    // Shown because a file for the wrong Minecraft version is the
                    // easiest mistake to make here, and the hardest to notice after.
                    mb_strimwidth(implode(', ', $file->gameVersions), 0, 26, '…'),
                    $file->isDownloadable() ? '' : 'distribution not allowed',
                ));
            }

            if ($profile->gameVersion($records) === null) {
                $this->warn('     this server does not pin a Minecraft version, so files were not narrowed to one');
            }

            if ($this->option('install') && $files !== []) {
                try {
                    $path = $installer->install($server, $files[0], $type);
                    $this->info("     installed → {$path}");
                } catch (\Throwable $e) {
                    $this->error('     install failed: ' . $e->getMessage());
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
