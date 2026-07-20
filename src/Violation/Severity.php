<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

enum Severity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
}
