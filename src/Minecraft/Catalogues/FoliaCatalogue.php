<?php

namespace Wyvern\Minecraft\Catalogues;

use Wyvern\Minecraft\Loader;

/** Folia is published through the same Fill v3 API as Paper. */
class FoliaCatalogue extends PaperCatalogue
{
    public function loader(): Loader
    {
        return Loader::Folia;
    }
}
