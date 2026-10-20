<?php

namespace App\Models;

use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mailgun_id',
    'subject',
    'from_email',
    'from_name',
    'to_email',
    'to_name',
    'text_body',
    'html_body',
    'sent_at',
    'is_read',
    'synced_at',
    'delivery_note',
])]
class EmailMessage extends Model
{
    /** @use HasFactory<EmailMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }
}
