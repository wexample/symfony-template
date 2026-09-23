<?php

namespace Wexample\SymfonyTemplate\Enum;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * Which dialect of markdown a text is read as.
 *
 * Github's is the default and not the strict one, because the strict one has no
 * tables: a document holding a pipe table comes out of CommonMark as rows of
 * vertical bars, which is not a rendering anybody asked for. The flavour is a
 * superset — tables, strikethrough, task lists, bare urls turned into links —
 * so a text written for CommonMark renders the same, and a text CommonMark
 * spoiled renders at last.
 *
 * `COMMON_MARK` stays reachable for the caller who needs a pipe to be a pipe.
 */
enum MarkdownFlavor: string
{
    case COMMON_MARK = 'commonmark';

    case GITHUB = 'gfm';

    /**
     * What a caller naming no flavour gets.
     */
    public static function default(): self
    {
        return self::GITHUB;
    }

    /**
     * The flavour under that name, or the default one.
     *
     * Made to be fed from a template, where the flavour arrives as the string a
     * designer typed: an unknown one renders rather than raises, since a page is
     * a poor place to learn that a word was misspelled.
     */
    public static function fromNameOrDefault(self|string|null $flavor): self
    {
        if ($flavor instanceof self) {
            return $flavor;
        }

        return (null === $flavor ? null : self::tryFrom($flavor)) ?? self::default();
    }

    /**
     * The environment a converter of this flavour runs on, not yet sealed.
     *
     * An environment and not a converter, so that whoever builds on it can still
     * register a renderer before the first conversion locks it — which is how a
     * converter drawing tables in the design system's own markup is made.
     *
     * @param array<string, mixed> $options
     */
    public function environment(array $options = []): Environment
    {
        $environment = new Environment($options);
        $environment->addExtension(new CommonMarkCoreExtension());

        if (self::GITHUB === $this) {
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
        }

        return $environment;
    }
}
