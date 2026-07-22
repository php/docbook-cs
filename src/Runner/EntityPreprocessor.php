<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

final class EntityPreprocessor
{
    private const array PREDEFINED = ['amp', 'lt', 'gt', 'quot', 'apos'];
    private const string ENTITY_PATTERN = '&([a-zA-Z_][\w.\-]*);';
    private const string XML_DECLARATION_PATTERN = '/<\?xml[^?]*\?>/i';

    /**
     * @param array<string, string> $entities
     */
    public function __construct(
        private array $entities,
    ) {
    }

    public function process(string $xml): string
    {
        $xml = $this->stripDoctype($xml);

        return $this->expandEntities($xml);
    }

    public function processForParsing(string $xml): string
    {
        $xml = $this->stripDoctype($xml);

        return $this->expandEntities($xml, markXmlExpansions: true);
    }

    private function expandEntities(string $content, bool $markXmlExpansions = false): string
    {
        $maxDepth = 20;

        for ($i = 0; $i < $maxDepth; $i++) {
            $changed = false;

            $content = preg_replace_callback(
                '/<!--[\s\S]*?-->|<!\[CDATA\[[\s\S]*?\]\]>|<\?[\s\S]*?\?>|' . self::ENTITY_PATTERN . '/',
                function (array $matches) use (&$changed, $markXmlExpansions): string {
                    if (
                        str_starts_with($matches[0], '<!--')
                        || str_starts_with($matches[0], '<![CDATA[')
                        || str_starts_with($matches[0], '<?')
                    ) {
                        return $matches[0];
                    }

                    $name = $matches[1] ?? null;
                    if (!$name || in_array($name, self::PREDEFINED, true)) {
                        return $matches[0];
                    }

                    if (!isset($this->entities[$name])) {
                        return $matches[0];
                    }

                    $changed = true;

                    $value = $this->stripXmlDeclaration($this->entities[$name]);

                    return $markXmlExpansions && $this->containsXmlElement($value)
                        ? EntityExpansionMarker::wrap($value)
                        : $value;
                },
                $content,
            ) ?: $content;

            if (!$changed) {
                break;
            }
        }

        return $content;
    }

    private function containsXmlElement(string $content): bool
    {
        return preg_match('/<\s*[a-zA-Z_][\w:.-]*(?:\s|\/?>)/', $content) === 1;
    }

    private function stripDoctype(string $xmlContent): string
    {
        $start = stripos($xmlContent, '<!DOCTYPE');

        if ($start === false) {
            return $xmlContent;
        }

        $length = strlen($xmlContent);
        $pos = $start + 9;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBracket = false;

        while ($pos < $length) {
            $char = $xmlContent[$pos];

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === '[' && !$inSingleQuote && !$inDoubleQuote) {
                $inBracket = true;
            } elseif ($char === ']' && !$inSingleQuote && !$inDoubleQuote) {
                $inBracket = false;
            } elseif ($char === '>' && !$inSingleQuote && !$inDoubleQuote && !$inBracket) {
                return substr($xmlContent, 0, $start)
                    . substr($xmlContent, $pos + 1);
            }

            $pos++;
        }

        return $xmlContent;
    }

    private function stripXmlDeclaration(string $xmlContent): string
    {
        return preg_replace(self::XML_DECLARATION_PATTERN, '', $xmlContent) ?: $xmlContent;
    }
}
