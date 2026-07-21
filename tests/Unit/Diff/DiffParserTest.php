<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Diff;

use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\FileChange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(DiffParser::class),
    CoversClass(FileChange::class),
    //
    UsesClass(DiffChangeset::class),
]
final class DiffParserTest extends TestCase
{
    private DiffParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DiffParser();
    }

    #[Test]
    public function itReturnsEmptyArrayForEmptyDiff(): void
    {
        self::assertSame([], $this->parser->parse('')->fileChanges);
    }

    #[Test]
    public function itParsesAddedLineNumbers(): void
    {
        $diff = <<<'DIFF'
diff --git a/reference/file.xml b/reference/file.xml
--- a/reference/file.xml
+++ b/reference/file.xml
@@ -1,3 +1,4 @@
 line1
+new line
 line2
 line3
DIFF;

        $change = $this->parser->parse($diff)->changeFor('reference/file.xml');

        self::assertNotNull($change);
        self::assertSame([2], $change->addedLineNumbers);
    }

    #[Test]
    public function itParsesMultipleAddedLines(): void
    {
        $diff = <<<'DIFF'
diff --git a/doc/chapter.xml b/doc/chapter.xml
--- a/doc/chapter.xml
+++ b/doc/chapter.xml
@@ -5,4 +5,6 @@
 context
+first added
+second added
 more context
 last line
DIFF;

        $result = $this->parser->parse($diff);

        self::assertSame([6, 7], $result->changeFor('doc/chapter.xml')?->addedLineNumbers);
    }

    #[Test]
    public function itStripsTheBPrefix(): void
    {
        $diff = <<<'DIFF'
diff --git a/src/file.xml b/src/file.xml
--- a/src/file.xml
+++ b/src/file.xml
@@ -1,1 +1,2 @@
 existing
+added
DIFF;

        $result = $this->parser->parse($diff);

        self::assertCount(1, $result->fileChanges);
        self::assertSame('src/file.xml', $result->fileChanges[0]->filePath);
    }

    #[Test]
    public function itExcludesDeletedFiles(): void
    {
        $diff = <<<'DIFF'
diff --git a/removed.xml b/removed.xml
deleted file mode 100644
--- a/removed.xml
+++ /dev/null
@@ -1,3 +0,0 @@
-line1
-line2
-line3
DIFF;

        self::assertSame([], $this->parser->parse($diff)->fileChanges);
    }

    #[Test]
    public function itHandlesNewlyCreatedFiles(): void
    {
        $diff = <<<'DIFF'
diff --git a/new.xml b/new.xml
new file mode 100644
--- /dev/null
+++ b/new.xml
@@ -0,0 +1,3 @@
+line1
+line2
+line3
DIFF;

        $change = $this->parser->parse($diff)->changeFor('new.xml');

        self::assertNotNull($change);
        self::assertSame([1, 2, 3], $change->addedLineNumbers);
    }

    #[Test]
    public function itHandlesMultipleFilesInOneDiff(): void
    {
        $diff = <<<'DIFF'
diff --git a/first.xml b/first.xml
--- a/first.xml
+++ b/first.xml
@@ -1,2 +1,3 @@
 unchanged
+added in first
 unchanged
diff --git a/second.xml b/second.xml
--- a/second.xml
+++ b/second.xml
@@ -1,2 +1,3 @@
 unchanged
+added in second
 unchanged
DIFF;

        $result = $this->parser->parse($diff);
        $firstChange = $result->changeFor('first.xml');
        $secondChange = $result->changeFor('second.xml');

        self::assertNotNull($firstChange);
        self::assertNotNull($secondChange);
        self::assertSame([2], $firstChange->addedLineNumbers);
        self::assertSame([2], $secondChange->addedLineNumbers);
    }

    #[Test]
    public function itIgnoresRemovedLines(): void
    {
        $diff = <<<'DIFF'
diff --git a/file.xml b/file.xml
--- a/file.xml
+++ b/file.xml
@@ -1,4 +1,3 @@
 line1
-removed line
 line2
 line3
DIFF;

        $change = $this->parser->parse($diff)->changeFor('file.xml');

        // No lines added, so the changed set is empty (not absent — the file is tracked).
        self::assertNotNull($change);
        self::assertSame([], $change->addedLineNumbers);
    }

    #[Test]
    public function itAnchorsRemovedLinesInTheResultingFile(): void
    {
        $diff = <<<'DIFF'
diff --git a/file.xml b/file.xml
--- a/file.xml
+++ b/file.xml
@@ -1,4 +1,3 @@
 line1
-removed line
 line2
 line3
DIFF;

        $change = $this->parser->parse($diff)->changeFor('file.xml');
        self::assertNotNull($change);

        self::assertSame([2], $change->deletionAnchors);
    }

    #[Test]
    public function itIgnoresTheMissingFinalNewlineMarker(): void
    {
        $diff = <<<'DIFF'
diff --git a/file.xml b/file.xml
--- a/file.xml
+++ b/file.xml
@@ -1 +1,2 @@
-old
\ No newline at end of file
+new
+second
DIFF;

        $result = $this->parser->parse($diff);

        self::assertSame([1, 2], $result->changeFor('file.xml')?->addedLineNumbers);
    }

    #[Test]
    public function itAnchorsReplacedLinesWhenTheMissingFinalNewlineMarkerIsPresent(): void
    {
        $diff = <<<'DIFF'
diff --git a/file.xml b/file.xml
--- a/file.xml
+++ b/file.xml
@@ -1 +1,2 @@
-old
\ No newline at end of file
+new
+second
DIFF;

        $change = $this->parser->parse($diff)->changeFor('file.xml');
        self::assertNotNull($change);

        self::assertSame([1], $change->deletionAnchors);
    }

    #[Test]
    public function itTracksLineNumbersAcrossMultipleHunks(): void
    {
        $diff = <<<'DIFF'
diff --git a/file.xml b/file.xml
--- a/file.xml
+++ b/file.xml
@@ -1,3 +1,4 @@
 line1
+added at 2
 line2
 line3
@@ -10,3 +11,4 @@
 line10
+added at 12
 line11
 line12
DIFF;

        $result = $this->parser->parse($diff);

        self::assertSame([2, 12], $result->changeFor('file.xml')?->addedLineNumbers);
    }

    #[Test]
    public function itHandlesHunkWithNoContext(): void
    {
        $diff = <<<'DIFF'
diff --git a/file.xml b/file.xml
--- a/file.xml
+++ b/file.xml
@@ -0,0 +1 @@
+only line
DIFF;

        $result = $this->parser->parse($diff);

        self::assertSame([1], $result->changeFor('file.xml')?->addedLineNumbers);
    }
}
