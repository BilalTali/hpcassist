<?php

namespace App\Http\Controllers;

/**
 * StateController is an alias for StageController to support both
 * naming conventions seamlessly across routes, CLI commands, and extensions.
 */
class StateController extends StageController
{
    // Inherits all stage management, template streaming, and upload methods from StageController
}
