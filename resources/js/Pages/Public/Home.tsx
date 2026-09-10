import PublicContactForm from '@/Components/PublicContactForm';
import PublicLocationMap from '@/Components/PublicLocationMap';
import PublicOffers from '@/Components/PublicOffers';
import PublicWhyMadina from '@/Components/PublicWhyMadina';
import PublicOrderProcess from '@/Components/PublicOrderProcess';
import PublicProductCard, { CatalogProduct } from '@/Components/PublicProductCard';
import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
} from 'lucide-react';

const faq = [
    ['Quels types de produits pouvez-vous rechercher ?', 'Nous étudions les demandes de produits, équipements et machines autorisés à l’importation, pour les professionnels comme pour les particuliers.'],
    ['Comment obtenir une estimation ?', 'Décrivez votre besoin, la quantité et vos contraintes. Notre équipe revient vers vous avec les informations nécessaires pour cadrer l’estimation.'],
    ['Proposez-vous le fret aérien et maritime ?', 'Oui. Le mode est choisi selon le volume, le poids, le délai et la nature du produit.'],
    ['Comment suivre une commande ?', 'Utilisez le numéro de commande et le code sécurisé transmis par votre interlocuteur Madina Import.'],
    ['Puis-je acheter un produit déjà disponible à Madagascar ?', 'Oui, les produits publiés dans le catalogue sont issus du stock disponible.'],
    ['Quels documents dois-je fournir ?', 'Cela dépend du produit et du projet. Nous vous précisons les éléments requis avant toute validation.'],
];

export default function Home({ products, publicConfig }: { products: CatalogProduct[]; publicConfig: PublicConfig }) {
    const { flash } = usePage().props as any;
    const whatsapp = `https://wa.me/${publicConfig.whatsapp.replace(/\D/g, '')}`;

    return (
        <PublicLayout config={publicConfig}>
            <Head title="Importation Chine–Madagascar">
                <meta name="description" content="Madina Import accompagne les entrepreneurs malgaches pour le sourcing, l’achat, le contrôle et le fret depuis la Chine." />
            </Head>

            <section className="bg-[#171717]">
                    <div
                        className="relative isolate min-h-[570px] overflow-hidden bg-[#171717] bg-cover bg-[72%_center] sm:bg-[66%_center] lg:min-h-[585px] lg:bg-center"
                        style={{ backgroundImage: "url('/brand/hero-logistics-v2.png')" }}
                    >
                        <div className="absolute inset-0 -z-10 bg-gradient-to-r from-[#171717]/80 via-[#171717]/55 to-[#171717]/5" />
                        <div className="absolute inset-0 -z-10 bg-gradient-to-t from-[#171717]/45 via-transparent to-transparent" />
                        <div className="public-container flex min-h-[430px] items-center py-14">
                            <div className="public-reveal max-w-[640px]">
                                <p className="public-kicker !text-[#FFE600]">Sourcing · Achat · Fret · Suivi</p>
                                <h1 className="mt-5 max-w-[590px] text-[34px] font-bold leading-[1.1] tracking-[-.035em] text-white sm:text-[40px] lg:text-[44px]">
                                    De la Chine à Madagascar, <span className="text-[#FFE600]">votre projet avance</span> en confiance.
                                </h1>
                                <p className="mt-5 max-w-[540px] text-sm leading-6 text-white/75">
                                    Madina Import accompagne les entrepreneurs et entreprises malgaches à chaque étape : recherche de fournisseurs, achat, contrôle, transport et livraison.
                                </p>
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Link href="/contact" className="public-button">Demander un devis <ArrowRight size={16} /></Link>
                                    <Link href="/catalogue" className="public-button-secondary !border-white/35 !bg-white/5 !text-white hover:!bg-white hover:!text-[#171717]">Voir les produits disponibles</Link>
                                </div>
                            </div>
                        </div>

                        <div className="relative mx-auto mb-4 grid w-[calc(100%-2rem)] max-w-[1160px] lg:absolute lg:bottom-6 lg:left-1/2 lg:mb-0 lg:-translate-x-1/2 lg:grid-cols-4">
                            {[
                                ['/icons/madina-3d/hero-china.webp', 'Présence en Chine'],
                                ['/icons/madina-3d/complete-support.webp', 'Accompagnement personnalisé'],
                                ['/icons/madina-3d/hero-tracking.webp', 'Suivi de commande'],
                                ['/icons/madina-3d/hero-freight.webp', 'Fret aérien et maritime'],
                            ].map(([icon, label]) => (
                                <div key={label} className="hero-benefit flex items-center gap-4 border-b border-white/20 px-5 py-5 last:border-0 lg:border-b-0 lg:border-r">
                                    <span className="hero-benefit-icon">
                                        <img src={icon} width="256" height="256" alt="" aria-hidden="true" />
                                    </span>
                                    <strong className="text-[13px] leading-5 text-white drop-shadow-sm">{label}</strong>
                                </div>
                            ))}
                        </div>
                </div>
            </section>

            <PublicWhyMadina />

            <PublicOffers />

            <PublicOrderProcess />

            <section className="public-section bg-[#F8F7F3]">
                <div className="public-container">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <Header kicker="Disponible maintenant" title="Une sélection prête à avancer." />
                        <Link href="/catalogue" className="public-text-link">Voir tout le catalogue <ArrowRight size={16} /></Link>
                    </div>
                    {products.length ? (
                        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{products.map((product) => <PublicProductCard key={product.slug} product={product} />)}</div>
                    ) : (
                        <div className="mt-10 rounded-[4px] border border-dashed border-black/15 bg-white p-12 text-center">
                            <img src="/icons/madina-3d/catalog-preparing.webp" width={256} height={256} alt="" aria-hidden="true" className="catalog-preparing-icon mx-auto" />
                            <h3 className="mt-4 text-xl font-bold">Le catalogue se prépare</h3>
                            <p className="mt-2 text-sm text-[#5E5E5E]">Contactez-nous pour une recherche personnalisée en Chine.</p>
                        </div>
                    )}
                </div>
            </section>

            <section className="border-y border-black/[.07] bg-white py-12 text-[#171717]">
                <div className="public-container grid items-center gap-8 lg:grid-cols-[1fr_auto]">
                    <div>
                        <p className="public-kicker">Votre commande, sans zone d’ombre</p>
                        <h2 className="mt-3 max-w-3xl text-[28px] font-bold tracking-[-.025em] sm:text-[32px]">Consultez les étapes avec votre accès sécurisé.</h2>
                        <p className="mt-3 max-w-2xl text-sm text-[#5E5E5E]">Votre numéro de commande et votre Tracking number suffisent pour retrouver les informations utiles.</p>
                    </div>
                    <Link href="/suivi" className="public-button">Suivre ma commande <ArrowRight size={16} /></Link>
                </div>
            </section>

            <section className="public-section bg-white">
                <div className="public-container grid gap-12 lg:grid-cols-[.85fr_1.15fr]">
                    <div>
                        <Header kicker="Questions fréquentes" title="Les réponses à vos questions." />
                    </div>
                    <div>
                        <div className="mt-4 divide-y divide-black/[.08] border-y border-black/[.08]">
                            {faq.map(([question, answer]) => (
                                <details key={question} className="group py-5">
                                    <summary className="cursor-pointer list-none pr-8 text-sm font-bold marker:hidden">{question}</summary>
                                    <p className="mt-3 pr-6 text-[13px] leading-6 text-[#5E5E5E]">{answer}</p>
                                </details>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="public-section border-t border-black/[.06] bg-[#F3F5F4]">
                <div className="public-container">
                    <div className="grid gap-12 lg:grid-cols-[minmax(0,.88fr)_minmax(0,1.12fr)] lg:gap-16">
                        <div>
                            <Header kicker="Parlons de votre projet" title="Une demande claire est le début d’un bon parcours." />
                            <a href={whatsapp} target="_blank" rel="noreferrer" className="public-text-link mt-7">Nous contacter sur WhatsApp <ArrowRight size={16} /></a>
                            <div className="mt-3"><PublicLocationMap address={publicConfig.address} compact /></div>
                        </div>
                        <div className="rounded-[4px] border border-black/[.07] bg-white p-6 shadow-[0_12px_40px_rgba(23,23,23,.05)] sm:p-8 lg:translate-x-3">
                            <PublicContactForm flash={flash?.success} />
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

function Header({ kicker, title, text, centered = false }: { kicker: string; title: string; text?: string; centered?: boolean }) {
    return (
        <div className={centered ? 'text-center' : ''}>
            <p className="public-kicker">{kicker}</p>
            <h2 className={`mt-3 text-[27px] font-bold leading-[1.18] tracking-[-.025em] text-[#171717] sm:text-[34px] ${centered ? 'mx-auto' : ''}`}>{title}</h2>
            {text && <p className={`mt-4 max-w-2xl text-sm leading-7 text-[#5E5E5E] ${centered ? 'mx-auto' : ''}`}>{text}</p>}
        </div>
    );
}
