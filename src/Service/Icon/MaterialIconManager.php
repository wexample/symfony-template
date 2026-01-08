<?php

namespace Wexample\SymfonyTemplate\Service\Icon;

use Wexample\Helpers\Helper\TextHelper;

class MaterialIconManager extends AbstractIconManager
{
    private const ICONS_LIBRARY_MATERIAL = 'material';

    public function getLibraryKey(): string
    {
        return self::ICONS_LIBRARY_MATERIAL;
    }

    public function iconTag(string $name, string $baseClass, string $tagName): string
    {
        return
            '<' . $tagName .
            ' class="' . $baseClass . ' material-icons">' .
            $name .
            '</' . $tagName . '>';
    }

    protected function buildIconsList(): array
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
}
