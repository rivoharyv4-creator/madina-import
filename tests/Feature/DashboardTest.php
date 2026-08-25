<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_manager_can_open_dashboard_and_every_module(): void
    {
        $user = User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        foreach (['clients','devis','commandes','paiements','factures','fournisseurs','achats','logistique','stock','ventes','depenses','salaires','fiscalite','rapports','parametres'] as $module) {
            $this->actingAs($user)->get('/modules/'.$module)->assertOk();
        }
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
