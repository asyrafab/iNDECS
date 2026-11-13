<?php

declare(strict_types=1);

namespace App\Airs\Exceptions;

use RuntimeException;

/** A check for this application is already queued or running. */
final class CheckInProgress extends RuntimeException
{
}
