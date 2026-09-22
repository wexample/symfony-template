<?php

namespace Wexample\SymfonyTemplate\Class;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Wexample\SymfonyTemplate\Helper\MarkdownHelper;

/**
 * A markdown text split in two: the yaml it opens with, and the rest.
 *
 * Immutable, and every transformation gives back another one, so a caller
 * chains what its documentation owes its reader — `->withoutComments()
 * ->withoutSections(['Commit log'])->body` — and the original stays readable
 * next to it.
 *
 * A front matter that yaml cannot parse comes out as no fields at all rather
 * than as an exception: one malformed document is a line to show in a list of a
 * hundred and fifty, not a page that refuses to open.
 */
final readonly class MarkdownDocument
{
    /**
     * @param array<string, mixed> $frontMatter
     */
    private function __construct(
        public array $frontMatter,
        public string $body,
    ) {
    }

    public static function fromString(string $content): self
    {
        [$matter, $body] = MarkdownHelper::splitFrontMatter($content);

        return new self(
            self::parse($matter),
            ltrim($body, "\n")
        );
    }

    /**
     * The same document without the comments its body carries.
     */
    public function withoutComments(): self
    {
        return new self(
            $this->frontMatter,
            MarkdownHelper::withoutComments($this->body)
        );
    }

    /**
     * The same document without those sections.
     *
     * @param string[] $headings
     */
    public function withoutSections(array $headings): self
    {
        return new self(
            $this->frontMatter,
            ltrim(MarkdownHelper::withoutSections($this->body, $headings))
        );
    }

    /**
     * A front matter field as the one line of text it was meant to be.
     *
     * Anything that is not a scalar, and anything that trims down to nothing,
     * comes back as null — a field written but left empty and a field never
     * written say the same thing to whoever reads the document.
     */
    public function text(string $key): ?string
    {
        $value = $this->frontMatter[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /**
     * A front matter field as a list of texts.
     *
     * A single value gives a list of one, so a field written both ways across a
     * documentation — `target: staff` here, a yaml list there — reads the same
     * at the caller.
     *
     * @return string[]
     */
    public function texts(string $key): array
    {
        $value = $this->frontMatter[$key] ?? null;

        if (null === $value) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $one): string => is_scalar($one) ? trim((string) $one) : '',
                (array) $value
            ),
            static fn (string $one): bool => '' !== $one
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function parse(string $frontMatter): array
    {
        if ('' === trim($frontMatter)) {
            return [];
        }

        try {
            return (array) (Yaml::parse($frontMatter) ?? []);
        } catch (ParseException) {
            return [];
        }
    }
}
