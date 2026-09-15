import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ loginUrl }: { loginUrl: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        secret_phrase: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = event => {
        event.preventDefault();
        post(route('password.email'), { onFinish: () => reset('secret_phrase', 'password', 'password_confirmation') });
    };

    return (
        <GuestLayout>
            <Head title="Réinitialiser le mot de passe" />
            <div className="mb-6">
                <span className="mb-4 grid size-12 place-items-center rounded-xl bg-[#FCF108]/25 text-[#817900]"><ShieldCheck size={23}/></span>
                <h1 className="text-2xl font-bold">Mot de passe oublié</h1>
                <p className="mt-2 text-sm leading-6 text-gray-500">Utilisez la phrase secrète remise lors de la création de votre compte. Aucun e-mail ne sera envoyé.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div><InputLabel htmlFor="email" value="Adresse e-mail"/><TextInput id="email" type="email" value={data.email} className="mt-1 block w-full" autoComplete="username" isFocused onChange={event=>setData('email',event.target.value)}/><InputError message={errors.email} className="mt-1"/></div>
                <div><InputLabel htmlFor="secret_phrase" value="Phrase secrète"/><div className="relative mt-1"><KeyRound size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/><TextInput id="secret_phrase" type="password" value={data.secret_phrase} className="block w-full pl-10" autoComplete="off" onChange={event=>setData('secret_phrase',event.target.value)}/></div><InputError message={errors.secret_phrase} className="mt-1"/></div>
                <div><InputLabel htmlFor="password" value="Nouveau mot de passe"/><TextInput id="password" type="password" value={data.password} className="mt-1 block w-full" autoComplete="new-password" onChange={event=>setData('password',event.target.value)}/><InputError message={errors.password} className="mt-1"/></div>
                <div><InputLabel htmlFor="password_confirmation" value="Confirmer le nouveau mot de passe"/><TextInput id="password_confirmation" type="password" value={data.password_confirmation} className="mt-1 block w-full" autoComplete="new-password" onChange={event=>setData('password_confirmation',event.target.value)}/></div>
                <div className="flex flex-wrap items-center justify-between gap-3 pt-2"><Link href={loginUrl} className="text-sm text-gray-500 underline">Retour à la connexion</Link><PrimaryButton disabled={processing}>{processing?'Vérification…':'Réinitialiser'}</PrimaryButton></div>
            </form>
        </GuestLayout>
    );
}
