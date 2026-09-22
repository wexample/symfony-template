<?php

namespace Wexample\SymfonyTemplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTemplate\Enum\MarkdownFlavor;
use Wexample\SymfonyTemplate\Service\MarkdownService;

class MarkdownServiceTest extends TestCase
{
    private const string TABLE = "| a | b |\n|---|---|\n| 1 | 2 |";

    private MarkdownService $markdown;

    protected function setUp(): void
    {
        $this->markdown = new MarkdownService();
    }

    /**
     * The whole reason the flavour moved: a hundred and fifty-seven documents
     * of a quality system out of a hundred and fifty-eight hold a table.
     */
    public function testTablesRenderByDefault(): void
    {
        $this->assertStringContainsString('<table>', $this->markdown->toHtml(self::TABLE));
    }

    public function testStrictCommonMarkHasNoTables(): void
    {
        $this->assertStringNotContainsString(
            '<table>',
            $this->markdown->toHtml(self::TABLE, MarkdownFlavor::COMMON_MARK)
        );
    }

    /**
     * A flavour arrives from a template as the string a designer typed, and a
     * page is a poor place to learn that a word was misspelled.
     */
    public function testUnknownFlavorFallsBackToTheDefault(): void
    {
        $this->assertSame(
            $this->markdown->toHtml(self::TABLE),
            $this->markdown->toHtml(self::TABLE, 'klingon')
        );
    }

    public function testHtmlWrittenInsideMarkdownIsStripped(): void
    {
        $this->assertStringNotContainsString(
            '<script',
            $this->markdown->toHtml('<script>alert(1)</script>')
        );
    }

    public function testUnsafeLinksAreDropped(): void
    {
        $this->assertStringNotContainsString(
            'javascript:',
            $this->markdown->toHtml('[x](javascript:alert%281%29)')
        );
    }

    public function testReadOfAFileThatIsNotThere(): void
    {
        $this->assertNull($this->markdown->read(__DIR__.'/does-not-exist.md'));
        $this->assertSame([], $this->markdown->readFrontMatter(__DIR__.'/does-not-exist.md'));
    }

    public function testReadFrontMatterReadsTheSameFieldsAsRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'md');
        file_put_contents($path, "---\ntitle: A procedure\n---\n\n# A procedure\n");

        try {
            $this->assertSame(
                ['title' => 'A procedure'],
                $this->markdown->readFrontMatter($path)
            );
            $this->assertSame(
                $this->markdown->readFrontMatter($path),
                $this->markdown->read($path)->frontMatter
            );
        } finally {
            unlink($path);
        }
    }

    /**
     * A document opening on a horizontal rule has no front matter, and the head
     * of the file must not be read as one.
     */
    public function testReadFrontMatterOfADocumentWithoutOne(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'md');
        file_put_contents($path, "---\n\n# A title\n");

        try {
            $this->assertSame([], $this->markdown->readFrontMatter($path));
        } finally {
            unlink($path);
        }
    }
}
