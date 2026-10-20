<?php

namespace App\Http\Controllers;

use App\Services\EmailMessageImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailgunIngestController extends Controller
{
    public function store(Request $request, EmailMessageImporter $importer): JsonResponse
    {
        $messages = $request->input('messages', []);

        if (is_array($messages)) {
            $request->merge([
                'messages' => array_map(function (mixed $message): mixed {
                    if (! is_array($message) || ! array_key_exists('mailgun_id', $message)) {
                        return $message;
                    }

                    $message['mailgun_id'] = (string) $message['mailgun_id'];

                    return $message;
                }, $messages),
            ]);
        }

        $validated = $request->validate([
            'messages' => ['present', 'array', 'max:100'],
            'messages.*.mailgun_id' => ['required', 'string', 'max:255'],
            'messages.*.subject' => ['nullable', 'string', 'max:998'],
            'messages.*.from_email' => ['nullable', 'string', 'max:255'],
            'messages.*.from_name' => ['nullable', 'string', 'max:255'],
            'messages.*.to_email' => ['nullable', 'string', 'max:255'],
            'messages.*.to_name' => ['nullable', 'string', 'max:255'],
            'messages.*.text_body' => ['nullable', 'string', 'max:200000'],
            'messages.*.html_body' => ['nullable', 'string', 'max:200000'],
            'messages.*.sent_at' => ['nullable', 'date'],
            'messages.*.is_read' => ['nullable', 'boolean'],
            'messages.*.delivery_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'imported' => $importer->import($validated['messages']),
        ]);
    }
}
