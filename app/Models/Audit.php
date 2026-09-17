<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity;

/**
 * An audit of a change on a model. It does not extend BaseModel, so saving an audit never records an audit of itself.
 */
class Audit extends Activity {}
