<?php

namespace Wyvern;

/**
 * The Wyvern palette, in the six roles Filament recognises.
 *
 * One idea carries the whole system: colour means a state, never a decoration. The only
 * urgent question a game panel answers is whether a server is running, so saturation is
 * spent on that and nothing else.
 *
 * SAND is therefore the whole panel — a warm neutral rather than the blue-black every
 * other panel uses. Filament reads the ramp from both ends: 50-200 are the light theme's
 * surfaces and the dark theme's text, 800-950 the dark theme's surfaces and the light
 * theme's text, 400-600 the muted text of both. So the ramp has to stay monotonic; it
 * cannot be a set of hand-picked surfaces.
 *
 * AZURE is the single accent, and it is affordance only — focus, the active nav item,
 * links, selection, the primary button. It is cold because every warm accent collides
 * with a status: a brand amber and a warning amber are indistinguishable in an 8px dot.
 *
 * GREEN, AMBER and RED are reserved for state and appear nowhere else.
 */
final class WyvernTheme
{
    /** Every surface, and every piece of text, in both themes. */
    public const SAND = [
        50 => '#FAF8F4',
        100 => '#F2EFE9',
        200 => '#E6E1D7',
        300 => '#D2CCC0',
        400 => '#A9A399',
        500 => '#7C776E',
        600 => '#5A564F',
        700 => '#3B3835',
        800 => '#24221F',
        900 => '#151412',
        950 => '#0B0A09',
    ];

    /** The accent. 400 reads on dark, 500 and darker carry white text. */
    public const AZURE = [
        50 => '#EEF4FE',
        100 => '#DBE7FD',
        200 => '#BACFFB',
        300 => '#8FB2F8',
        400 => '#5B92F5',
        500 => '#3A6FD8',
        600 => '#2D57AC',
        700 => '#234480',
        800 => '#1B3461',
        900 => '#14284A',
        950 => '#0C1A31',
    ];

    public const RED = [
        50 => '#FDECED',
        100 => '#FAD2D4',
        200 => '#F5A8AC',
        300 => '#F27E84',
        400 => '#F2555A',
        500 => '#C93B41',
        600 => '#A82F34',
        700 => '#87262A',
        800 => '#6B1E21',
        900 => '#54181A',
        950 => '#3D1113',
    ];

    public const GREEN = [
        50 => '#E9F8EF',
        100 => '#C8EDD8',
        200 => '#95DCB4',
        300 => '#63CB91',
        400 => '#3FBF7F',
        500 => '#2F8F5B',
        600 => '#26754A',
        700 => '#1E5C3B',
        800 => '#18492F',
        900 => '#133A26',
        950 => '#0D281A',
    ];

    public const AMBER = [
        50 => '#FBF4E5',
        100 => '#F5E4BF',
        200 => '#EDCE85',
        300 => '#E6B856',
        400 => '#E0A32E',
        500 => '#B07D1C',
        600 => '#8F6516',
        700 => '#725011',
        800 => '#5A400E',
        900 => '#48330B',
        950 => '#2F2107',
    ];

    /** Wyvern has one accent, so info reuses it rather than introducing a second hue. */
    public const COLORS = [
        'primary' => self::AZURE,
        'info' => self::AZURE,
        'gray' => self::SAND,
        'danger' => self::RED,
        'success' => self::GREEN,
        'warning' => self::AMBER,
    ];
}
