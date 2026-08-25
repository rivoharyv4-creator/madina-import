import BrandLogo from '@/Components/BrandLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({children}:PropsWithChildren){
 return <div className="grid min-h-screen bg-[#F8F8F6] lg:grid-cols-[1.05fr_.95fr]">
  <section className="relative hidden overflow-hidden bg-[#2F2F2F] p-14 text-white lg:flex lg:flex-col lg:justify-between"><div className="absolute -right-32 -top-32 size-[420px] rounded-full border-[80px] border-[#FCF108]/10"/><Link href="/" className="relative inline-flex w-fit items-center"><BrandLogo className="h-32 w-36"/></Link><div className="relative max-w-lg"><span className="mb-6 block h-1 w-16 rounded bg-[#FCF108]"/><h1 className="text-4xl font-bold leading-tight">Pilotez chaque importation, du devis à la livraison.</h1><p className="mt-5 text-base leading-relaxed text-white/55">Une vision claire de vos commandes, paiements, fournisseurs, stocks et obligations financières.</p></div><p className="text-xs text-white/30">© {new Date().getFullYear()} Madina Import · Madagascar</p></section>
  <section className="flex items-center justify-center p-6"><div className="w-full max-w-[430px]"><div className="mb-7 flex justify-center lg:hidden"><BrandLogo className="h-28 w-36"/></div><p className="eyebrow">Espace sécurisé</p><h2 className="page-title mb-2 !text-3xl">Bienvenue</h2><p className="mb-8 text-sm text-gray-400">Connectez-vous à votre espace de gestion.</p><div className="rounded-2xl border border-[#E9E8E3] bg-white p-7 shadow-[0_20px_60px_rgba(47,47,47,.08)]">{children}</div></div></section>
 </div>;
}
