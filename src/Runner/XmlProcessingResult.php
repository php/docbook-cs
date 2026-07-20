<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Source\File;

final readonly class XmlProcessingResult
{
    public function __construct(
        public FileReport $fileReport,
        public File $initialFile,
        public File $currentFile,
    ) {
    }

    public function isModified(): bool
    {
        return $this->initialFile->content !== $this->currentFile->content;
    }

    /** @throws FixerException */
    public function fixedContent(): string
    {
        if (!$this->isModified()) {
            throw FixerException::cannotReadFixedContent();
        }

        return $this->currentFile->content;
    }
}
