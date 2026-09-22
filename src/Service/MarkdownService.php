<?php

namespace Wexample\SymfonyTemplate\Service;

use League\CommonMark\ConverterInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Wexample\SymfonyTemplate\Class\MarkdownDocument;
use Wexample\SymfonyTemplate\Enum\MarkdownFlavor;
use Wexample\SymfonyTemplate\Helper\MarkdownHelper;

/**
 * The one place in the suite where markdown is read and turned into html.
 *
 * Before this, three packages held a piece of the answer — a twig filter here,
 * a function that only read a file there, a third-party filter underneath — and
 * none of them rendered a table. What a caller needs is behind one service:
 * parse a text, read a file, render either.
 *
 * Nothing here knows about a kernel or a project directory. A path given to it
 * is a path on the disk, and deciding which paths a template is allowed to name
 * is the business of `MarkdownExtension`, which is where the templates are.
 */
class MarkdownService
{
    /**
     * Kept as they were set when this was a converter built inside a twig
     * extension: html written inside a markdown document is dropped rather than
     * escaped, and a `javascript:` link never reaches the page.
     */
    private const array CONVERTER_OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    /**
     * Assembling a converter means assembling its whole extension set, and a
     * page renders one document per click — so each flavour is built the first
     * time it is asked for and kept.
     *
     * @var array<string, ConverterInterface>
     */
    private array $converters = [];

    /**
     * Markdown turned into what a browser shows.
     */
    public function toHtml(
        string $markdown,
        MarkdownFlavor|string|null $flavor = null
    ): string {
        $flavor = MarkdownFlavor::fromNameOrDefault($flavor);

        $converter = $this->converters[$flavor->value] ??= $flavor->converter(self::CONVERTER_OPTIONS);

        return (string) $converter->convert($markdown);
    }

    /**
     * A text read as a document: its front matter on one side, its body on the
     * other.
     */
    public function parse(string $content): MarkdownDocument
    {
        return MarkdownDocument::fromString($content);
    }

    /**
     * A file read as a document, or null when there is nothing to read.
     *
     * Null and not an exception: a path pointing at nothing is the ordinary
     * answer to a link that has gone stale, and the caller has a page to draw
     * either way.
     */
    public function read(string $path): ?MarkdownDocument
    {
        $content = is_file($path) ? @file_get_contents($path) : false;

        return false === $content ? null : $this->parse($content);
    }

    /**
     * Only the front matter of a file, read without opening the rest of it.
     *
     * The reason this exists next to `read()`: a list of a hundred and fifty
     * documents is drawn from thirteen fields apiece, and reading a hundred and
     * fifty whole procedures to show a table of their titles is a hundred and
     * fifty files read for nothing. The handle is closed at the fence.
     *
     * @return array<string, mixed>
     */
    public function readFrontMatter(string $path): array
    {
        $handle = is_file($path) ? @fopen($path, 'r') : false;

        if (false === $handle) {
            return [];
        }

        $block = '';
        $lines = 0;
        $closed = false;

        if (MarkdownHelper::FRONT_MATTER_FENCE === trim((string) fgets($handle))) {
            while (false !== ($line = fgets($handle)) && $lines++ < MarkdownHelper::FRONT_MATTER_MAX_LINES) {
                if (MarkdownHelper::FRONT_MATTER_FENCE === trim($line)) {
                    $closed = true;

                    break;
                }

                $block .= $line;
            }
        }

        fclose($handle);

        if (! $closed) {
            return [];
        }

        try {
            return (array) (Yaml::parse($block) ?? []);
        } catch (ParseException) {
            return [];
        }
    }
}
