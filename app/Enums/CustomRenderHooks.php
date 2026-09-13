<?php

namespace App\Enums;

enum CustomRenderHooks: string
{
    case FooterStart = 'wyvern::footer.start';
    case FooterEnd = 'wyvern::footer.end';
}
