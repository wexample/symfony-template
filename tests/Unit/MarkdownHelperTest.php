<?php

namespace Wexample\SymfonyTemplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTemplate\Helper\MarkdownHelper;

class MarkdownHelperTest extends TestCase
{
    public function testSplitFrontMatter(): void
    {
        $this->assertSame(
            ['title: A procedure', "# A procedure\n"],
            MarkdownHelper::splitFrontMatter("---\ntitle: A procedure\n---\n# A procedure\n")
        );
    }

    public function testSplitFrontMatterWithoutOne(): void
    {
        $this->assertSame(
            ['', '# A procedure'],
            MarkdownHelper::splitFrontMatter('# A procedure')
        );
    }

    /**
     * A first line that is a horizontal rule and not an opening fence: the
     * document has to come back whole.
     */
    public function testSplitFrontMatterNeverClosed(): void
    {
        $content = "---\n\nnot a front matter\n";

        $this->assertSame(['', $content], MarkdownHelper::splitFrontMatter($content));
    }

    public function testSplitFrontMatterOnWindowsLineEndings(): void
    {
        $this->assertSame(
            ['a: 1', 'body'],
            MarkdownHelper::splitFrontMatter("---\r\na: 1\r\n---\r\nbody")
        );
    }

    public function testWithoutComments(): void
    {
        $this->assertSame(
            "before\n\nafter",
            MarkdownHelper::withoutComments("before\n<!-- written for\nthe pipeline -->\nafter")
        );
    }

    /**
     * Inside a code block a comment is not a comment, it is the example being
     * shown.
     */
    public function testWithoutCommentsLeavesFencedCodeAlone(): void
    {
        $markdown = "```html\n<!-- keep me -->\n```";

        $this->assertSame($markdown, MarkdownHelper::withoutComments($markdown));
    }

    public function testWithoutSection(): void
    {
        $this->assertSame(
            "# Title\n\nbody\n\n## After\n\nkept",
            MarkdownHelper::withoutSection(
                "# Title\n\nbody\n\n## Commit log\n\n- abc123\n\n## After\n\nkept",
                'Commit log'
            )
        );
    }

    /**
     * The section runs to the next heading of its own rank or above, so a
     * heading under it goes with it.
     */
    public function testWithoutSectionTakesItsSubHeadings(): void
    {
        $this->assertSame(
            "# Title\n\n# After",
            MarkdownHelper::withoutSection(
                "# Title\n\n## Dropped\n\n### Under it\n\ntext\n\n# After",
                'Dropped'
            )
        );
    }

    public function testWithoutSectionIgnoresCase(): void
    {
        $this->assertSame(
            "# Title\n",
            MarkdownHelper::withoutSection("# Title\n\n## COMMIT LOG\n\n- abc123", 'Commit log')
        );
    }

    /**
     * The reason the whole helper walks lines rather than running a regular
     * expression: a shell block is full of lines opening on a hash.
     */
    public function testWithoutSectionLeavesFencedCodeAlone(): void
    {
        $markdown = "# Title\n\n```bash\n# Commit log\necho ok\n```\n\nkept";

        $this->assertSame($markdown, MarkdownHelper::withoutSection($markdown, 'Commit log'));
    }

    public function testWithoutSectionAcrossNestedFences(): void
    {
        $markdown = "````\n~~~\n# Dropped\n~~~\n````\n\n## Dropped\n\ngone";

        $this->assertSame(
            "````\n~~~\n# Dropped\n~~~\n````\n",
            MarkdownHelper::withoutSections($markdown, ['Dropped'])
        );
    }

    public function testWithoutSectionsDropsEachOfThem(): void
    {
        $this->assertSame(
            "# Title\n",
            MarkdownHelper::withoutSections(
                "# Title\n\n## A\n\none\n\n## B\n\ntwo",
                ['A', 'B']
            )
        );
    }

    public function testWithoutSectionsWithNothingToDrop(): void
    {
        $markdown = "# Title\n\n## A\n\none";

        $this->assertSame($markdown, MarkdownHelper::withoutSections($markdown, []));
    }
}
