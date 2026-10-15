<?php

namespace App\Models;

use App\Core\Identity\Models\User as CoreUser;

/**
 * Class User
 * Fallback & framework compatibility wrapper pointing to App\Core\Identity\Models\User.
 */
class User extends CoreUser
{
}
