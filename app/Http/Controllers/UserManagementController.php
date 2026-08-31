<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()->orderByRaw("role = 'super_admin' desc")->orderBy('name')->get(['id','name','email','role','permissions','active','created_at']),
            'menuOptions' => collect(config('access.menus'))->map(fn($label,$value)=>compact('value','label'))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request);
        User::create([
            ...$data,
            'password'=>Hash::make($data['password']),
            'email_verified_at'=>now(),
            'permissions'=>array_values(array_unique($data['permissions']??[])),
        ]);

        return back()->with('success','Utilisateur ajouté avec succès.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(),403,'Le compte super administrateur ne peut pas être modifié ici.');
        $data=$this->validated($request,$user);
        if(empty($data['password'])) unset($data['password']);
        else $data['password']=Hash::make($data['password']);
        $data['permissions']=array_values(array_unique($data['permissions']??[]));
        $user->update($data);

        return back()->with('success','Accès de l’utilisateur mis à jour.');
    }

    private function validated(Request $request, ?User $user=null): array
    {
        return $request->validate([
            'name'=>['required','string','max:255'],
            'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user?->id)],
            'role'=>['required',Rule::in(['assistant','user'])],
            'password'=>[$user?'nullable':'required','string','min:8'],
            'permissions'=>['required','array','min:1'],
            'permissions.*'=>[Rule::in(array_keys(config('access.menus',[])))],
            'active'=>['required','boolean'],
        ]);
    }
}
