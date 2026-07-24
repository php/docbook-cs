<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class AttributeOrderFixer implements Fixer
{
    private const string OPENING_TAG_FORMAT = '<%s%s>';
    private const string OPENING_TAG_PATTERN = '/^<([a-zA-Z0-9:_-]+)\b([^<>]*?)>$/';
    private const string ATTRIBUTE_TOKEN_PATTERN = '/\s+([a-zA-Z0-9:_-]+)\s*=\s*(?:"[^"]*"|\'[^\']*\')/';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::OPENING_TAG_PATTERN, $violation->content, $matches)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        $fixedAttributeString = $this->fixedAttributeString($matches[2]);

        if ($fixedAttributeString === null) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            $violation->filePath,
            $violation->beginOffset,
            $violation->untilOffset,
            sprintf(self::OPENING_TAG_FORMAT, $matches[1], $fixedAttributeString),
            $violation->sniffCode,
        );
    }

    private function fixedAttributeString(string $attrString): ?string
    {
        if (!str_contains($attrString, 'xml:id') || !str_contains($attrString, 'xmlns')) {
            return null;
        }

        preg_match_all(self::ATTRIBUTE_TOKEN_PATTERN, $attrString, $matches, PREG_OFFSET_CAPTURE);

        $xmlIdToken = null;
        $firstXmlnsStart = null;

        foreach ($matches[0] as $i => [$token, $start]) {
            $start = (int) $start;
            $name = $matches[1][$i][0];

            if ($name === 'xml:id') {
                $xmlIdToken = [
                    'text' => $token,
                    'start' => $start,
                    'end' => $start + strlen($token),
                ];
            }

            if (($name === 'xmlns' || str_starts_with($name, 'xmlns:')) && $firstXmlnsStart === null) {
                $firstXmlnsStart = $start;
            }
        }

        if ($xmlIdToken === null || $firstXmlnsStart === null || $xmlIdToken['start'] < $firstXmlnsStart) {
            return null;
        }

        return substr($attrString, 0, $firstXmlnsStart)
            . $xmlIdToken['text']
            . substr($attrString, $firstXmlnsStart, $xmlIdToken['start'] - $firstXmlnsStart)
            . substr($attrString, $xmlIdToken['end']);
    }
}
