<?php

namespace Wexample\SymfonyTemplate\Twig;

use Exception;
use Twig\Environment;
use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;
use Wexample\SymfonyTemplate\Service\IconService;

class IconExtension extends AbstractExtension
{
    public function __construct(
        private readonly IconService $iconService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                VariableHelper::ICON . '_source',
                [$this, VariableHelper::ICON . 'Source'],
                [
                    self::FUNCTION_OPTION_IS_SAFE => self::FUNCTION_OPTION_IS_SAFE_VALUE_HTML,
                    self::FUNCTION_OPTION_NEEDS_ENVIRONMENT => true,
                ]
            ),
            new TwigFunction('icon_list', [$this, 'iconList']),
            new TwigFunction(
                'icon',
                [$this, 'icon'],
                [self::FUNCTION_OPTION_IS_SAFE => self::FUNCTION_OPTION_IS_SAFE_VALUE_HTML]
            ),
        ];
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
        return $this->iconService->icon(
            $name,
            $class,
            $tagName,
            $type
        );
    }

    /**
     * @throws Exception
     */
    public function iconSource(
        Environment $twig,
        string $name,
        array $classes = []
    ): string {
        return $this->iconService->iconSource(
            $twig,
            $name,
            $classes
        );
    }

    /**
     * @throws Exception
     */
    public function iconList(string $type): array
    {
        return $this->iconService->iconList($type);
    }
}
