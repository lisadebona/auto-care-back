<?php

namespace App\Http\Controllers;

use App\Mail\ShopMessage;
use App\Models\EmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;

class EmailMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = EmailMessage::query()
            ->select([
                'id',
                'mailgun_id',
                'subject',
                'from_email',
                'from_name',
                'to_email',
                'to_name',
                'sent_at',
                'is_read',
                'synced_at',
            ])
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_email', 'like', "%{$search}%")
                        ->orWhere('from_name', 'like', "%{$search}%")
                        ->orWhere('to_email', 'like', "%{$search}%")
                        ->orWhere('to_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function show(EmailMessage $emailMessage): JsonResponse
    {
        if (! $emailMessage->is_read) {
            $emailMessage->forceFill(['is_read' => true])->save();
        }

        return response()->json(['message' => $emailMessage]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_email' => ['required', 'email', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $secret = config('services.mailgun.secret');
        $domain = config('services.mailgun.domain');

        if (! is_string($secret) || $secret === '' || ! is_string($domain) || $domain === '') {
            return response()->json([
                'message' => 'Mailgun API credentials are not configured.',
            ], 422);
        }

        try {
            $sent = Mail::mailer('mailgun')
                ->to($validated['to_email'], $validated['to_name'] ?? null)
                ->send(new ShopMessage($validated['subject'], $validated['body']));
        } catch (TransportExceptionInterface) {
            $email = $this->rememberSentMessage($validated, null, 'Mailgun did not accept this message.');

            return response()->json([
                'message' => 'Mailgun did not accept this message.',
                'email' => $email,
            ], 502);
        }

        $email = $this->rememberSentMessage($validated, $sent instanceof SentMessage ? $sent : null);

        return response()->json([
            'message' => 'Message sent through Mailgun.',
            'email' => $email,
        ], 202);
    }

    /**
     * @param  array{to_email: string, to_name?: string|null, subject: string, body: string}  $validated
     */
    private function rememberSentMessage(array $validated, ?SentMessage $sent, ?string $deliveryNote = null): EmailMessage
    {
        $mailgunId = trim((string) $sent?->getMessageId(), "<> \t\n\r");

        return EmailMessage::query()->updateOrCreate(
            ['mailgun_id' => $mailgunId !== '' ? $mailgunId : (string) Str::uuid()],
            [
                'subject' => $validated['subject'],
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_email' => $validated['to_email'],
                'to_name' => $validated['to_name'] ?? null,
                'text_body' => $validated['body'],
                'sent_at' => now(),
                'is_read' => true,
                'synced_at' => now(),
                'delivery_note' => $deliveryNote,
            ],
        );
    }
}
