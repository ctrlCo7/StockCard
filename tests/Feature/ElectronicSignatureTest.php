<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElectronicSignatureTest extends TestCase
{
    use RefreshDatabase;

    /** A real PNG, as the in-app signature pad produces it. */
    private function drawnSignature(int $width = 240): string
    {
        $image = imagecreatetruecolor($width, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 10, 60, $width - 10, 15, imagecolorallocate($image, 11, 31, 74));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function signWith(User $user, ?string $drawn = null): void
    {
        app(SignatureService::class)->store($user, $drawn ?? $this->drawnSignature());
    }

    private function submittedRequisition(User $requester): Requisition
    {
        $item = $this->makeItem(10);
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Monthly supplies'],
            [['item_id' => $item->id, 'quantity' => 2]], $requester);
        $this->ris()->submit($r, $requester);

        return $r->fresh();
    }

    public function test_user_saves_a_signature_drawn_in_the_app(): void
    {
        Storage::fake('local');
        $user = $this->requester();

        $this->actingAs($user)->put(route('profile.signature'), ['signature_data' => $this->drawnSignature()])
            ->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->signature_path);
        Storage::disk('local')->assertExists($user->signature_path);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring(Storage::disk('local')->get($user->signature_path))[2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.signature_updated', 'user_id' => $user->id]);
    }

    public function test_drawn_signature_that_is_not_a_png_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->requester();

        $this->actingAs($user)->put(route('profile.signature'), ['signature_data' => 'data:image/png;base64,'.base64_encode('<svg onload=alert(1)>')])
            ->assertSessionHasErrors(['signature_data' => 'The drawn signature could not be read. Please sign again.']);

        $this->assertNull($user->fresh()->signature_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_uploaded_signature_must_be_a_png_or_jpg_image(): void
    {
        Storage::fake('local');
        $user = $this->requester();

        $this->actingAs($user)->put(route('profile.signature'), ['signature_file' => UploadedFile::fake()->create('signature.pdf', 20, 'application/pdf')])
            ->assertSessionHasErrors(['signature_file' => 'Upload the signature as a PNG or JPG image.']);
        $this->assertNull($user->fresh()->signature_path);

        $this->actingAs($user)->put(route('profile.signature'), ['signature_file' => UploadedFile::fake()->image('signature.png', 400, 120)])
            ->assertSessionHas('success');
        Storage::disk('local')->assertExists($user->fresh()->signature_path);
    }

    public function test_signing_stamps_a_copy_that_later_signature_changes_do_not_alter(): void
    {
        Storage::fake('local');
        $requester = $this->requester();
        $approver = $this->admin();
        $original = $this->drawnSignature(240);
        $this->signWith($approver, $original);
        $r = $this->submittedRequisition($requester);
        $this->ris()->forward($r, $this->admin());

        $this->ris()->approve($r->fresh(), $approver);
        $this->signWith($approver, $this->drawnSignature(300));

        $stamped = $r->fresh()->approved_signature_path;
        $this->assertNotNull($stamped);
        $this->assertNotSame($approver->fresh()->signature_path, $stamped);
        $this->assertSame(base64_decode(substr($original, strlen('data:image/png;base64,'))), Storage::disk('local')->get($stamped));
    }

    public function test_printed_ris_shows_each_signatorys_signature_image(): void
    {
        Storage::fake('local');
        $requester = $this->requester();
        $this->signWith($requester);
        $admin = $this->admin();
        $r = $this->submittedRequisition($requester);

        $html = $this->actingAs($admin)->get(route('requisitions.print', $r))->assertOk()->getContent();

        $this->assertStringContainsString('alt="Signature of '.e($requester->name).'"', $html);
        $this->assertStringContainsString('src="data:image/png;base64,', $html);
    }

    public function test_signing_without_a_signature_on_file_still_works_and_is_marked_on_the_form(): void
    {
        Storage::fake('local');
        $requester = $this->requester();

        $r = $this->submittedRequisition($requester);

        $this->assertNull($r->requested_signature_path);
        $this->actingAs($requester)->get(route('requisitions.print', $r))->assertOk()
            ->assertSee('(no e-signature on file)', false);
    }

    public function test_removing_a_signature_keeps_documents_already_signed(): void
    {
        Storage::fake('local');
        $requester = $this->requester();
        $this->signWith($requester);
        $profileCopy = $requester->fresh()->signature_path;
        $r = $this->submittedRequisition($requester);

        $this->actingAs($requester)->delete(route('profile.signature.destroy'))->assertRedirect();

        $this->assertNull($requester->fresh()->signature_path);
        Storage::disk('local')->assertMissing($profileCopy);
        Storage::disk('local')->assertExists($r->requested_signature_path);
    }
}
