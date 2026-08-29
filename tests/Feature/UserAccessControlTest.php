<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin=User::where('email','manager@madina-import.mg')->firstOrFail();
    }

    public function test_super_admin_can_create_an_assistant_with_selected_menu_access(): void
    {
        $this->actingAs($this->admin)->get('/admin/utilisateurs')->assertInertia(fn(Assert $page)=>$page
            ->component('Users/Index')
            ->where('menuOptions',fn($options)=>collect($options)->contains(fn($option)=>$option['value']==='logistique'))
        );

        $this->actingAs($this->admin)->post('/admin/utilisateurs',[
            'name'=>'Assistante Test','email'=>'assistante@test.mg','role'=>'assistant','password'=>'MotDePasse2026',
            'permissions'=>['logistique','stock','catalogue'],'active'=>true,
        ])->assertRedirect();

        $assistant=User::where('email','assistante@test.mg')->firstOrFail();
        $this->assertSame(['logistique','stock','catalogue'],$assistant->permissions);
        $this->assertTrue(Hash::check('MotDePasse2026',$assistant->password));
    }

    public function test_assistant_only_sees_and_opens_authorized_modules(): void
    {
        $assistant=User::create(['name'=>'Assistant limité','email'=>'limite@test.mg','role'=>'assistant','password'=>'MotDePasse2026','permissions'=>['stock'],'active'=>true,'email_verified_at'=>now()]);

        $this->actingAs($assistant)->get('/modules/stock')->assertInertia(fn(Assert $page)=>$page
            ->where('auth.user.role','assistant')
            ->where('auth.user.permissions',['stock'])
        );
        $this->actingAs($assistant)->get('/dashboard')->assertForbidden();
        $this->actingAs($assistant)->get('/modules/commandes')->assertForbidden();
        $this->actingAs($assistant)->post('/modules/commandes',[])->assertForbidden();
        $this->actingAs($assistant)->get('/admin/utilisateurs')->assertForbidden();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::create(['name'=>'Compte désactivé','email'=>'inactive@test.mg','role'=>'user','password'=>'MotDePasse2026','permissions'=>['stock'],'active'=>false]);

        $response=$this->post(config('madina.admin_login_path','gestion-privee'),['email'=>'inactive@test.mg','password'=>'MotDePasse2026']);
        $response->assertSessionHasErrors(['email'=>'Identifiants incorrects ou accès indisponible. Vérifiez vos informations et réessayez.']);
        $this->assertGuest();

        $this->post(config('madina.admin_login_path','gestion-privee'),['email'=>'inconnu@test.mg','password'=>'MauvaisMotDePasse'])
            ->assertSessionHasErrors(['email'=>'Identifiants incorrects ou accès indisponible. Vérifiez vos informations et réessayez.']);
    }
}
