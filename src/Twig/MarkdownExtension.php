<?php

namespace Wexample\SymfonyTemplate\Twig;

use League\CommonMark\CommonMarkConverter;
use Twig\TwigFilter;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;

class MarkdownExtension extends AbstractExtension
{
    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', [$this, 'convert'], [
                self::FUNCTION_OPTION_IS_SAFE => self::FUNCTION_OPTION_IS_SAFE_VALUE_HTML,
            ]),
        ];
    }

    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
