<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * Ask the provider to enforce tool argument schemas strictly where it supports that.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Strict
{
}
