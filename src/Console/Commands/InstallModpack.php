<?php

namespace Wyvern\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;
use Wyvern\Content\ContentLibrary;
use Wyvern\Content\Modpack\ModpackInstaller;
use Wyvern\Content\ServerProfile;
use Wyvern\Minecraft\InstallRecords;

class InstallModpack extends Command
{
    protected $signature = 'wyvern:modpack:install
                            {server : Server id or uuid}
                            {project : Modrinth project slug}
                            {--pack-version= : A specific version id, otherwise the newest that fits}
                            {--dry-run : Resolve and report, install nothing}';

    protected $description = 'Install a Modrinth modpack onto a server';

    public function handle(ContentLibrary $library, ModpackInstaller $installer, InstallRecords $records): int
    {
        $server = Server::query()
            ->where('id', $this->argument('server'))
            ->orWhere('uuid', $this->argument('server'))
            ->first();

        if (!$server) {
            $this->error('No such server.');

            return self::FAILURE;
        }

        $profile = ServerProfile::of($server);
        $files = $library->files('modrinth', $this->argument('project'), $profile->loader, $profile->gameVersion($records));

        if ($files === []) {
            $this->error('Modrinth has no build of that pack for this server.');

            return self::FAILURE;
        }

        $file = $this->option('pack-version')
            ? collect($files)->firstWhere('id', $this->option('pack-version'))
            : $files[0];

        if (!$file?->isDownloadable()) {
            $this->error('That version cannot be downloaded.');

            return self::FAILURE;
        }

        $this->line("  server:  {$server->name} (" . ($profile->loader?->label() ?? 'unknown loader') . ')');
        $this->line("  pack:    {$file->filename}  {$file->sizeForHumans()}");
        $this->line('  for:     ' . implode(', ', $file->gameVersions) . ' · ' . implode(', ', $file->loaders));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->newLine();

        try {
            $index = $installer->install($server, $file->url, function (string $message): void {
                $this->line("  {$message}");
            });
        } catch (\Throwable $e) {
            $this->error('  ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("  {$index->name} {$index->versionId} installed.");
        $this->line('  it expects Minecraft ' . ($index->minecraftVersion() ?? '?') . ' on ' . ($index->loader() ?? '?'));

        return self::SUCCESS;
    }
}
