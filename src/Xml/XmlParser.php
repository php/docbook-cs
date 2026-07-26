<?php

declare(strict_types=1);

namespace DocbookCS\Xml;

final class XmlParser
{
    public function parseDocument(string $content): \DOMDocument|\LibXMLError
    {
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = true;

        [$loaded, $error] = $this->parseWithErrors(
            static fn(): bool => $document->loadXML($content, LIBXML_NONET),
        );

        if ($loaded) {
            return $document;
        }

        return $this->parseError($error);
    }

    public function parseElement(string $content): \SimpleXMLElement|\LibXMLError
    {
        [$element, $error] = $this->parseWithErrors(
            static fn(): \SimpleXMLElement|false => simplexml_load_string($content),
        );

        if ($element !== false) {
            return $element;
        }

        return $this->parseError($error);
    }

    /**
     * @template TResult
     * @param callable(): TResult $parser
     * @return array{TResult, ?\LibXMLError}
     */
    private function parseWithErrors(callable $parser): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $result = $parser();
            $error = libxml_get_errors()[0] ?? null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }

        return [$result, $error];
    }

    private function parseError(?\LibXMLError $error): \LibXMLError
    {
        $error ??= new \LibXMLError();
        $error->message ??= 'Unknown parse error';
        $error->line ??= 0;

        return $error;
    }
}
