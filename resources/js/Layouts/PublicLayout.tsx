import BrandLogo from '@/Components/BrandLogo';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Facebook, Menu, Moon, Phone, Sun, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export type PublicConfig = {
    madagascar_phone: string;
    china_phone: string;
    whatsapp: string;
    facebook_url?: string | null;
};

const links = [
    ['Accueil', '/'],
    ['Nos services', '/#services'],
    ['Catalogue', '/catalogue'],
    ['Suivre une commande', '/suivi'],
    ['Contact', '/contact'],
];

export default function PublicLayout({ children, config }: { children: React.ReactNode; config: PublicConfig }) {
    const [open, setOpen] = useState(false);
    const [theme, setTheme] = useState<'light' | 'dark'>(() => {
        if (typeof window === 'undefined') return 'light';
        return window.localStorage.getItem('madina-public-theme') === 'dark' ? 'dark' : 'light';
    });
    const path = window.location.pathname;
    const whatsapp = `https://wa.me/${String(config.whatsapp || '').replace(/\D/g, '')}?text=${encodeURIComponent("Bonjour Madina Import, je souhaite demander un devis pour un projet d’importation.")}`;

    useEffect(() => {
        window.localStorage.setItem('madina-public-theme', theme);
    }, [theme]);

    const toggleTheme = () => setTheme(current => current === 'light' ? 'dark' : 'light');

    return (
        <div className={`public-site min-h-screen overflow-x-hidden bg-[#F8F7F3] text-[#171717] ${theme === 'dark' ? 'theme-dark' : 'theme-light'}`}>
            <a href="#contenu" className="sr-only z-[100] rounded bg-white p-3 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">
                Aller au contenu
            </a>

            <header className="fixed inset-x-0 top-0 z-50 border-b border-black/[.07] bg-white/95 backdrop-blur-md">
                <div className="public-container flex h-[72px] items-center gap-8">
                    <Link href="/" aria-label="Madina Import — Accueil" className="mr-auto flex items-center">
                        <BrandLogo className="h-14 w-[76px]" />
                    </Link>
                    <nav className="hidden h-full items-center gap-7 lg:flex" aria-label="Navigation principale">
                        {links.map(([label, href]) => {
                            const active = href === '/' ? path === '/' : href.startsWith('/#') ? false : path.startsWith(href);
                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    className={`relative flex h-full items-center text-[13px] font-bold transition hover:text-[#C8102E] ${active ? 'text-[#C8102E] after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-[#C8102E]' : 'text-[#5E5E5E]'}`}
                                >
                                    {label}
                                </Link>
                            );
                        })}
                    </nav>
                    <button
                        type="button"
                        onClick={toggleTheme}
                        className="public-theme-toggle"
                        aria-label={theme === 'dark' ? 'Activer le thème clair' : 'Activer le thème sombre'}
                        aria-pressed={theme === 'dark'}
                        title={theme === 'dark' ? 'Thème clair' : 'Thème sombre'}
                    >
                        {theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
                    </button>
                    <a href={whatsapp} target="_blank" rel="noreferrer" className="public-button hidden lg:inline-flex">
                        Demander un devis <ArrowUpRight size={15} />
                    </a>
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        className="grid size-10 place-items-center rounded-md border border-black/10 lg:hidden"
                        aria-expanded={open}
                        aria-label="Ouvrir le menu"
                    >
                        {open ? <X size={20} /> : <Menu size={20} />}
                    </button>
                </div>
                {open && (
                    <nav className="border-t border-black/5 bg-white px-5 py-5 lg:hidden" aria-label="Navigation mobile">
                        <div className="mx-auto flex max-w-xl flex-col">
                            {links.map(([label, href]) => (
                                <Link key={href} href={href} onClick={() => setOpen(false)} className="border-b border-black/5 py-3 text-sm font-bold">
                                    {label}
                                </Link>
                            ))}
                            <a href={whatsapp} className="public-button mt-5">Demander un devis</a>
                        </div>
                    </nav>
                )}
            </header>

            <main id="contenu" className="pt-[72px]">{children}</main>

            <footer className="border-t border-black/10 bg-white text-[#171717]">
                <div className="public-container grid gap-10 py-12 md:grid-cols-[1.25fr_.75fr_1fr]">
                    <div>
                        <BrandLogo className="h-16 w-24" />
                        <p className="mt-4 max-w-sm text-sm leading-7 text-black/60">
                            Un accompagnement structuré pour vos achats et importations entre la Chine et Madagascar.
                        </p>
                    </div>
                    <div>
                        <strong className="text-xs uppercase tracking-[.16em] text-[#C8102E]">Navigation</strong>
                        <div className="mt-5 grid gap-2.5 text-sm text-black/60">
                            {links.slice(1).map(([label, href]) => <Link key={href} href={href} className="hover:text-[#C8102E]">{label}</Link>)}
                        </div>
                    </div>
                    <div>
                        <strong className="text-xs uppercase tracking-[.16em] text-[#C8102E]">Nous joindre</strong>
                        <p className="mt-5 flex items-center gap-3 text-sm text-black/60"><Phone size={15} /> Madagascar : {config.madagascar_phone}</p>
                        <p className="mt-3 flex items-center gap-3 text-sm text-black/60"><Phone size={15} /> Chine : {config.china_phone}</p>
                        {config.facebook_url && <a href={config.facebook_url} target="_blank" rel="noreferrer" className="mt-3 flex items-center gap-3 text-sm text-black/60 transition hover:text-[#C8102E]"><Facebook size={15} /> Page Facebook</a>}
                    </div>
                </div>
                <div className="border-t border-black/10 py-5 text-center text-xs text-black/40">© {new Date().getFullYear()} Madina Import · Madagascar</div>
            </footer>
        </div>
    );
}
