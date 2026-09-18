import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';
export default function CustomerShell({ title, publicConfig, children, header }: PropsWithChildren<{ title: string; publicConfig: PublicConfig; header?: ReactNode }>) {
    const { flash, errors, auth } = usePage().props as any;
    return <PublicLayout config={publicConfig}><Head title={title}/><section className="public-section"><div className="public-container commerce">{header ?? <><div className="mb-6 flex flex-wrap gap-4 text-sm"><Link href="/catalogue">Catalogue</Link><Link href="/panier">Panier</Link><Link href="/mes-commandes">Mes commandes</Link>{auth?.user ? <Link href="/logout" method="post" as="button">Déconnexion</Link> : <Link href="/connexion">Connexion client</Link>}</div><h1 className="mb-8 text-3xl font-extrabold">{title}</h1></>}{flash?.success && <p role="status" className="mb-5 rounded border p-4">{flash.success}</p>}{Object.values(errors || {}).length > 0 && <div role="alert" className="mb-5 rounded border border-red-500 p-4">{Object.values(errors).map((error: any, i) => <p key={i}>{error}</p>)}</div>}{children}</div></section></PublicLayout>;
}
