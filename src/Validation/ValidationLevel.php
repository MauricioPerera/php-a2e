<?php
declare(strict_types=1);
namespace PHPA2E\Validation;

enum ValidationLevel: string
{
    case Strict = 'strict';
    case Moderate = 'moderate';
    case Lenient = 'lenient';
}
