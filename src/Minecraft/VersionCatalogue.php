<?php

namespace Wyvern\Minecraft;

use Wyvern\Minecraft\Catalogues\FabricCatalogue;
use Wyvern\Minecraft\Catalogues\ForgeCatalogue;
use Wyvern\Minecraft\Catalogues\NeoForgeCatalogue;
use Wyvern\Minecraft\Catalogues\PaperCatalogue;
use Wyvern\Minecraft\Catalogues\PurpurCatalogue;
use Wyvern\Minecraft\Catalogues\VanillaCatalogue;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;

/**
 * One way in to every flavour's own upstream API.
 *
 * Nothing outside this namespace should know that Paper speaks Fill v3 while Forge
 * publishes a promotions file — the picker asks for versions and gets versions.
 */
class VersionCatalogue
{
    /** @var array<string, LoaderCatalogue> */
    private array $catalogues = [];

    public function for(Loader $loader): LoaderCatalogue
    {
        return $this->catalogues[$loader->value] ??= match ($loader) {
            Loader::Vanilla => new VanillaCatalogue(),
            Loader::Paper => new PaperCatalogue(),
            Loader::Purpur => new PurpurCatalogue(),
            Loader::Fabric => new FabricCatalogue(),
            Loader::Forge => new ForgeCatalogue(),
            Loader::NeoForge => new NeoForgeCatalogue(),
        };
    }

    /** @return string[] */
    public function gameVersions(Loader $loader): array
    {
        return $this->for($loader)->gameVersions();
    }

    /** @return string[] */
    public function builds(Loader $loader, string $gameVersion): array
    {
        return $this->for($loader)->builds($gameVersion);
    }

    public function binary(Loader $loader, string $gameVersion, ?string $build = null): ?ServerBinary
    {
        return $this->for($loader)->binary($gameVersion, $build);
    }
}
