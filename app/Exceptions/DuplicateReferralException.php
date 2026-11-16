<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when a referral number is already used by the same user on the same day.
 */
class DuplicateReferralException extends DomainException
{
}
