<?php

declare(strict_types=1);

namespace App\Airs\Exceptions;

use RuntimeException;

/** A check cannot start; the message is a stable code (MODULE_NOT_ENABLED, LIGHTING_TYPES_REQUIRED, DOCUMENT_INTEGRITY, NOT_FAILED). */
final class CheckNotAllowed extends RuntimeException {}
