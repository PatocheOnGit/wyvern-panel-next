<?php

namespace Wyvern\Http;

use Illuminate\Http\Response;
use Wyvern\Minecraft\Players\PlayerHeads;

class PlayerHead
{
    public function __invoke(string $name, PlayerHeads $heads): Response
    {
        // An image: a guest gets a 403, not a redirect to the login page.
        abort_unless(auth()->check(), 403);

        $png = $heads->png($name);

        abort_if($png === null, 404);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
