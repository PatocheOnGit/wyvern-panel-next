<?php

namespace Wyvern;

/**
 * The colours the charts draw with.
 *
 * Literal rgba rather than var(--wy-*), for the same reason the terminal needs literals:
 * Chart.js parses these strings itself and never sees a stylesheet. They are kept here so
 * there is still one place to change them, and they are derived from WyvernTheme — azure
 * 400, sand 500 — rather than invented.
 *
 * They do not flip with the theme, and do not need to: every fill is semi-transparent, so
 * it composites onto whatever surface the card is, and each solid line colour clears
 * contrast against both a white card and a near-black one.
 *
 * Note where the palette rule bends and why. Elsewhere colour means a state, but a chart's
 * entire job is to encode data in visual channels, so a second series legitimately earns a
 * second hue. What it must not do is borrow a *status* hue for a category — an inbound
 * traffic series drawn in green reads as "healthy" to anyone scanning the page, which is
 * not a claim the data supports. Hence azure and violet for the pair, and green, amber and
 * red reserved as they are everywhere else.
 */
final class ChartPalette
{
    /** Azure 400 — the single-series fill, and the first of a pair. */
    public const ACCENT_FILL = 'rgba(91, 146, 245, 0.28)';

    public const ACCENT_LINE = 'rgb(91, 146, 245)';

    /** A violet that is distinguishable from azure at a glance and is nobody's status. */
    public const SECOND_FILL = 'rgba(150, 128, 232, 0.28)';

    public const SECOND_LINE = 'rgb(150, 128, 232)';

    /** Sand 500 — for the part of a gauge that is not the measurement. */
    public const QUIET = 'rgba(124, 119, 110, 0.45)';

    /**
     * A single filled series, with its line.
     *
     * @return array{backgroundColor: list<string>, borderColor: string, borderWidth: int}
     */
    public static function series(): array
    {
        return [
            'backgroundColor' => [self::ACCENT_FILL],
            'borderColor' => self::ACCENT_LINE,
            'borderWidth' => 2,
        ];
    }

    /**
     * The second series of a pair.
     *
     * @return array{backgroundColor: list<string>, borderColor: string, borderWidth: int}
     */
    public static function secondSeries(): array
    {
        return [
            'backgroundColor' => [self::SECOND_FILL],
            'borderColor' => self::SECOND_LINE,
            'borderWidth' => 2,
        ];
    }

    /**
     * Used against unused, for the storage doughnut.
     *
     * Two entries for two data points. It used to declare three colours for two slices,
     * so the third was never drawn.
     *
     * @return list<string>
     */
    public static function gauge(): array
    {
        return [self::ACCENT_LINE, self::QUIET];
    }
}
