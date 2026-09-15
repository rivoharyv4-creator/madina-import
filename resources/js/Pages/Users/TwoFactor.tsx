import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy, KeyRound, Printer, ShieldCheck, ShieldOff } from 'lucide-react';
import { FormEvent } from 'react';
import { QRCodeSVG } from 'qrcode.react';

type ManagedUser = { id:number; name:string; email:string; enabled:boolean };
type Setup = { secret:string; provisioningUri:string } | null;

export default function TwoFactor({ managedUser, setup, recoveryCodes }:{ managedUser:ManagedUser; setup:Setup; recoveryCodes:string[]|null }) {
    const security = useForm({ current_password: '' });
    const confirmation = useForm({ code: '' });

    const beginSetup = (event:FormEvent) => {
        event.preventDefault();
        security.post(`/admin/utilisateurs/${managedUser.id}/double-authentification`, { preserveScroll:true });
    };
    const disable = () => security.delete(`/admin/utilisateurs/${managedUser.id}/double-authentification`, { preserveScroll:true });
    const confirm = (event:FormEvent) => {
        event.preventDefault();
        confirmation.post(`/admin/utilisateurs/${managedUser.id}/double-authentification/confirmer`, { preserveScroll:true });
    };

    return (
        <AuthenticatedLayout header={<><p className="eyebrow">Sécurité</p><h1 className="page-title">Google Authenticator</h1><p className="mt-1 text-sm text-gray-400">Configuration de {managedUser.name}</p></>}>
            <Head title="Google Authenticator" />
            <Link href="/admin/utilisateurs" className="btn-secondary mb-5 w-fit"><ArrowLeft size={16}/>Utilisateurs</Link>

            {recoveryCodes && <section className="panel mb-6 border-amber-300 bg-amber-50 print:border-0">
                <div className="flex flex-wrap items-start justify-between gap-4"><div><h2 className="font-bold text-amber-900">Codes de récupération</h2><p className="mt-1 text-sm text-amber-800">Ils ne seront affichés qu’une seule fois. Remettez-les à l’utilisateur par un canal sûr.</p></div><button type="button" onClick={()=>window.print()} className="btn-secondary print:hidden"><Printer size={15}/>Imprimer</button></div>
                <div className="mt-5 grid gap-2 font-mono text-sm sm:grid-cols-2">{recoveryCodes.map(code=><code key={code} className="rounded-lg border border-amber-200 bg-white px-3 py-2">{code}</code>)}</div>
            </section>}

            <div className="grid items-start gap-6 lg:grid-cols-2">
                <section className="panel">
                    <div className="flex items-center gap-4"><span className={`grid size-12 place-items-center rounded-xl ${managedUser.enabled?'bg-emerald-50 text-emerald-600':'bg-gray-100 text-gray-400'}`}>{managedUser.enabled?<ShieldCheck/>:<ShieldOff/>}</span><div><h2 className="font-bold">{managedUser.name}</h2><p className="text-sm text-gray-400">{managedUser.email}</p></div></div>
                    <p className="mt-5 rounded-xl bg-gray-50 p-4 text-sm leading-6 text-gray-600">Statut : <strong className={managedUser.enabled?'text-emerald-600':'text-amber-600'}>{managedUser.enabled?'Protection activée':'Protection non activée'}</strong></p>
                    <form onSubmit={beginSetup} className="mt-5">
                        <label className="text-xs font-semibold text-gray-700">Votre mot de passe super-admin</label>
                        <div className="relative mt-1"><KeyRound size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300"/><input type="password" className="field pl-10" value={security.data.current_password} onChange={event=>security.setData('current_password',event.target.value)} autoComplete="current-password" required/></div>
                        {security.errors.current_password&&<p className="mt-1 text-xs text-red-600">{security.errors.current_password}</p>}
                        <div className="mt-4 flex flex-wrap gap-3"><button type="submit" disabled={security.processing} className="btn-primary">{managedUser.enabled?'Reconfigurer':'Configurer'} Google Authenticator</button>{managedUser.enabled&&<button type="button" disabled={security.processing||!security.data.current_password} onClick={disable} className="btn-secondary text-red-600">Désactiver</button>}</div>
                    </form>
                </section>

                {setup && <section className="panel">
                    <h2 className="font-bold">1. Scanner le QR code</h2>
                    <p className="mt-2 text-sm leading-6 text-gray-500">Ouvrez Google Authenticator sur le téléphone de {managedUser.name}, puis ajoutez un compte.</p>
                    <div className="mt-5 w-fit rounded-xl border border-gray-200 bg-white p-4"><QRCodeSVG value={setup.provisioningUri} size={190} level="M" /></div>
                    <p className="mt-4 text-xs text-gray-500">Clé manuelle</p><div className="mt-1 flex items-center gap-2 rounded-xl bg-gray-50 p-3"><code className="min-w-0 flex-1 break-all text-xs font-bold tracking-wider">{setup.secret}</code><button type="button" onClick={()=>navigator.clipboard.writeText(setup.secret)} aria-label="Copier la clé"><Copy size={16}/></button></div>
                    <form onSubmit={confirm} className="mt-6 border-t border-gray-100 pt-5"><h2 className="font-bold">2. Confirmer le code</h2><input className="field mt-3 text-center text-lg tracking-[.2em]" inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={confirmation.data.code} onChange={event=>confirmation.setData('code',event.target.value.replace(/\D/g,''))} placeholder="000000" required/>{confirmation.errors.code&&<p className="mt-1 text-xs text-red-600">{confirmation.errors.code}</p>}<button className="btn-primary mt-4 w-full" disabled={confirmation.processing}>Activer la double authentification</button></form>
                </section>}
            </div>
        </AuthenticatedLayout>
    );
}
