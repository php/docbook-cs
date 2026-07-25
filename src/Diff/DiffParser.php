<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final class DiffParser
{
    private const string NO_FINAL_LINE_MARKER = '\ No newline at end of file';

    public function parse(string $diff): Diff
    {
        /** @var array<string, list<int>> $changedLinesByFile */
        $changedLinesByFile = [];
        /** @var array<string, list<int>> $deletionAnchorsByFile */
        $deletionAnchorsByFile = [];
        $currentFile = null;
        $deleted = false;
        $newLineNumber = 0;
        $oldLinesRemaining = 0;
        $newLinesRemaining = 0;
        $inHunk = false;
        $previousLineWasDeletion = false;

        foreach (explode("\n", $diff) as $line) {
            if (str_starts_with($line, 'diff --git ')) {
                $currentFile = null;
                $deleted = false;
                $newLineNumber = 0;
                $inHunk = false;
                $previousLineWasDeletion = false;
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
                if ($currentFile !== null && !isset($changedLinesByFile[$currentFile])) {
                    $changedLinesByFile[$currentFile] = [];
                    $deletionAnchorsByFile[$currentFile] = [];
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
                    $previousLineWasDeletion = false;
                }
                continue;
            }

            if (!$inHunk || $line === self::NO_FINAL_LINE_MARKER) {
                continue;
            }

            if (str_starts_with($line, '+')) {
                $changedLinesByFile[$currentFile][] = $newLineNumber;
                $newLineNumber++;
                $newLinesRemaining--;
                $previousLineWasDeletion = false;
            } elseif (str_starts_with($line, '-')) {
                if (!$previousLineWasDeletion) {
                    $deletionAnchorsByFile[$currentFile][] = max(1, $newLineNumber);
                }

                $oldLinesRemaining--;
                $previousLineWasDeletion = true;
            } elseif (str_starts_with($line, ' ')) {
                // Context line — present in both old and new file.
                $newLineNumber++;
                $oldLinesRemaining--;
                $newLinesRemaining--;
                $previousLineWasDeletion = false;
            }

            if ($oldLinesRemaining === 0 && $newLinesRemaining === 0) {
                $inHunk = false;
                $previousLineWasDeletion = false;
            }
        }

        $fileChanges = [];

        foreach ($changedLinesByFile as $filePath => $lineNumbers) {
            $fileChanges[] = new FileChange($filePath, $lineNumbers, $deletionAnchorsByFile[$filePath]);
        }

        return new Diff($fileChanges);
    }
}
