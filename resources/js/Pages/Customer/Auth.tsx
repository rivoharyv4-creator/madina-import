import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, Mail } from 'lucide-react';
import { useState } from 'react';

const labels = { name: 'Nom complet', email: 'Adresse e-mail', phone: 'Téléphone', password: 'Mot de passe', password_confirmation: 'Confirmer le mot de passe' };

function Decoration({ right = false }: { right?: boolean }) {
    return <svg viewBox="0 0 240 320" fill="none" className={`customer-auth-decoration ${right ? 'customer-auth-decoration-right' : 'customer-auth-decoration-left'}`} aria-hidden="true">
        <path d="M12 300h216M22 72c30 30 25-38 49-16s28 8 46-3M151 35c-9-15 9-26 12-9s-8 29-20 20M39 108h93v59H63l-17 18v-18h-7zM54 126h62m-62 13h42" stroke="currentColor" strokeLinecap="round"/>
        <path d="M34 224h51v76H34z" fill="#FFE600"/>
        {[0,1,2,3].map(row => [0,1,2].map(col => <circle key={`${row}-${col}`} cx={44+col*15} cy={235+row*18} r="2.5" fill="#171717"/>))}
        <path d="M102 210h97v90h-97zM102 210l49-30 48 30m-48-30v30m-49 0 48 30 49-30m-49 30v60" stroke="currentColor"/>
        <path d="M140 264h20m-10-10v20" stroke="#C8102E" strokeWidth="2" strokeLinecap="round"/>
        <circle cx="194" cy="90" r="4" stroke="currentColor"/>
        <path d="M163 132c32 0 49 24 34 47" stroke="currentColor" strokeDasharray="3 6"/>
    </svg>;
}

export default function Auth({ register, publicConfig }: { register: boolean; publicConfig: PublicConfig }) {
    const form = useForm({ name: '', phone: '', email: '', password: '', password_confirmation: '' });
    const flash = usePage().props.flash as { success?: string } | undefined;
    const [showPassword, setShowPassword] = useState(false);
    const fields: (keyof typeof labels)[] = register ? ['name', 'email', 'phone', 'password', 'password_confirmation'] : ['email', 'password'];
    const title = register ? 'Créer votre compte client' : 'Connexion client';

    return <PublicLayout config={publicConfig}>
        <Head title={title}/>
        <section className="customer-auth commerce px-5 py-12 sm:py-16" aria-labelledby="customer-auth-title">
            <div className="customer-auth-stage">
                <Decoration/><Decoration right/>
                <div className="customer-auth-card commerce-panel mx-auto w-full max-w-[440px]">
                    <div className="mb-8 text-center">
                        <img src="/images/customer-login-madina.svg" alt="" aria-hidden="true" className="customer-auth-icon mx-auto mb-5 size-20 rounded-full object-contain"/>
                        <h1 id="customer-auth-title" className="text-3xl font-extrabold">{title}</h1>
                        <p className="customer-auth-muted mx-auto mt-3 max-w-xs text-sm leading-6">{register ? 'Un compte pour suivre vos commandes et préparer vos prochains achats.' : 'Ravi de vous retrouver. Connectez-vous à votre espace client.'}</p>
                    </div>
                    {flash?.success && <p role="status" className="mb-5 rounded-lg border p-3 text-sm">{flash.success}</p>}
                    <form className="space-y-5" onSubmit={e => {
                        e.preventDefault();
                        form.post(register ? '/inscription' : '/connexion', { onFinish: () => form.reset('password', 'password_confirmation') });
                    }}>
                        {fields.map(field => {
                            const password = field.includes('password');
                            const error = form.errors[field];
                            return <div key={field}>
                                <label htmlFor={`customer-${field}`} className="mb-2 block text-sm font-bold">{labels[field]}</label>
                                <div className="relative">
                                    <input id={`customer-${field}`} name={field} className={`block h-12 w-full text-sm ${password ? 'pr-12' : field === 'email' ? 'pr-10' : ''}`} required
                                        type={password ? showPassword ? 'text' : 'password' : field === 'email' ? 'email' : field === 'phone' ? 'tel' : 'text'}
                                        autoComplete={field === 'name' ? 'name' : field === 'email' ? 'email' : field === 'phone' ? 'tel' : register ? 'new-password' : 'current-password'}
                                        placeholder={field === 'email' ? 'vous@exemple.com' : field === 'phone' ? 'Votre numéro de téléphone' : undefined}
                                        aria-invalid={!!error} aria-describedby={error ? `customer-${field}-error` : register && field === 'password' ? 'customer-password-hint' : undefined}
                                        value={form.data[field]} onChange={e => form.setData(field, e.target.value)}/>
                                    {password ? <button type="button" className="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg" aria-label={showPassword ? 'Masquer les mots de passe' : 'Afficher les mots de passe'} aria-pressed={showPassword} onClick={() => setShowPassword(value => !value)}>
                                        {showPassword ? <EyeOff size={18}/> : <Eye size={18}/>}</button> : field === 'email' ? <Mail size={17} className="customer-auth-muted pointer-events-none absolute right-3 top-4" aria-hidden="true"/> : null}
                                </div>
                                {register && field === 'password' && <p id="customer-password-hint" className="customer-auth-muted mt-2 text-xs">Au moins 8 caractères.</p>}
                                {error && <p id={`customer-${field}-error`} role="alert" className="customer-auth-error mt-2 text-sm">{error}</p>}
                            </div>;
                        })}
                        <button disabled={form.processing} className="public-button flex min-h-12 w-full justify-center">{form.processing ? 'Veuillez patienter…' : register ? 'Créer mon compte' : 'Se connecter'}</button>
                    </form>
                    <div className="customer-auth-divider my-6 border-t"/>
                    <p className="text-center text-sm">{register ? 'Déjà un compte ? ' : 'Pas encore de compte ? '}<Link href={register ? '/connexion' : '/inscription'} className="customer-auth-link font-bold underline decoration-transparent underline-offset-4 hover:decoration-current">{register ? 'Se connecter' : 'Créer un compte'}</Link></p>
                </div>
            </div>
            <Link href="/catalogue" className="customer-auth-muted mx-auto mt-6 flex w-fit items-center gap-2 text-sm hover:underline"><ArrowLeft size={15} aria-hidden="true"/>Retour au catalogue</Link>
        </section>
    </PublicLayout>;
}
