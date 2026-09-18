import CustomerShell from '@/Components/CustomerShell';
import { PublicConfig } from '@/Layouts/PublicLayout';
import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, LogOut, Mail, RefreshCw, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Verify({ maskedEmail, retryAfter, publicConfig }: { maskedEmail: string; retryAfter: number; publicConfig: PublicConfig }) {
    const form = useForm({ code: '' });
    const resend = useForm({});
    const [seconds, setSeconds] = useState(retryAfter);
    useEffect(() => setSeconds(retryAfter), [retryAfter]);
    useEffect(() => {
        const timer = setInterval(() => setSeconds(value => Math.max(0, value - 1)), 1000);
        return () => clearInterval(timer);
    }, []);
    const header = <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div><p className="customer-auth-muted mb-3 text-xs font-bold uppercase tracking-[.18em]">Votre espace client</p><h1 className="text-3xl font-extrabold">Confirmez votre adresse e-mail</h1></div>
        <Link href={route('logout')} method="post" as="button" className="customer-auth-muted inline-flex items-center gap-2 text-sm font-semibold"><LogOut size={16} aria-hidden="true" />Déconnexion</Link>
    </div>;
    return <CustomerShell title="Confirmez votre adresse e-mail" publicConfig={publicConfig} header={header}>
        <div className="commerce-panel relative mx-auto max-w-lg overflow-hidden !rounded-3xl !p-7 shadow-[0_20px_70px_rgba(23,23,23,0.06)] sm:!p-10">
            <div className="absolute inset-x-0 top-0 h-1 bg-[#C8102E]" aria-hidden="true" />
            <div className="mb-8 text-center">
                <div className="relative mx-auto mb-6 grid size-20 place-items-center rounded-3xl bg-[#C8102E]/[.06] text-[#C8102E]"><Mail size={35} strokeWidth={1.6} aria-hidden="true" /><span className="absolute -bottom-1 -right-1 grid size-8 place-items-center rounded-full bg-[#FFE600] text-[#171717]"><ShieldCheck size={18} aria-hidden="true" /></span></div>
                <h2 className="text-2xl font-extrabold">Un dernier pas avant vos achats</h2>
                <p className="customer-auth-muted mt-4 text-sm leading-6">Saisissez le code à six chiffres reçu dans votre boîte e-mail.</p>
                <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-current/10 px-4 py-2 text-sm font-semibold"><Mail size={14} aria-hidden="true" />{maskedEmail}</div>
            </div>
            <form onSubmit={event => { event.preventDefault(); form.post('/verify-email'); }}>
                <label htmlFor="verification-code" className="mb-3 block text-sm font-bold">Code de confirmation</label>
                <input id="verification-code" name="code" type="text" autoComplete="one-time-code" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} required className="block !h-16 w-full !rounded-xl text-center !text-3xl font-bold tracking-[.35em] sm:!h-20" placeholder="000000" aria-invalid={!!form.errors.code} aria-describedby="verification-hint" value={form.data.code} onChange={event => form.setData('code', event.target.value.replace(/\D/g, '').slice(0, 6))} />
                <p id="verification-hint" className="customer-auth-muted mt-3 text-xs">Votre code est valable pendant 10 minutes.</p>
                <button disabled={form.processing} className="public-button mt-6 flex min-h-12 w-full justify-center !rounded-xl disabled:opacity-60">{form.processing ? 'Vérification…' : 'Confirmer mon adresse'}<ArrowRight size={17} aria-hidden="true" /></button>
            </form>
            <div className="customer-auth-divider mt-8 border-t pt-6 text-center">
                <p className="customer-auth-muted text-sm">Vous n’avez pas reçu de code ?</p>
                <button type="button" className="customer-auth-link mt-3 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-3 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50" disabled={seconds > 0 || resend.processing} onClick={() => resend.post('/email/verification-notification', { onSuccess: () => setSeconds(60) })}><RefreshCw size={15} className={resend.processing ? 'animate-spin' : ''} aria-hidden="true" />{resend.processing ? 'Envoi en cours…' : seconds > 0 ? `Nouvel envoi dans ${seconds} s` : 'Renvoyer le code'}</button>
                <p className="customer-auth-muted mt-3 text-xs">Pensez à consulter vos courriers indésirables.</p>
            </div>
        </div>
        <p className="customer-auth-muted mt-6 flex items-center justify-center gap-2 text-center text-xs"><ShieldCheck size={14} aria-hidden="true" />Gardez votre code confidentiel.</p>
    </CustomerShell>;
}
