<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * Use the provider's cheapest text model. The model this picks can change between releases.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class UseCheapestModel
{
}
