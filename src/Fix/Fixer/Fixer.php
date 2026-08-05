<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

/** @template TFixerData = mixed */
interface Fixer
{
    /**
     * @param Violation<TFixerData> $violation
     * @throws FixerException
     */
    public function process(Violation $violation): Fix|FixPlan;
}
