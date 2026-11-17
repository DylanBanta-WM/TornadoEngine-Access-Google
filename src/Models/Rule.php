<?php

namespace TornadoEngine\AccessGoogle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TornadoEngine\Access\Models\Role;

/**
 * Gives a role to a Google user ("user"), a Google Group's members ("group"),
 * or the people in an organizational unit ("ou", with its sub-OUs if sub_ous).
 */
class Rule extends Model
{
    protected $table = 'access_google_rules';

    protected $fillable = ['role_id', 'type', 'value', 'sub_ous', 'primary', 'matched'];

    /** How the rule reads on the page, for example: the OU /Students. */
    public function describe(): string
    {
        return __('the :type :value', ['type' => __(self::TYPES[$this->type]), 'value' => $this->value]);
    }

    public const TYPES = ['user' => 'Google user', 'group' => 'Google Group', 'ou' => 'OU'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Whether a person in this OU path is covered by this (OU) rule.
     */
    public function coversOu(string $ou): bool
    {
        $path = rtrim($this->value, '/');

        return strcasecmp(rtrim($ou, '/'), $path) === 0 || ($this->sub_ous && ($path === '' || stripos($ou, $path.'/') === 0));
    }

    protected function casts(): array
    {
        return ['sub_ous' => 'boolean', 'primary' => 'boolean', 'matched' => 'integer'];
    }
}
