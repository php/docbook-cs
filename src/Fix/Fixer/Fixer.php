<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

interface Fixer
{
    /** @throws FixerException */
    public function process(Violation $violation): Fix|FixPlan;
}
