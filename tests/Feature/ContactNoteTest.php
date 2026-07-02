<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ContactNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Account $account;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner@example.com',
            'password' => bcrypt('secret'),
        ]);

        $agency = Agency::create([
            'owner_id' => $this->owner->id,
            'name'     => 'Painters',
            'slug'     => 'painters',
            'status'   => config('custom.agency.status_active'),
        ]);

        $this->account = $agency->accounts()->create([
            'name'   => 'Gulf Coast Painting',
            'slug'   => 'gulf-coast',
            'status' => config('custom.account.status_active'),
        ]);

        $this->contact = $this->account->contacts()->create([
            'first_name' => 'Jane',
            'last_name'  => 'Client',
        ]);

        Passport::actingAs($this->owner, ['*'], 'api');
    }

    private function notesUrl(?int $noteId = null): string
    {
        $base = "/api/accounts/{$this->account->id}/contacts/{$this->contact->id}/notes";

        return $noteId !== null ? "{$base}/{$noteId}" : $base;
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function test_notes_list_is_empty_for_new_contact(): void
    {
        $this->getJson($this->notesUrl())
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_notes_are_listed_newest_first(): void
    {
        $n1 = $this->contact->contactNotes()->create(['user_id' => $this->owner->id, 'body' => 'First']);
        $n2 = $this->contact->contactNotes()->create(['user_id' => $this->owner->id, 'body' => 'Second']);

        $data = $this->getJson($this->notesUrl())->assertOk()->json('data');

        $this->assertEquals($n2->id, $data[0]['id']);
        $this->assertEquals($n1->id, $data[1]['id']);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_add_a_note_to_a_contact(): void
    {
        $this->postJson($this->notesUrl(), ['body' => 'Called client, interested.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Called client, interested.');

        $this->assertDatabaseHas('contact_notes', [
            'contact_id' => $this->contact->id,
            'user_id'    => $this->owner->id,
            'body'       => 'Called client, interested.',
        ]);
    }

    public function test_empty_note_body_is_rejected(): void
    {
        $this->postJson($this->notesUrl(), ['body' => ''])
            ->assertUnprocessable();

        $this->assertDatabaseEmpty('contact_notes');
    }

    public function test_note_body_over_5000_chars_is_rejected(): void
    {
        $this->postJson($this->notesUrl(), ['body' => str_repeat('x', 5001)])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_can_delete_a_note(): void
    {
        $note = $this->contact->contactNotes()->create(['body' => 'To delete']);

        $this->deleteJson($this->notesUrl($note->id))->assertOk();

        $this->assertDatabaseMissing('contact_notes', ['id' => $note->id]);
    }

    public function test_deleting_unknown_note_returns_404(): void
    {
        $this->deleteJson($this->notesUrl(99999))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Scoping
    // -------------------------------------------------------------------------

    public function test_notes_from_another_contact_are_not_visible(): void
    {
        $other = $this->account->contacts()->create(['first_name' => 'Other']);
        $other->contactNotes()->create(['body' => 'Other note']);

        $data = $this->getJson($this->notesUrl())->assertOk()->json('data');
        $this->assertEmpty($data);
    }
}
