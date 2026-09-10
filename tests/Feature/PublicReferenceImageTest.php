<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicReferenceImageTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name'=>'Client Test', 'contact'=>'123456789', 'client_type'=>'entrepreneur', 'need'=>'Machine', 'message'=>'Une machine pour notre atelier.', 'consent'=>'1', 'website'=>''];
    }

    public function test_guest_can_attach_an_image_that_only_authorized_staff_can_view(): void
    {
        Storage::fake('persistent');
        $this->post('/contact', [...$this->payload(), 'reference_image'=>UploadedFile::fake()->image('reference.jpg')])->assertRedirect()->assertSessionHasNoErrors();
        $record = DB::table('contact_requests')->first();
        Storage::disk('persistent')->assertExists($record->reference_image_path);
        $url = route('contact-requests.reference-image', $record->id);
        $this->get($url)->assertRedirect(route('login'));
        $staff = User::factory()->create(['role'=>'manager', 'active'=>true, 'permissions'=>[]]);
        $this->actingAs($staff)->get($url)->assertForbidden();
        $staff->update(['permissions'=>['demandes']]);
        $this->actingAs($staff)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/modules/demandes/'.$record->id)->assertInertia(fn (Assert $page) => $page->component('Module/PublicRequestShow')->where('request.reference_image_url', $url));
        Storage::disk('persistent')->delete($record->reference_image_path);
        $this->get($url)->assertNotFound();
    }

    public function test_image_is_optional(): void
    {
        $this->post('/contact', $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contact_requests', ['name'=>'Client Test', 'reference_image_path'=>null]);
    }

    public function test_invalid_and_oversized_images_are_rejected_without_saving_files_or_requests(): void
    {
        Storage::fake('persistent');
        $forged = UploadedFile::fake()->createWithContent('fake.jpg', 'not an image');
        $actualUpload = new UploadedFile($forged->getPathname(), 'fake.jpg', 'image/jpeg', null, true);
        foreach ([UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('large.jpg')->size(2049), $actualUpload, UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')] as $file) {
            $this->post('/contact', [...$this->payload(), 'reference_image'=>$file])->assertSessionHasErrors('reference_image');
        }
        $this->assertDatabaseCount('contact_requests', 0);
        $this->assertSame([], Storage::disk('persistent')->allFiles());
    }
}
