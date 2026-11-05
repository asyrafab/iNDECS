<?php

declare(strict_types=1);

namespace App\Enums;

/** AMIRA's avatar mood on the public portal (spec §5). Presentation only. */
enum AmiraMood: string
{
    case Calm = 'calm';
    case Happy = 'happy';
    case Think = 'think';
    case Concern = 'concern';
    case Alert = 'alert';
}
