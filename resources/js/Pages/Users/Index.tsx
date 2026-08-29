import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Check, KeyRound, Pencil, ShieldCheck, UserPlus, UsersRound, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type ManagedUser={id:number;name:string;email:string;role:'super_admin'|'assistant'|'user';permissions:string[]|null;active:boolean;created_at:string};
type MenuOption={value:string;label:string};
type FormData={name:string;email:string;role:'assistant'|'user';password:string;permissions:string[];active:boolean};

const empty:FormData={name:'',email:'',role:'assistant',password:'',permissions:[],active:true};

export default function UsersIndex({users,menuOptions,flash}:{users:ManagedUser[];menuOptions:MenuOption[];flash?:string}){
 const [editing,setEditing]=useState<number|null>(null);
 const {data,setData,post,put,processing,errors,reset,clearErrors}=useForm<FormData>(empty);
 const startCreate=()=>{setEditing(null);reset();clearErrors();};
 const startEdit=(user:ManagedUser)=>{setEditing(user.id);clearErrors();setData({name:user.name,email:user.email,role:user.role==='assistant'?'assistant':'user',password:'',permissions:user.permissions||[],active:user.active});};
 const toggle=(permission:string)=>setData('permissions',data.permissions.includes(permission)?data.permissions.filter(item=>item!==permission):[...data.permissions,permission]);
 const submit=(event:FormEvent)=>{event.preventDefault();const options={preserveScroll:true,onSuccess:()=>startCreate()};editing?put(`/admin/utilisateurs/${editing}`,options):post('/admin/utilisateurs',options);};

 return <AuthenticatedLayout header={<><p className="eyebrow">Administration</p><h1 className="page-title">Utilisateurs & accès</h1><p className="mt-1 text-sm text-gray-400">Créez les comptes de votre équipe et choisissez précisément les menus accessibles.</p></>}>
  <Head title="Utilisateurs & accès"/>
  {flash&&<div className="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"><Check size={17}/>{flash}</div>}
  <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_430px]">
   <section className="panel overflow-hidden p-0">
    <div className="panel-head border-b border-gray-100 p-5"><div><h2>Comptes de l’équipe</h2><p>{users.length} utilisateur{users.length>1?'s':''} enregistré{users.length>1?'s':''}</p></div><button type="button" onClick={startCreate} className="btn-primary"><UserPlus size={16}/>Ajouter</button></div>
    <div className="divide-y divide-gray-100">
     {users.map(user=><article key={user.id} className="flex flex-col gap-4 p-5 transition hover:bg-[#FCF108]/[.035] sm:flex-row sm:items-center">
      <div className={`grid size-11 shrink-0 place-items-center rounded-xl ${user.role==='super_admin'?'bg-[#FCF108]/30 text-[#756e00]':'bg-[#BD2433]/10 text-[#BD2433]'}`}>{user.role==='super_admin'?<ShieldCheck size={21}/>:<UsersRound size={21}/>}</div>
      <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><strong className="truncate text-sm">{user.name}</strong><span className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${user.active?'bg-emerald-50 text-emerald-700':'bg-gray-100 text-gray-400'}`}>{user.active?'Actif':'Désactivé'}</span></div><p className="mt-0.5 truncate text-xs text-gray-400">{user.email}</p><p className="mt-2 text-[11px] text-gray-500">{user.role==='super_admin'?'Super administrateur · accès complet':`${user.role==='assistant'?'Assistant':'Utilisateur'} · ${user.permissions?.length||0} menu(s) autorisé(s)`}</p></div>
      {user.role!=='super_admin'&&<button type="button" onClick={()=>startEdit(user)} className="btn-secondary shrink-0"><Pencil size={15}/>Modifier les accès</button>}
     </article>)}
    </div>
   </section>

   <form onSubmit={submit} className="panel sticky top-[92px] space-y-5">
    <div className="panel-head"><div><h2>{editing?'Modifier l’utilisateur':'Nouvel utilisateur'}</h2><p>Sélectionnez au moins un menu.</p></div>{editing&&<button type="button" onClick={startCreate} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100" aria-label="Annuler"><X size={18}/></button>}</div>
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
     <Field label="Nom complet" error={errors.name}><input className="field" value={data.name} onChange={e=>setData('name',e.target.value)} placeholder="Ex. Assistante logistique" required/></Field>
     <Field label="Adresse e-mail" error={errors.email}><input type="email" className="field" value={data.email} onChange={e=>setData('email',e.target.value)} placeholder="assistant@madina-import.mg" required/></Field>
     <Field label="Type de compte" error={errors.role}><select className="field" value={data.role} onChange={e=>setData('role',e.target.value as FormData['role'])}><option value="assistant">Assistant</option><option value="user">Autre utilisateur</option></select></Field>
     <Field label={editing?'Nouveau mot de passe (optionnel)':'Mot de passe'} error={errors.password}><div className="relative"><KeyRound size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300"/><input type="password" className="field pl-10" value={data.password} onChange={e=>setData('password',e.target.value)} minLength={8} required={!editing} placeholder="8 caractères minimum"/></div></Field>
    </div>
    <div>
     <div className="mb-3 flex items-center justify-between"><div><h3 className="text-sm font-bold">Menus autorisés</h3><p className="text-[11px] text-gray-400">Les pages non cochées seront également bloquées par URL.</p></div><button type="button" onClick={()=>setData('permissions',data.permissions.length===menuOptions.length?[]:menuOptions.map(item=>item.value))} className="text-xs font-semibold text-[#BD2433]">{data.permissions.length===menuOptions.length?'Tout retirer':'Tout sélectionner'}</button></div>
     <div className="grid max-h-[310px] gap-2 overflow-y-auto rounded-xl border border-gray-100 bg-[#FAFAF8] p-3 sm:grid-cols-2 xl:grid-cols-1">
      {menuOptions.map(option=>{const selected=data.permissions.includes(option.value);return <label key={option.value} className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-xs font-semibold transition ${selected?'border-[#BD2433]/25 bg-white text-[#2F2F2F] shadow-sm':'border-transparent text-gray-400 hover:bg-white'}`}><input type="checkbox" className="sr-only" checked={selected} onChange={()=>toggle(option.value)}/><span className={`grid size-5 place-items-center rounded-md border ${selected?'border-[#BD2433] bg-[#BD2433] text-white':'border-gray-200 bg-white'}`}>{selected&&<Check size={13}/>}</span>{option.label}</label>})}
     </div>
     {errors.permissions&&<p className="mt-1 text-xs text-red-600">{errors.permissions}</p>}
    </div>
    <label className="flex cursor-pointer items-center justify-between rounded-xl border border-gray-100 bg-[#FAFAF8] p-3"><span><strong className="block text-xs">Compte actif</strong><small className="text-[10px] text-gray-400">Autoriser la connexion à la gestion interne</small></span><input type="checkbox" checked={data.active} onChange={e=>setData('active',e.target.checked)} className="rounded border-gray-300 text-[#BD2433] focus:ring-[#BD2433]/20"/></label>
    <button type="submit" disabled={processing} className="btn-primary w-full">{processing?'Enregistrement…':editing?'Enregistrer les accès':'Créer l’utilisateur'}</button>
   </form>
  </div>
 </AuthenticatedLayout>;
}

function Field({label,error,children}:{label:string;error?:string;children:ReactNode}){
 return <label className="block"><span className="mb-1.5 block text-xs font-semibold text-gray-700">{label}</span>{children}{error&&<span className="mt-1 block text-xs text-red-600">{error}</span>}</label>;
}
