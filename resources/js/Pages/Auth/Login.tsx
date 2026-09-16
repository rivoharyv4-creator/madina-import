import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ShieldAlert, Smartphone } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        code: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(window.location.pathname, {
            onFinish: () => reset('password', 'code'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Connexion sécurisée" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {errors.email&&<div role="alert" aria-live="polite" className="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><span className="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-[#BD2433] shadow-sm"><ShieldAlert size={18}/></span><div><strong className="block text-sm">Connexion impossible</strong><p className="mt-0.5 text-xs leading-relaxed text-red-700">{errors.email}</p></div></div>}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        autoCapitalize="none"
                        spellCheck={false}
                        aria-invalid={Boolean(errors.email)}
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Mot de passe" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        aria-invalid={Boolean(errors.email||errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password&&<p className="mt-2 text-xs text-red-600">{errors.password}</p>}
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="code" value="Google Authenticator (si activé)" />
                    <div className="relative mt-1">
                        <Smartphone size={17} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <TextInput
                            id="code"
                            name="code"
                            value={data.code}
                            className="block w-full pl-10 tracking-[.12em]"
                            autoComplete="one-time-code"
                            inputMode="text"
                            maxLength={32}
                            aria-invalid={Boolean(errors.code)}
                            onChange={(e) => setData('code', e.target.value)}
                        />
                    </div>
                    <p className="mt-1 text-xs text-gray-400">Laissez ce champ vide si le super-administrateur n’a pas activé cette protection pour votre compte.</p>
                    {errors.code&&<p className="mt-2 text-xs text-red-600">{errors.code}</p>}
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Se souvenir de moi
                        </span>
                    </label>
                </div>

                <div className="mt-4 flex items-center justify-end">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Mot de passe oublié ?
                        </Link>
                    )}

                    <PrimaryButton className="ms-4" disabled={processing}>
                        {processing?'Vérification…':'Se connecter'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
