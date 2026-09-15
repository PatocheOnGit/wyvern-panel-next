<?php

namespace Wyvern\Content;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Puts a file from a library onto a server.
 *
 * The download happens on the node, not here: Wings' files/pull fetches the url
 * directly, so a 60 MiB mod never travels through the panel. That is also why install
 * is fast enough to do from a click.
 */
class ContentInstaller
{
    public function __construct(private readonly DaemonFileRepository $files) {}

    /**
     * @throws RuntimeException when the file cannot be installed on this server
     * @throws ConnectionException when the node cannot be reached
     */
    public function install(Server $server, ContentFile $file, ContentType $type): string
    {
        if (!$file->isDownloadable()) {
            throw new RuntimeException(trans('wyvern.content.errors.not_distributable'));
        }

        $profile = ServerProfile::of($server);

        if (!$profile->isKnown()) {
            throw new RuntimeException(trans('wyvern.content.errors.unknown_loader'));
        }

        if (!in_array($type, $profile->installableTypes(), true)) {
            throw new RuntimeException(trans('wyvern.content.errors.wrong_type', [
                'loader' => $profile->loader->label(),
            ]));
        }

        $directory = $type->directoryFor($profile->loader);

        $this->files->setServer($server)->pull($file->url, '/' . $directory, [
            'filename' => $file->filename,
            // Background: a click should not hold a request open for a 60 MiB download.
            'foreground' => false,
        ]);

        return trim($directory . '/' . $file->filename, '/');
    }
}
