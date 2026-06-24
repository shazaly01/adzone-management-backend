<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'phone',
        'type',
        'status',
        'beneficiary_id',
        'area_id',
        'sender_id',
        'error_log',
    ];


    /**
     * المستخدم (الموظف) الذي قام بإرسال الرسالة
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
