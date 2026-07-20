<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Source\File;
use DocbookCS\Violation\Violation;

final readonly class ViolationScopeFilter
{
    /**
     * @param list<Violation> $violations
     * @return list<Violation>
     */
    public function filter(array $violations, \DOMDocument $document, File $file, SourceScope $scope): array
    {
        if ($scope->isWholeFile()) {
            return $violations;
        }

        /** @var array<int, true> $changedLineSet */
        $changedLineSet = array_fill_keys($scope->lineNumbers($file), true);

        return array_values(array_filter(
            $violations,
            fn(Violation $violation) => $scope->includes($violation)
                || (
                    $violation->content === null
                    && $violation->beginOffset === $violation->untilOffset
                    && $this->isRelevant($violation, $document, $changedLineSet)
                ),
        ));
    }

    /** @param array<int, true> $changedLineSet */
    private function isRelevant(Violation $violation, \DOMDocument $document, array $changedLineSet): bool
    {
        if (isset($changedLineSet[$violation->line])) {
            return true;
        }

        $violationElement = $this->firstElementOnLine($document, $violation->line);
        if ($violationElement === null) {
            return false;
        }

        $endLine = $this->computeElementEndLine($violationElement);

        foreach ($changedLineSet as $changedLine => $_) {
            $owner = $this->innermostContaining($violationElement, $changedLine, $endLine);
            if ($owner === $violationElement) {
                return true;
            }

            if ($owner !== null && $owner->parentNode === $violationElement) {
                return true;
            }
        }

        return false;
    }

    private function firstElementOnLine(\DOMDocument $document, int $line): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->getLineNo() === $line) {
                return $element;
            }
        }

        return null;
    }

    private function innermostContaining(\DOMElement $element, int $line, int $endLine): ?\DOMElement
    {
        if ($line > $endLine || $line < $element->getLineNo()) {
            return null;
        }

        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        $count = count($children);
        foreach ($children as $i => $child) {
            $childEnd = $endLine;
            for ($j = $i + 1; $j < $count; $j++) {
                $nextLine = $children[$j]->getLineNo();
                if ($nextLine > $child->getLineNo()) {
                    $childEnd = $nextLine - 1;
                    break;
                }
            }
            $childEnd = min($childEnd, $this->computeElementEndLine($child));

            $deeper = $this->innermostContaining($child, $line, $childEnd);
            if ($deeper !== null) {
                return $deeper;
            }
        }

        return $element;
    }

    private function computeElementEndLine(\DOMElement $element): int
    {
        $max = $element->getLineNo();

        foreach ($element->childNodes as $child) {
            $line = $child->getLineNo();
            if ($line > $max) {
                $max = $line;
            }

            if ($child instanceof \DOMElement) {
                $childEnd = $this->computeElementEndLine($child);
                if ($childEnd > $max) {
                    $max = $childEnd;
                }
            }
        }

        return $max;
    }
}
