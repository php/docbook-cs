<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Source\File;

final class SimparaSniff extends AbstractSniff implements Fixable
{
    private const string ELEMENT_NAME = 'para';
    private const string REPORTING_MESSAGE = '<para> contains only inline content and should be <simpara>.';
    private const string PARA_TAG_PATTERN = '/<\/?para\b[^>]*>/';

    private const array SIMPARA_ALLOWED = [
        'abbrev',
        'accel',
        'acronym',
        'alt',
        'anchor',
        'annotation',
        'application',
        'author',
        'biblioref',
        'buildtarget',
        'citation',
        'citebiblioid',
        'citerefentry',
        'citetitle',
        'classname',
        'code',
        'command',
        'computeroutput',
        'constant',
        'coref',
        'database',
        'date',
        'editor',
        'email',
        'emphasis',
        'enumidentifier',
        'enumname',
        'enumvalue',
        'envar',
        'errorcode',
        'errorname',
        'errortext',
        'errortype',
        'exceptionname',
        'filename',
        'firstterm',
        'footnote',
        'footnoteref',
        'foreignphrase',
        'function',
        'glossterm',
        'guibutton',
        'guiicon',
        'guilabel',
        'guimenu',
        'guimenuitem',
        'guisubmenu',
        'hardware',
        'indexterm',
        'info',
        'initializer',
        'inlineequation',
        'inlinemediaobject',
        'interfacename',
        'jobtitle',
        'keycap',
        'keycode',
        'keycombo',
        'keysym',
        'link',
        'literal',
        'macroname',
        'markup',
        'menuchoice',
        'methodname',
        'modifier',
        'mousebutton',
        'nonterminal',
        'olink',
        'ooclass',
        'ooexception',
        'oointerface',
        'option',
        'optional',
        'org',
        'orgname',
        'package',
        'parameter',
        'person',
        'personname',
        'phrase',
        'productname',
        'productnumber',
        'prompt',
        'property',
        'quote',
        'remark',
        'replaceable',
        'returnvalue',
        'revnumber',
        'shortcut',
        'subscript',
        'superscript',
        'symbol',
        'systemitem',
        'tag',
        'templatename',
        'termdef',
        'token',
        'trademark',
        'type',
        'typedefname',
        'unionname',
        'uri',
        'userinput',
        'varname',
        'wordasword',
        'xref',
    ];

    public static function getCode(): string
    {
        return 'DocbookCS.Simpara';
    }

    public static function getFixerClassName(): string
    {
        return SimparaFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \LogicException if a source match cannot be mapped
     * @throws \OutOfBoundsException if a matched tag offset lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];
        $sourceMatchIndex = 0;

        $paras = $document->getElementsByTagName('para');
        if ($paras->length === 0) {
            return [];
        }

        $sourceMatches = $this->sourceMatches($file);
        $allowed = $this->getAllowedElements();

        /** @var \DOMElement $para */
        foreach ($paras as $para) {
            if (!$this->isSourceBacked($para)) {
                continue;
            }

            $match = $sourceMatches[$sourceMatchIndex] ?? null;
            $sourceMatchIndex++;

            if ($match === null) {
                throw new \LogicException('Could not map simpara violation to source content.');
            }

            $closingOffset = $match['closingOffset'];
            if (null === $closingOffset) {
                continue;
            }

            $parent = $para->parentNode;
            if (
                $parent instanceof \DOMElement
                && strtolower($parent->localName ?? '') === 'formalpara'
            ) {
                continue;
            }

            if (!$this->isSimple($para, $allowed)) {
                continue;
            }

            $affectedRanges = $this->elementNameRanges(
                $file,
                $match['beginOffset'],
                $closingOffset,
                self::ELEMENT_NAME,
            );

            $violations[] = $this->createViolation(
                $file->path,
                self::REPORTING_MESSAGE,
                $affectedRanges,
            );
        }

        return $violations;
    }

    /**
     * @param list<string> $allowed
     */
    private function isSimple(\DOMElement $node, array $allowed): bool
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName ?: '');

            if (!in_array($name, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function getAllowedElements(): array
    {
        $extra = $this->getProperty('additionalInlineElements');

        if ($extra === '') {
            return self::SIMPARA_ALLOWED;
        }

        $additional = array_map('trim', explode(',', $extra));
        $additional = array_filter($additional, static fn(string $s): bool => $s !== '');

        return array_values(array_unique(array_merge(self::SIMPARA_ALLOWED, $additional)));
    }

    /**  @return list<array{beginOffset: int, closingOffset: int|null}> */
    private function sourceMatches(File $file): array
    {
        preg_match_all(
            self::PARA_TAG_PATTERN,
            $file->contentWithNonElementMarkupMasked(),
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        /** @var list<int> $stack */
        $stack = [];
        $sourceMatches = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $offset = (int) $offset;

            if (str_ends_with(rtrim($tag), '/>')) {
                $sourceMatches[] = [
                    'beginOffset' => $offset,
                    'closingOffset' => null,
                ];
                continue;
            }

            if (!str_starts_with($tag, '</')) {
                $stack[] = $offset;
                continue;
            }

            if (null === $opening = array_pop($stack)) {
                continue;
            }

            $sourceMatches[] = [
                'beginOffset' => $opening,
                'closingOffset' => $offset,
            ];
        }

        usort($sourceMatches, static fn(array $a, array $b): int => $a['beginOffset'] <=> $b['beginOffset']);

        return $sourceMatches;
    }
}
