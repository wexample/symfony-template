<?php

namespace Wexample\SymfonyTemplate\Service\Icon;

use Wexample\SymfonyHelpers\Helper\FileHelper;

class FaIconManager extends AbstractIconManager
{
    private const ICONS_LIBRARY_FA = 'fa';

    public function getLibraryKey(): string
    {
        return self::ICONS_LIBRARY_FA;
    }

    public function iconTag(string $name, string $baseClass, string $tagName): string
    {
        $classes = $this->icons[$name]['name'];
        $classes = 'fa-' . str_replace('/', ' fa-', $classes);

        return
            '<' . $tagName .
            ' class="' . $baseClass . '">' .
            '<i class="fa ' . $classes . '"></i>' .
            '</' . $tagName . '>';
    }

    protected function buildIconsList(): array
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
}
