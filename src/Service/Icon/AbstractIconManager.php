<?php

namespace Wexample\SymfonyTemplate\Service\Icon;

use DOMDocument;
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

abstract class AbstractIconManager
{
    public const LIBRARY_SEPARATOR = ':';

    protected array $icons = [];
    protected string $projectDir;
    private CacheItemInterface $cacheItem;

    public function __construct(
        KernelInterface $kernel,
        private readonly CacheItemPoolInterface $cache
    ) {
        $this->projectDir = $kernel->getProjectDir();
        $this->cacheItem = $this->cache->getItem($this->getCacheKey());

        if (! $this->cacheItem->isHit()) {
            $this->icons = $this->buildIconsList();
            $this->saveRegistryCache();
        } else {
            $icons = $this->cacheItem->get();
            $this->icons = is_array($icons) ? $icons : [];
        }
    }

    abstract public function getLibraryKey(): string;

    abstract protected function buildIconsList(): array;

    abstract public function iconTag(string $name, string $baseClass, string $tagName): string;

    protected function getCacheKey(): string
    {
        return 'symfony_design_system_icons_list_' . $this->getLibraryKey();
    }

    public function hasIcon(string $name): bool
    {
        return isset($this->icons[$name]);
    }

    /**
     * @throws Exception
     */
    public function iconSource(
        Environment $twig,
        string $name,
        array $classes = []
    ): ?string {
        return $this->loadIconSvg($name, $classes);
    }

    private function saveRegistryCache(): void
    {
        $this->cache->save(
            $this->cacheItem->set($this->icons)
        );
    }

    private function loadIconSvg(
        string $name,
        array $classes
    ): ?string {
        $contentName = md5(
            implode(
                $classes
            )
        );

        if (isset($this->icons[$name])) {
            if (! is_array($this->icons[$name]['content'] ?? null)) {
                $this->icons[$name]['content'] = [];
            }

            if (! isset($this->icons[$name]['content'][$contentName])) {
                $svgContent = file_get_contents($this->icons[$name]['file']);

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
                    $this->icons[$name]['content'][$contentName] = $content;

                    $this->saveRegistryCache();
                }
            }

            return $this->icons[$name]['content'][$contentName];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function iconList(): array
    {
        return $this->buildIconsList();
    }
}
