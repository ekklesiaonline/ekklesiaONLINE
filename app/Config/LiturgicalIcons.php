<?php

namespace Config;

class LiturgicalIcons
{
    /**
     * Mapping from stored icon keys to Bootstrap Icons classes.
     *
     * @var array<string, string>
     */
    public array $map = [
        'season-advent' => 'bi-hourglass-split',
        'season-christmas' => 'bi-stars',
        'season-epiphany' => 'bi-brightness-high',
        'season-lent' => 'bi-droplet',
        'season-holy-week' => 'bi-signpost-2',
        'season-easter' => 'bi-sunrise',
        'season-pentecost' => 'bi-wind',
        'season-after-pentecost' => 'bi-calendar2-week',
        'observance-sunday' => 'bi-calendar2-week',
        'observance-feast' => 'bi-cup-hot',
        'observance-day' => 'bi-calendar-day',
        'observance-other' => 'bi-bookmark-star',
    ];

    /**
     * Suggested icon keys for season/observance icon form fields.
     *
     * key => language key
     *
     * @var array<string, string>
     */
    public array $options = [
        'season-advent' => 'Diocesan.liturgicalIconOptionSeasonAdvent',
        'season-christmas' => 'Diocesan.liturgicalIconOptionSeasonChristmas',
        'season-epiphany' => 'Diocesan.liturgicalIconOptionSeasonEpiphany',
        'season-lent' => 'Diocesan.liturgicalIconOptionSeasonLent',
        'season-holy-week' => 'Diocesan.liturgicalIconOptionSeasonHolyWeek',
        'season-easter' => 'Diocesan.liturgicalIconOptionSeasonEaster',
        'season-pentecost' => 'Diocesan.liturgicalIconOptionSeasonPentecost',
        'season-after-pentecost' => 'Diocesan.liturgicalIconOptionSeasonAfterPentecost',
        'observance-sunday' => 'Diocesan.liturgicalIconOptionObservanceSunday',
        'observance-feast' => 'Diocesan.liturgicalIconOptionObservanceFeast',
        'observance-day' => 'Diocesan.liturgicalIconOptionObservanceDay',
        'observance-other' => 'Diocesan.liturgicalIconOptionObservanceOther',
    ];

    /**
     * Liturgical color presentation.
     *
     * color => [swatch, text, labelKey, swatchLabelKey]
     *
     * @var array<string, array{swatch: string, text: string, labelKey: string, swatchLabelKey: string}>
     */
    public array $colors = [
        'blue' => [
            'swatch' => '#0d6efd',
            'text' => '#ffffff',
            'labelKey' => 'Diocesan.liturgicalColorBlue',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchBlue',
        ],
        'purple' => [
            'swatch' => '#6f42c1',
            'text' => '#ffffff',
            'labelKey' => 'Diocesan.liturgicalColorPurple',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchPurple',
        ],
        'white' => [
            'swatch' => '#f8f9fa',
            'text' => '#212529',
            'labelKey' => 'Diocesan.liturgicalColorWhite',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchWhite',
        ],
        'green' => [
            'swatch' => '#198754',
            'text' => '#ffffff',
            'labelKey' => 'Diocesan.liturgicalColorGreen',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchGreen',
        ],
        'red' => [
            'swatch' => '#dc3545',
            'text' => '#ffffff',
            'labelKey' => 'Diocesan.liturgicalColorRed',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchRed',
        ],
        'rose' => [
            'swatch' => '#d63384',
            'text' => '#ffffff',
            'labelKey' => 'Diocesan.liturgicalColorRose',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchRose',
        ],
        'black' => [
            'swatch' => '#212529',
            'text' => '#ffffff',
            'labelKey' => 'Diocesan.liturgicalColorBlack',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchBlack',
        ],
        'gold' => [
            'swatch' => '#d4a017',
            'text' => '#212529',
            'labelKey' => 'Diocesan.liturgicalColorGold',
            'swatchLabelKey' => 'Diocesan.liturgicalColorSwatchGold',
        ],
    ];

    /**
     * Fallback icon when a key does not exist in the map.
     */
    public string $defaultIcon = 'bi-tag';
}
