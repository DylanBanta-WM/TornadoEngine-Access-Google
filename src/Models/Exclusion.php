<?php

namespace TornadoEngine\AccessGoogle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone the sync leaves out: no account made, no roles given, and the roles it gave taken back.
 */
class Exclusion extends Model
{
    public const REASONS = ['Employment ended', 'Service account'];

    protected $table = 'access_google_exclusions';

    protected $fillable = ['email', 'reason', 'excluded_by'];

    public function excludedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluded_by');
    }
}
