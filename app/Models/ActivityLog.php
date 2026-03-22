<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'entity',
        'entity_id',
        'ip_address',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the activity log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log user activity.
     *
     * @param int $userId
     * @param string $action
     * @param string $entity
     * @param int $entityId
     * @param string|null $ipAddress
     * @return static
     */
    public static function log($userId, $action, $entity, $entityId, $ipAddress = null)
    {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'ip_address' => $ipAddress,
        ]);
    }
}