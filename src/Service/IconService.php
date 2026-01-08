<?php

namespace Wexample\SymfonyTemplate\Service;

use Exception;
use Twig\Environment;
use Wexample\SymfonyHelpers\Helper\DomHelper;
use Wexample\SymfonyTemplate\Service\Icon\AbstractIconManager;
use Wexample\SymfonyTemplate\Service\Icon\FaIconManager;
use Wexample\SymfonyTemplate\Service\Icon\MaterialIconManager;

class IconService
{
    public function __construct(
        private readonly FaIconManager $faIconManager,
        private readonly MaterialIconManager $materialIconManager
    ) {
    }

    /**
     * Render an icon via tag instead of inline SVG.
     *
     * @param string $name Icon name, optionally prefixed with library (e.g. "fa:coffee").
     * @param string $class CSS classes.
     * @param string $tagName HTML tag to use.
     * @param string|null $type Force library ('fa' or 'material'), otherwise auto-detect.
     *
     * @return string
     */
    public function icon(
        string $name,
        $class = '',
        $tagName = 'i',
        $type = null
    ) {
        [$prefix, $icon] = array_pad(explode(AbstractIconManager::LIBRARY_SEPARATOR, $name, 2), 2, '');

        $lib = $type ?? $prefix;
        $class = trim($class);
        $baseClass = $class !== '' ? $class . ' icon' : 'icon';

        // Material Icons
        if (
            ($this->materialIconManager->getLibraryKey() === $lib || ($lib === null && null === $type))
            && $this->materialIconManager->hasIcon($icon)
        ) {
            return $this->materialIconManager->iconTag($icon, $baseClass, $tagName);
        }

        // Font Awesome
        if (
            ($this->faIconManager->getLibraryKey() === $lib || ($lib === null && null === $type))
            && $this->faIconManager->hasIcon($icon)
        ) {
            return $this->faIconManager->iconTag($icon, $baseClass, $tagName);
        }

        return
            '<' . $tagName .
            ' class="icon">' .
            $name .
            '</' . $tagName . '>';
    }

    /**
     * @throws Exception
     */
    public function iconSource(
        Environment $twig,
        string $name,
        array $classes = []
    ): string {
        $default = DomHelper::buildTag('span');
        [$type, $icon] = array_pad(explode(AbstractIconManager::LIBRARY_SEPARATOR, $name, 2), 2, '');

        if ($type === $this->materialIconManager->getLibraryKey()) {
            return $this->materialIconManager->iconSource($twig, $icon, $classes) ?? $default;
        }

        if ($type === $this->faIconManager->getLibraryKey()) {
            return $this->faIconManager->iconSource($twig, $icon, $classes) ?? $default;
        }

        return $default;
    }

    /**
     * @throws Exception
     */
    public function iconList(string $type): array
    {
        return match ($type) {
            $this->faIconManager->getLibraryKey() => $this->faIconManager->iconList(),
            $this->materialIconManager->getLibraryKey() => $this->materialIconManager->iconList(),
            default => [],
        };
    }
}
