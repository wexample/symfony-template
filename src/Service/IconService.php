<?php

namespace Wexample\SymfonyTemplate\Service;

use DOMDocument;
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Helper\DomHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;

class IconService
{
    private const ICONS_LIBRARY_FA = 'fa';
    private const ICONS_LIBRARY_MATERIAL = 'material';
    public const LIBRARY_SEPARATOR = ':';

    protected stdClass $icons;
    private string $projectDir;
    private CacheItemInterface $cacheItem;

    public function __construct(
        KernelInterface $kernel,
        protected readonly CacheItemPoolInterface $cache
    ) {
        $this->projectDir = $kernel->getProjectDir();
        $this->cacheItem = $this->cache->getItem('symfony_design_system_icons_list');

        if (! $this->cacheItem->isHit()) {
            $this->icons = (object) [
                self::ICONS_LIBRARY_FA => $this->buildIconsListFa(),
                self::ICONS_LIBRARY_MATERIAL => $this->buildIconsListMaterial(),
            ];
            $this->saveRegistryCache();
        } else {
            $this->icons = $this->cacheItem->get();
        }
    }

    private function saveRegistryCache(): void
    {
        $this->cache->save(
            $this->cacheItem->set($this->icons)
        );
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
        [$prefix, $icon] = array_pad(explode(self::LIBRARY_SEPARATOR, $name, 2), 2, '');

        $lib = $type ?? $prefix;
        $class = trim($class);
        $baseClass = $class !== '' ? $class . ' icon' : 'icon';

        // Material Icons
        if (
            (self::ICONS_LIBRARY_MATERIAL === $lib || ($lib === null && null === $type)
                && isset($this->icons->{self::ICONS_LIBRARY_MATERIAL}[$icon]))
        ) {
            return
                '<' . $tagName .
                ' class="' . $baseClass . ' material-icons">' .
                $icon .
                '</' . $tagName . '>';
        }

        // Font Awesome
        if (
            (self::ICONS_LIBRARY_FA === $lib || ($lib === null && null === $type))
            && isset($this->icons->{self::ICONS_LIBRARY_FA}[$icon])
        ) {
            $classes = $this->icons->{self::ICONS_LIBRARY_FA}[$icon]["name"];
            $classes = 'fa-' . str_replace('/', ' fa-', $classes);

            return
                '<' . $tagName .
                ' class="' . $baseClass . '">' .
                '<i class="fa ' . $classes . '"></i>' .
                '</' . $tagName . '>';
        }

        return
            '<' . $tagName .
            ' class="icon">' .
            $name .
            '</' . $tagName . '>';
    }

    public function buildIconsListMaterial(): array
    {
        $output = [];
        $pathFonts = $this->projectDir . '/node_modules/material-design-icons/';

        if (is_dir($pathFonts)) {
            foreach (scandir($pathFonts) as $item) {
                $pathSvg = $pathFonts . $item . '/svg/production/';
                if ($item[0] !== '.' && is_dir($pathFonts . $item) && file_exists($pathSvg)) {
                    foreach (scandir($pathSvg) as $fileIcon) {
                        if ($fileIcon[0] !== '.') {
                            $prefix = 'ic_';
                            $suffix = '_24px.svg';
                            if (str_starts_with($fileIcon, $prefix) && str_ends_with($fileIcon, $suffix)) {
                                $iconName = TextHelper::removePrefix(
                                    TextHelper::removeSuffix(
                                        $fileIcon,
                                        $suffix
                                    ),
                                    $prefix
                                );

                                $output[$iconName] = [
                                    'content' => null,
                                    'file' => $pathSvg . $fileIcon,
                                    'name' => $iconName,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $output;
    }

    public function buildIconsListFa(): array
    {
        $pathSvg = $this->projectDir . '/vendor/fortawesome/font-awesome/svgs/';
        $output = [];

        if (is_dir($pathSvg)) {
            $groups = scandir($pathSvg);

            foreach ($groups as $group) {
                if ('.' !== $group[0]) {
                    $icons = scandir($pathSvg . $group);
                    foreach ($icons as $fileIcon) {
                        if ('.' !== $fileIcon[0]) {
                            $iconName = $group . '/' . FileHelper::removeExtension(basename($fileIcon));
                            $output[$iconName] = [
                                'name' => $iconName,
                                'file' => $pathSvg . $group . '/' . $fileIcon,
                                'content' => null,
                            ];
                        }
                    }
                }
            }
        }

        return $output;
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

        if ($icon = $this->loadIconSvg(self::ICONS_LIBRARY_MATERIAL, $name, $classes)) {
            return $icon;
        }

        if ($icon = $this->loadIconSvg(self::ICONS_LIBRARY_FA, $name, $classes)) {
            return $icon;
        }

        return $default;
    }

    private function loadIconSvg(
        string $registryType,
        string $name,
        array $classes
    ): ?string {
        [$type, $name] = explode(
            self::LIBRARY_SEPARATOR,
            $name
        );

        if ($registryType !== $type) {
            return null;
        }

        $registry = &$this->icons->$registryType;
        $contentName = md5(
            implode(
                $classes
            )
        );

        if (isset($registry[$name])) {
            if (! isset($registry[$name]['content'][$contentName])) {
                $svgContent = file_get_contents($registry[$name]['file']);

                $dom = new DOMDocument();
                $dom->loadXML($svgContent);
                $tags = $dom->getElementsByTagName('svg');
                if ($tags->length > 0) {
                    $svg = $tags->item(0);

                    $existingClass = $svg->getAttribute('class');
                    $svg->setAttribute(
                        'class',
                        $existingClass
                        . implode(
                            ' ',
                            array_merge(
                                ['icon'],
                                $classes
                            )
                        )
                    );

                    $content = $dom->saveXML($svg);
                    $registry[$name]['content'][$contentName] = $content;

                    $this->saveRegistryCache();
                }
            }

            return $registry[$name]['content'][$contentName];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function iconList(string $type): array
    {
        return match ($type) {
            self::ICONS_LIBRARY_FA => $this->buildIconsListFa(),
            self::ICONS_LIBRARY_MATERIAL => $this->buildIconsListMaterial(),
            default => [],
        };
    }
}
