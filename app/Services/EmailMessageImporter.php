<?php

namespace App\Services;

use App\Models\EmailMessage;
use Illuminate\Support\Carbon;

class EmailMessageImporter
{
    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function import(array $messages): int
    {
        foreach ($messages as $message) {
            $mailgunId = (string) $message['mailgun_id'];
            $existing = EmailMessage::query()->where('mailgun_id', $mailgunId)->first();

            $attributes = [
                'subject' => $message['subject'] ?? null,
                'from_email' => $message['from_email'] ?? null,
                'from_name' => $message['from_name'] ?? null,
                'to_email' => $message['to_email'] ?? null,
                'to_name' => $message['to_name'] ?? null,
                'synced_at' => now(),
                'is_read' => ($existing?->is_read ?? false) || (bool) ($message['is_read'] ?? false),
            ];

            if (filled($message['text_body'] ?? null)) {
                $attributes['text_body'] = $message['text_body'];
            }

            if (filled($message['html_body'] ?? null)) {
                $attributes['html_body'] = $message['html_body'];
            }

            if (filled($message['sent_at'] ?? null)) {
                $attributes['sent_at'] = Carbon::parse($message['sent_at']);
            }

            if (filled($message['delivery_note'] ?? null)) {
                $attributes['delivery_note'] = $message['delivery_note'];
            }

            EmailMessage::query()->updateOrCreate(
                ['mailgun_id' => $mailgunId],
                $attributes,
            );
        }

        return count($messages);
    }
}
