<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final class DiffParser
{
    private const string NO_FINAL_LINE_MARKER = '\ No newline at end of file';

    /** @return array<string, list<int>> */
    public function parse(string $diff): array
    {
        $result = [];
        $currentFile = null;
        $deleted = false;
        $newLineNumber = 0;
        $oldLinesRemaining = 0;
        $newLinesRemaining = 0;
        $inHunk = false;

        foreach (explode("\n", $diff) as $line) {
            if (str_starts_with($line, 'diff --git ')) {
                $currentFile = null;
                $deleted = false;
                $newLineNumber = 0;
                $inHunk = false;
                continue;
            }

            if (str_starts_with($line, 'deleted file mode')) {
                $deleted = true;
                continue;
            }

            // Target file header: "+++ b/path" or "+++ /dev/null"
            if (str_starts_with($line, '+++ ') && !$deleted) {
                $path = rtrim(substr($line, 4));
                if (str_starts_with($path, 'b/')) {
                    $path = substr($path, 2);
                }
                $currentFile = $path !== '/dev/null' ? $path : null;
                $inHunk = false;
                if ($currentFile !== null && !isset($result[$currentFile])) {
                    $result[$currentFile] = [];
                }
                continue;
            }

            if ($currentFile === null) {
                continue;
            }

            // Hunk header: @@ -old_start[,old_count] +new_start[,new_count] @@
            if (str_starts_with($line, '@@ ')) {
                if (preg_match('/^@@ -\d+(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $line, $m, PREG_UNMATCHED_AS_NULL)) {
                    $oldLinesRemaining = isset($m[1]) ? (int) $m[1] : 1;
                    $newLineNumber = (int) $m[2];
                    $newLinesRemaining = isset($m[3]) ? (int) $m[3] : 1;
                    $inHunk = true;
                }
                continue;
            }

            if (!$inHunk || $line === self::NO_FINAL_LINE_MARKER) {
                continue;
            }

            if (str_starts_with($line, '+')) {
                $result[$currentFile][] = $newLineNumber;
                $newLineNumber++;
                $newLinesRemaining--;
            } elseif (str_starts_with($line, '-')) {
                $oldLinesRemaining--;
            } elseif (str_starts_with($line, ' ')) {
                // Context line — present in both old and new file.
                $newLineNumber++;
                $oldLinesRemaining--;
                $newLinesRemaining--;
            }

            if ($oldLinesRemaining === 0 && $newLinesRemaining === 0) {
                $inHunk = false;
            }
        }

        return $result;
    }
}
