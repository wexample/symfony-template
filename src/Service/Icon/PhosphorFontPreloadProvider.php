<?php

namespace Wexample\SymfonyTemplate\Service\Icon;

use Symfony\Component\Asset\Packages;
use Wexample\SymfonyHelpers\Interface\HeadLinkProviderInterface;

/**
 * The icon font, asked for with the head. Named only by the layout
 * stylesheet, it was otherwise requested once that sheet had arrived and a
 * first icon was styled — after every script of the head had run.
 */
class PhosphorFontPreloadProvider implements HeadLinkProviderInterface
{
    // Bold is the one weight icons are written in, and the one built.
    private const string FONT_PATH = 'build/fonts/Phosphor-Bold.woff2';

    public function __construct(
        private readonly Packages $packages,
    ) {
    }

    public function getHeadLinks(): array
    {
        try {
            $href = $this->packages->getUrl(self::FONT_PATH);
        } catch (\Throwable) {
            // Assets not built, or built without the font: nothing to ask for.
            return [];
        }

        return [
            [
                'rel' => 'preload',
                'as' => 'font',
                'type' => 'font/woff2',
                'href' => $href,
                // A font is always fetched in cors mode: a preload without it
                // is not the request the stylesheet makes, and is fetched twice.
                'crossorigin' => 'anonymous',
            ],
        ];
    }
}
