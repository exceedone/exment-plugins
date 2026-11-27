<?php

namespace App\Plugins\ExmentGroupware\Calendar;

/** A fetch of an outside calendar that failed (IcsFetch); its message is a code the page translates, never the address. */
class IcsFetchError extends \RuntimeException
{
}
