<?php

namespace Tests\Feature;

use App\Mail\ShopMessage;
use App\Models\EmailMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.mailgun.handler_token' => 'test-handler-token']);
    }

    public function test_guests_cannot_view_messages(): void
    {
        $this->getJson('/api/messages')->assertForbidden();
        $this->postJson('/api/messages', [
            'to_email' => 'ada@example.com',
            'subject' => 'Hello',
            'body' => 'Body',
        ])->assertForbidden();
    }

    public function test_super_admin_can_list_messages_synced_from_mailgun(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        EmailMessage::factory()->create([
            'subject' => 'Oil change reminder',
            'html_body' => '<p>secret body</p>',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.subject', 'Oil change reminder')
            ->assertJsonMissingPath('messages.0.html_body');
    }

    public function test_viewing_a_message_marks_it_read_and_returns_the_body(): void
    {
        $actor = $this->messageUser(['messages.view']);
        $message = EmailMessage::factory()->create([
            'is_read' => false,
            'text_body' => 'The van is ready.',
        ]);

        $this->actingAs($actor)
            ->getJson("/api/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('message.text_body', 'The van is ready.')
            ->assertJsonPath('message.is_read', true);

        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_authorized_user_sends_a_message_through_the_mailgun_api(): void
    {
        Mail::fake();
        config([
            'services.mailgun.secret' => 'key-test',
            'services.mailgun.domain' => 'sandbox.mailgun.org',
        ]);

        $actor = $this->messageUser(['messages.create']);

        $this->actingAs($actor)->postJson('/api/messages', [
            'to_email' => 'ada@example.com',
            'to_name' => 'Ada Lovelace',
            'subject' => 'Estimate ready',
            'body' => 'Your estimate is ready for review.',
        ])->assertAccepted()
            ->assertJsonPath('email.text_body', 'Your estimate is ready for review.')
            ->assertJsonPath('email.subject', 'Estimate ready');

        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'ada@example.com',
            'subject' => 'Estimate ready',
            'text_body' => 'Your estimate is ready for review.',
        ]);

        Mail::assertSent(ShopMessage::class, function (ShopMessage $mail): bool {
            return $mail->hasTo('ada@example.com')
                && $mail->messageSubject === 'Estimate ready'
                && $mail->messageBody === 'Your estimate is ready for review.';
        });
    }

    public function test_sending_without_mailgun_credentials_does_not_send_mail(): void
    {
        Mail::fake();
        config([
            'services.mailgun.secret' => null,
            'services.mailgun.domain' => null,
        ]);

        $actor = $this->messageUser(['messages.create']);

        $this->actingAs($actor)->postJson('/api/messages', [
            'to_email' => 'ada@example.com',
            'subject' => 'Estimate ready',
            'body' => 'Your estimate is ready for review.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Mailgun API credentials are not configured.');

        Mail::assertNothingSent();
    }

    public function test_mail_handler_imports_mailgun_messages_and_keeps_local_read_state(): void
    {
        $this->postJson('/api/internal/mailgun/messages', [
            'messages' => [],
        ])->assertUnauthorized();

        $this->withHeader('X-Mail-Handler-Token', 'wrong-token')
            ->postJson('/api/internal/mailgun/messages', [
                'messages' => [],
            ])->assertUnauthorized();

        $existing = EmailMessage::factory()->create([
            'mailgun_id' => '92',
            'subject' => 'Old subject',
            'is_read' => true,
        ]);

        $this->withHeader('X-Mail-Handler-Token', 'test-handler-token')
            ->postJson('/api/internal/mailgun/messages', [
                'messages' => [
                    [
                        'mailgun_id' => 92,
                        'subject' => 'Updated subject',
                        'from_email' => 'shop@example.com',
                        'from_name' => 'Auto Care',
                        'to_email' => 'ada@example.com',
                        'to_name' => 'Ada',
                        'text_body' => 'Plain copy',
                        'html_body' => '<p>Html copy</p>',
                        'sent_at' => '2026-10-10T04:00:00Z',
                        'is_read' => false,
                    ],
                    [
                        'mailgun_id' => '93',
                        'subject' => 'New message',
                        'from_email' => 'vendor@example.com',
                        'to_email' => 'shop@example.com',
                        'text_body' => 'Parts arrived',
                        'sent_at' => '2026-10-10T05:00:00Z',
                        'is_read' => false,
                        'delivery_note' => 'Free accounts are for test purposes only.',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $existing->refresh();
        $this->assertSame('Updated subject', $existing->subject);
        $this->assertTrue($existing->is_read);
        $this->assertSame('<p>Html copy</p>', $existing->html_body);

        $created = EmailMessage::query()->where('mailgun_id', '93')->firstOrFail();
        $this->assertSame('New message', $created->subject);
        $this->assertFalse($created->is_read);
        $this->assertSame('Parts arrived', $created->text_body);
        $this->assertSame('Free accounts are for test purposes only.', $created->delivery_note);

        $this->withHeader('X-Mail-Handler-Token', 'test-handler-token')
            ->postJson('/api/internal/mailgun/messages', [
                'messages' => [
                    [
                        'mailgun_id' => '93',
                        'subject' => 'New message',
                        'delivery_note' => 'Free accounts are for test purposes only.',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame('Parts arrived', $created->fresh()->text_body);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function messageUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
