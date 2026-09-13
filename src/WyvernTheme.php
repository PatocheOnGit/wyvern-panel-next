<?php

namespace Wyvern;

/**
 * The Wyvern palette, in the six roles Filament recognises.
 *
 * Slate carries every surface: 950 is the page ground, 900 cards, 800 raised, 700
 * controls, 600 the rare hairline, 500-200 text. Teal is the single accent — 400 reads on dark, 500 and
 * darker carry white text where 400 would fail contrast. Red, green and yellow are
 * status only and follow the same split.
 */
final class WyvernTheme
{
    public const SLATE = [
        50 => '#F7F9FB',
        100 => '#EDF1F5',
        200 => '#E8EDF3',
        300 => '#C7D3DF',
        400 => '#9FAEBE',
        500 => '#6E7E8F',
        600 => '#2E3E4E',
        700 => '#1E2833',
        800 => '#1B2530',
        900 => '#141C26',
        950 => '#0B1017',
    ];

    public const TEAL = [
        50 => '#E9FBF8',
        100 => '#C8F5EF',
        200 => '#95EADF',
        300 => '#5BDACB',
        400 => '#16B8A6',
        500 => '#0C8175',
        600 => '#0A6B61',
        700 => '#08564E',
        800 => '#06453F',
        900 => '#053733',
        950 => '#032420',
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

    public const YELLOW = [
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
        'primary' => self::TEAL,
        'info' => self::TEAL,
        'gray' => self::SLATE,
        'danger' => self::RED,
        'success' => self::GREEN,
        'warning' => self::YELLOW,
    ];
}
