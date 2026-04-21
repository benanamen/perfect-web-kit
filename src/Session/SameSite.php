<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Session;

/**
 * SameSite cookie attribute values accepted by the browser and PHP session API.
 */
enum SameSite: string
{
    case Lax = 'Lax';
    case Strict = 'Strict';
    case None = 'None';
}
