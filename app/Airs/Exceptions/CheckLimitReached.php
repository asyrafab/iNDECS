<?php

declare(strict_types=1);

namespace App\Airs\Exceptions;

use RuntimeException;

/** The application already has its two completed paid checks. */
final class CheckLimitReached extends RuntimeException {}
