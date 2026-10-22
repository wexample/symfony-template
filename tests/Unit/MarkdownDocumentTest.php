<?php

namespace Wexample\SymfonyTemplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTemplate\Class\MarkdownDocument;

class MarkdownDocumentTest extends TestCase
{
    private const string DOCUMENT = <<<'MD'
        ---
        title: A procedure
        target:
          - staff
          - "   "
        version: 1.2
        empty:
        ---

        # A procedure

        <!-- written for the pipeline -->
        Body.

        ## Commit log

        - abc123
        MD;

    public function testFrontMatterAndBodyAreSplit(): void
    {
        $document = MarkdownDocument::fromString(self::DOCUMENT);

        $this->assertSame('A procedure', $document->frontMatter['title']);
        $this->assertStringStartsWith('# A procedure', $document->body);
    }

    public function testTextTrimsAndCastsScalars(): void
    {
        $document = MarkdownDocument::fromString(self::DOCUMENT);

        $this->assertSame('A procedure', $document->text('title'));
        $this->assertSame('1.2', $document->text('version'));
    }

    /**
     * A field written but left empty and a field never written say the same
     * thing, and a field that is not a scalar says nothing on one line.
     */
    public function testTextOfWhatIsNotOneLineOfText(): void
    {
        $document = MarkdownDocument::fromString(self::DOCUMENT);

        $this->assertNull($document->text('empty'));
        $this->assertNull($document->text('missing'));
        $this->assertNull($document->text('target'));
    }

    public function testTextsDropsWhatTrimsToNothing(): void
    {
        $document = MarkdownDocument::fromString(self::DOCUMENT);

        $this->assertSame(['staff'], $document->texts('target'));
        $this->assertSame([], $document->texts('missing'));
    }

    public function testTextsOfASingleValue(): void
    {
        $document = MarkdownDocument::fromString("---\ntarget: staff\n---\n");

        $this->assertSame(['staff'], $document->texts('target'));
    }

    public function testTransformationsGiveBackAnotherDocument(): void
    {
        $document = MarkdownDocument::fromString(self::DOCUMENT);
        $clean = $document->withoutComments()->withoutSections(['Commit log']);

        $this->assertStringContainsString('pipeline', $document->body);
        $this->assertStringNotContainsString('pipeline', $clean->body);
        $this->assertStringNotContainsString('abc123', $clean->body);
        $this->assertSame($document->frontMatter, $clean->frontMatter);
    }

    /**
     * One malformed document is a line to report in a list of a hundred and
     * fifty, not a page that refuses to open.
     */
    public function testUnparsableFrontMatterIsNoFieldAtAll(): void
    {
        $document = MarkdownDocument::fromString("---\n: : :\n\tx\n---\nbody\n");

        $this->assertSame([], $document->frontMatter);
        $this->assertSame("body\n", $document->body);
    }

    public function testDocumentWithoutFrontMatter(): void
    {
        $document = MarkdownDocument::fromString("# Title\n");

        $this->assertSame([], $document->frontMatter);
        $this->assertSame("# Title\n", $document->body);
    }
}
