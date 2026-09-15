import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function TwoFactorChallenge() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    const submit: FormEventHandler = event => {
        event.preventDefault();
        post('/authentification-double-facteur');
    };

    return (
        <GuestLayout>
            <Head title="Double authentification" />
            <div className="mb-7 text-center">
                <span className="mx-auto grid size-14 place-items-center rounded-2xl bg-[#FCF108]/25 text-[#817900]"><ShieldCheck size={27} /></span>
                <h1 className="mt-4 text-2xl font-bold">Vérification de sécurité</h1>
                <p className="mt-2 text-sm leading-6 text-gray-500">Saisissez le code à six chiffres affiché dans Google Authenticator. Vous pouvez aussi utiliser un code de récupération.</p>
            </div>
            <form onSubmit={submit}>
                <InputLabel htmlFor="code" value="Code d’authentification" />
                <div className="relative mt-1">
                    <KeyRound size={17} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <TextInput
                        id="code"
                        value={data.code}
                        className="block w-full pl-10 text-center text-lg tracking-[.18em]"
                        autoComplete="one-time-code"
                        inputMode="text"
                        isFocused
                        onChange={event => setData('code', event.target.value)}
                    />
                </div>
                <InputError message={errors.code} className="mt-2" />
                <PrimaryButton className="mt-5 w-full justify-center" disabled={processing}>
                    {processing ? 'Vérification…' : 'Vérifier et se connecter'}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
