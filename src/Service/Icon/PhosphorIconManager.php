<?php

namespace Wexample\SymfonyTemplate\Service\Icon;

class PhosphorIconManager extends AbstractIconManager
{
    private const ICONS_LIBRARY_PHOSPHOR = 'ph';

    public function getLibraryKey(): string
    {
        return self::ICONS_LIBRARY_PHOSPHOR;
    }

    public function hasIcon(string $name): bool
    {
        return $name !== '';
    }

    public function iconTag(string $name, string $baseClass, string $tagName): string
    {
        [$style, $icon] = array_pad(explode('/', $name, 2), 2, '');

        $classes = [$baseClass];
        if ($style !== '') {
            $classes[] = 'ph-' . $style;
        }
        if ($icon !== '') {
            $classes[] = 'ph-' . $icon;
        }

        return
            '<' . $tagName .
            ' class="' . implode(' ', $classes) . '">' .
            '</' . $tagName . '>';
    }

    protected function buildIconsList(): array
    {
        return [];
    }
}
