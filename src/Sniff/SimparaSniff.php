<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

final class SimparaSniff extends AbstractSniff
{
    private const array SIMPARA_ALLOWED = [
        'abbrev',
        'acronym',
        'action',
        'anchor',
        'application',
        'author',
        'authorinitials',
        'citation',
        'citerefentry',
        'citetitle',
        'classname',
        'cmdsynopsis',
        'command',
        'comment',
        'computeroutput',
        'constant',
        'corpauthor',
        'database',
        'email',
        'emphasis',
        'envar',
        'errorcode',
        'errorname',
        'errortype',
        'filename',
        'firstterm',
        'footnote',
        'footnoteref',
        'foreignphrase',
        'funcsynopsis',
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
        'inlineequation',
        'inlinegraphic',
        'inlinemediaobject',
        'interface',
        'interfacedefinition',
        'keycap',
        'keycode',
        'keycombo',
        'keysym',
        'link',
        'literal',
        'markup',
        'medialabel',
        'menuchoice',
        'modespec',
        'mousebutton',
        'msgtext',
        'olink',
        'option',
        'optional',
        'othercredit',
        'parameter',
        'phrase',
        'productname',
        'productnumber',
        'prompt',
        'property',
        'quote',
        'replaceable',
        'returnvalue',
        'revhistory',
        'sgmltag',
        'structfield',
        'structname',
        'subscript',
        'superscript',
        'symbol',
        'synopsis',
        'systemitem',
        'token',
        'trademark',
        'type',
        'ulink',
        'userinput',
        'varname',
        'wordasword',
        'xref',
    ];

    public function getCode(): string
    {
        return 'DocbookCS.Simpara';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $allowed = $this->getAllowedElements();

        $paras = $document->getElementsByTagName('para');

        /** @var \DOMElement $para */
        foreach ($paras as $para) {
            if (!$this->isSourceBacked($para)) {
                continue;
            }

            $parent = $para->parentNode;
            if (
                $parent instanceof \DOMElement
                && strtolower($parent->localName ?? '') === 'formalpara'
            ) {
                continue;
            }

            if ($this->isSimple($para, $allowed)) {
                $violations[] = $this->createViolation(
                    $filePath,
                    $para->getLineNo(),
                    '<para> contains only inline content and should be <simpara>.',
                );
            }
        }

        return $violations;
    }

    /**
     * @param list<string> $allowed
     */
    private function isSimple(\DOMElement $node, array $allowed): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $name = strtolower($child->localName ?: '');

                if (!in_array($name, $allowed, true)) {
                    return false;
                }
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
}
