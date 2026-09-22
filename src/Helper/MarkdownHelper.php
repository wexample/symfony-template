<?php

namespace Wexample\SymfonyTemplate\Helper;

/**
 * The string work a markdown text asks for before anything renders it.
 *
 * Text in, text out: nothing here opens a file, parses yaml or builds html, so
 * the same functions answer for a document read from a disk, a field coming out
 * of a form and a fixture written inside a test. What needs a filesystem is in
 * `MarkdownService`, what needs the yaml parsed is in `MarkdownDocument`.
 *
 * Everything below walks the text line by line and keeps track of fenced code,
 * which is the whole reason this is not four regular expressions. A shell
 * snippet is full of lines opening on `#`, and a documentation about markdown
 * is full of lines opening on `---`: read without the fence in mind, a code
 * block gets read as headings and a page loses its second half.
 */
final class MarkdownHelper
{
    /**
     * The line a front matter block opens and closes with.
     */
    public const string FRONT_MATTER_FENCE = '---';

    /**
     * Past this many lines, an unclosed opening fence is read as what it more
     * probably is — a horizontal rule on the first line — and the text is kept
     * whole. Also what stops a file with no front matter at all from being read
     * to its end just to find that out.
     */
    public const int FRONT_MATTER_MAX_LINES = 120;

    /**
     * The yaml a document opens with and the rest of it.
     *
     * The block comes back raw, unparsed and without its fences; a document
     * carrying none gives an empty string and its whole self.
     *
     * @return array{0: string, 1: string} the front matter block, then the body
     */
    public static function splitFrontMatter(string $content): array
    {
        $lines = self::lines($content);

        if (self::FRONT_MATTER_FENCE !== trim($lines[0])) {
            return ['', $content];
        }

        $last = min(count($lines) - 1, self::FRONT_MATTER_MAX_LINES);

        for ($index = 1; $index <= $last; ++$index) {
            if (self::FRONT_MATTER_FENCE === trim($lines[$index])) {
                return [
                    implode("\n", array_slice($lines, 1, $index - 1)),
                    implode("\n", array_slice($lines, $index + 1)),
                ];
            }
        }

        // An opening fence that never closes is not a front matter.
        return ['', $content];
    }

    /**
     * The text without what was written for the tooling and not for a reader.
     *
     * A comment sitting inside a code block is left where it is: there it is not
     * a comment but a line of the example being shown.
     */
    public static function withoutComments(string $markdown): string
    {
        return self::outsideCode(
            $markdown,
            static fn (string $prose): string => preg_replace('/<!--.*?-->/s', '', $prose) ?? $prose
        );
    }

    /**
     * The text without a heading and everything under it, up to the next heading
     * of its own rank or above.
     *
     * The comparison is on the trimmed heading text, case aside, so `## Commit
     * log` and `# commit log` are the same section under two ranks. A heading
     * appearing twice takes both of its sections away.
     */
    public static function withoutSection(
        string $markdown,
        string $heading
    ): string {
        return self::withoutSections($markdown, [$heading]);
    }

    /**
     * The text without each of those sections.
     *
     * The mechanism is here; the list is not. Which sections a document owes its
     * reader is a question about that documentation and not about markdown, so
     * it stays at the caller — a package deciding it for everyone would be a
     * package to edit every time one application changes its mind.
     *
     * @param string[] $headings
     */
    public static function withoutSections(
        string $markdown,
        array $headings
    ): string {
        if ([] === $headings) {
            return $markdown;
        }

        $dropped = array_map(
            static fn (string $heading): string => mb_strtolower(trim($heading)),
            $headings
        );

        $kept = [];
        $dropping = null;
        $fence = null;

        foreach (self::lines($markdown) as $line) {
            $fence = self::fenceAfter($line, $fence);

            // Inside a code block, `# something` is a shell comment and `### `
            // is a markdown example — neither opens nor closes a section.
            if (null === $fence && preg_match('/^(#{1,6})\s+(.*)$/', $line, $match)) {
                $level = strlen($match[1]);

                if (null !== $dropping && $level <= $dropping) {
                    $dropping = null;
                }

                if (in_array(mb_strtolower(trim($match[2])), $dropped, true)) {
                    $dropping = $level;
                }
            }

            if (null === $dropping) {
                $kept[] = $line;
            }
        }

        return implode("\n", $kept);
    }

    /**
     * @return string[] the text's lines, whichever way its author ended them
     */
    public static function lines(string $text): array
    {
        return preg_split('/\r\n|\r|\n/', $text) ?: [''];
    }

    /**
     * Rewrites what a reader reads and leaves fenced code untouched.
     *
     * @param callable(string): string $transform
     */
    private static function outsideCode(
        string $markdown,
        callable $transform
    ): string {
        $out = [];
        $prose = [];
        $fence = null;

        foreach (self::lines($markdown) as $line) {
            $opened = null === $fence;
            $fence = self::fenceAfter($line, $fence);

            if ($opened && null === $fence) {
                $prose[] = $line;

                continue;
            }

            // The line that opens a block belongs to the block, the one that
            // closes it too; between them, prose is put aside and given back
            // to the transformation in one piece, so a comment spanning several
            // lines is still one comment.
            if ([] !== $prose) {
                $out[] = $transform(implode("\n", $prose));
                $prose = [];
            }

            $out[] = $line;
        }

        if ([] !== $prose) {
            $out[] = $transform(implode("\n", $prose));
        }

        return implode("\n", $out);
    }

    /**
     * The fence still open once that line has been read.
     *
     * A block opens on three backticks or three tildes and closes on the same
     * character, never on the other one — which is how a backticked block gets
     * to show a tilded one.
     */
    private static function fenceAfter(
        string $line,
        ?string $fence
    ): ?string {
        if (! preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $match)) {
            return $fence;
        }

        $marker = $match[1][0];

        if (null === $fence) {
            return $marker;
        }

        return $fence === $marker ? null : $fence;
    }
}
