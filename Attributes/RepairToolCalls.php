<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * When the model calls a tool that does not exist, tell it which tools do instead of failing the run.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RepairToolCalls
{
}
