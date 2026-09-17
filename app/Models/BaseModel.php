<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * The parent of every model in the application. Any model that extends it records audits.
 */
abstract class BaseModel extends Model
{
    use Auditable;
}
