<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'ticket',
        'subject',
        'status',
        'last_reply',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Status helpers
    public function isOpen()
    {
        return $this->status === 0;
    }

    public function isAnswered()
    {
        return $this->status === 1;
    }

    public function isReplied()
    {
        return $this->status === 2;
    }

    public function isClosed()
    {
        return $this->status === 3;
    }
}
