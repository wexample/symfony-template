<?php

namespace Wexample\SymfonyTemplate\Twig;

use Symfony\Component\HttpKernel\KernelInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;
use Wexample\SymfonyTemplate\Service\MarkdownService;

/**
 * Markdown as a template sees it: a filter for a text already at hand, a
 * function for a file in the project.
 *
 * Both render, both return html, and both name the flavour the same way. The
 * extension holds no converter and no parser — it holds the one thing that is
 * about templates and not about markdown, which is deciding that a path written
 * in a twig file is a path inside the project.
 */
class MarkdownExtension extends AbstractExtension
{
    public function __construct(
        private readonly MarkdownService $markdown,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->convert(...), [
                self::FUNCTION_OPTION_IS_SAFE => self::FUNCTION_OPTION_IS_SAFE_VALUE_HTML,
            ]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('markdown_file', $this->markdownFile(...), [
                self::FUNCTION_OPTION_IS_SAFE => self::FUNCTION_OPTION_IS_SAFE_VALUE_HTML,
            ]),
        ];
    }

    public function convert(
        string $markdown,
        ?string $flavor = null
    ): string {
        return $this->markdown->toHtml($markdown, $flavor);
    }

    /**
     * A markdown file of the project, rendered.
     *
     * The front matter goes: it is what the file says about itself, addressed to
     * the tooling, and printing it would put a rule and a list of colons at the
     * top of the page.
     *
     * A path leading nowhere, or leading outside the project, renders as
     * nothing — a template is a poor place to raise, and a page missing a block
     * says more than a page that will not open.
     */
    public function markdownFile(
        string $path,
        ?string $flavor = null
    ): string {
        $resolved = $this->resolve($path);

        if (null === $resolved) {
            return '';
        }

        $document = $this->markdown->read($resolved);

        return null === $document ? '' : $this->markdown->toHtml($document->body, $flavor);
    }

    /**
     * The absolute path a template named, or null when it is not one of ours.
     *
     * Paths are read from the project directory, the way `markdown_file()` has
     * always read them. The containment check is what makes the function safe to
     * hand a variable: a path built from a route parameter cannot walk out of
     * the project with a handful of `..`.
     */
    private function resolve(string $path): ?string
    {
        $root = rtrim($this->kernel->getProjectDir(), '/');
        $resolved = self::normalize(str_starts_with($path, '/') ? $path : $root.'/'.$path);

        return str_starts_with($resolved, $root.'/') ? $resolved : null;
    }

    /**
     * A path with its `.` and `..` resolved on the text alone.
     *
     * Deliberately not `realpath()`: in development the packages of the suite
     * are symlinked into `vendor/`, and a real path would place them outside the
     * project and have the check refuse a file the template is entitled to read.
     * What has to be stopped is a `..` walking out, and that is a question about
     * the path as it was written.
     */
    private static function normalize(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ('' === $part || '.' === $part) {
                continue;
            }

            if ('..' === $part) {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }
}
