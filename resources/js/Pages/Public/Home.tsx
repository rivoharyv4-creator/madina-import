import PublicContactForm from '@/Components/PublicContactForm';
import PublicProductCard, { CatalogProduct } from '@/Components/PublicProductCard';
import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Box,
    CheckCircle2,
    Factory,
    Globe2,
    MapPin,
    PackageCheck,
    Plane,
    SearchCheck,
    Ship,
    Sparkles,
    UserRoundCheck,
} from 'lucide-react';

const services = [
    ['Recherche et vérification des fournisseurs', 'Nous identifions les options adaptées et vérifions les informations utiles avant votre décision.', SearchCheck],
    ['Achat et négociation', 'Nous facilitons les échanges, la négociation et l’achat selon les conditions validées avec vous.', Factory],
    ['Contrôle et consolidation', 'Vos produits sont suivis, contrôlés et regroupés avant leur départ lorsque le projet le demande.', PackageCheck],
    ['Emballage et fret', 'Nous coordonnons l’emballage et le transport aérien ou maritime selon vos contraintes.', Box],
    ['Suivi jusqu’à Madagascar', 'Vous gardez une vision claire des étapes jusqu’à l’arrivée et la remise de vos produits.', MapPin],
];

const process = ['Votre besoin', 'Sourcing et estimation', 'Validation et achat', 'Contrôle et expédition', 'Arrivée à Madagascar'];

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
                                <h1 className="mt-5 max-w-[590px] text-[36px] font-bold leading-[1.08] tracking-[-.035em] text-white sm:text-[43px] lg:text-[48px]">
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
                                [Globe2, 'Présence en Chine'],
                                [UserRoundCheck, 'Accompagnement personnalisé'],
                                [CheckCircle2, 'Suivi de commande'],
                                [Ship, 'Fret aérien et maritime'],
                            ].map(([Icon, label]: any) => (
                                <div key={label} className="flex items-center gap-4 border-b border-white/20 px-5 py-5 last:border-0 lg:border-b-0 lg:border-r">
                                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white/10 text-white ring-1 ring-inset ring-white/15"><Icon size={19} /></span>
                                    <strong className="text-[13px] leading-5 text-white drop-shadow-sm">{label}</strong>
                                </div>
                            ))}
                        </div>
                </div>
            </section>

            <section className="public-section bg-white">
                <div className="public-container grid items-center gap-14 lg:grid-cols-[.9fr_1.1fr]">
                    <div>
                        <Header kicker="Un partenaire opérationnel" title="Chaque étape compte. Nous les suivons avec vous." text="Une méthode claire, du besoin initial jusqu’à la remise à Madagascar." />
                        <div className="mt-8 grid gap-3 sm:grid-cols-2">
                            {['Présence sur le terrain en Chine', 'Un interlocuteur unique', 'Visibilité sur les étapes', 'Accompagnement des entrepreneurs malgaches', 'Solutions adaptées à chaque projet'].map((text) => (
                                <div key={text} className="flex items-center gap-3 text-[13px] font-bold text-[#2F2F2F]">
                                    <CheckCircle2 className="shrink-0 text-[#C8102E]" size={17} />{text}
                                </div>
                            ))}
                        </div>
                    </div>
                    <figure className="relative min-h-[390px] overflow-hidden rounded-[4px] bg-[#171717] shadow-[0_18px_55px_rgba(23,23,23,.16)]">
                        <img
                            src="/brand/china-operations.webp"
                            alt="Contrôle de marchandises avec un partenaire logistique dans un entrepôt en Chine"
                            className="absolute inset-0 size-full object-cover object-center"
                            loading="lazy"
                        />
                        <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/55 to-transparent px-7 pb-7 pt-24 text-white sm:px-9 sm:pb-8">
                            <p className="public-kicker !text-[#FFE600]">Présence opérationnelle en Chine</p>
                            <figcaption className="mt-3 max-w-md text-lg font-bold leading-7">Contrôle des produits et préparation de chaque expédition avec vous.</figcaption>
                        </div>
                    </figure>
                </div>
            </section>

            <section id="services" className="public-section border-y border-black/[.06] bg-[#F3F5F4]">
                <div className="public-container">
                    <div className="mx-auto max-w-3xl text-center"><Header centered kicker="Nos services" title="Un accompagnement complet, adapté à votre projet." /></div>
                    <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                        {services.map(([title, text, Icon]: any, index) => (
                            <article key={title} className="group rounded-[4px] border border-black/[.07] bg-white p-6 shadow-[0_8px_30px_rgba(23,23,23,.035)] transition hover:-translate-y-1 hover:border-[#C8102E]/35 hover:shadow-[0_14px_35px_rgba(23,23,23,.08)]">
                                <div className="flex items-center justify-between">
                                    <span className="grid size-11 place-items-center rounded-full bg-[#F8F7F3] text-[#C8102E]"><Icon size={21} /></span>
                                    <span className="text-[10px] font-bold tracking-[.16em] text-black/20">0{index + 1}</span>
                                </div>
                                <h3 className="mt-7 text-base font-bold leading-6 text-[#171717]">{title}</h3>
                                <p className="mt-3 text-[13px] leading-6 text-[#5E5E5E]">{text}</p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            <section className="public-section bg-white">
                <div className="public-container">
                    <div className="mx-auto max-w-3xl text-center"><Header centered kicker="Notre processus" title="Une trajectoire lisible pour votre projet." /></div>
                    <div className="relative mt-12 grid gap-6 lg:grid-cols-5 lg:gap-0">
                        <div className="absolute left-[10%] right-[10%] top-6 hidden border-t border-dashed border-[#C8102E]/30 lg:block" />
                        {process.map((step, index) => (
                            <div key={step} className="relative z-10 flex items-center gap-4 lg:block lg:text-center">
                                <span className="grid size-12 shrink-0 place-items-center rounded-full border-4 border-white bg-[#C8102E] text-xs font-bold text-white shadow-[0_0_0_1px_rgba(200,16,46,.2)] lg:mx-auto">0{index + 1}</span>
                                <strong className="lg:mt-5 lg:block text-sm text-[#2F2F2F]">{step}</strong>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="bg-[#171717] py-11 text-white">
                <div className="public-container grid gap-7 text-center sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        [Globe2, 'Présence en Chine'],
                        [SearchCheck, 'Sourcing vérifié'],
                        [Plane, 'Fret aérien et maritime'],
                        [UserRoundCheck, 'Suivi personnalisé'],
                    ].map(([Icon, text]: any) => (
                        <div key={text} className="flex items-center justify-center gap-3 border-white/10 lg:border-r lg:last:border-0">
                            <Icon size={21} className="text-[#FFE600]" /><strong className="text-sm">{text}</strong>
                        </div>
                    ))}
                </div>
            </section>

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
                            <Sparkles className="mx-auto text-[#C8102E]" />
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
                        <h2 className="mt-3 max-w-3xl text-3xl font-bold tracking-[-.025em] sm:text-[34px]">Consultez les étapes avec votre accès sécurisé.</h2>
                        <p className="mt-3 max-w-2xl text-sm text-[#5E5E5E]">Votre numéro de commande et votre Tracking number suffisent pour retrouver les informations utiles.</p>
                    </div>
                    <Link href="/suivi" className="public-button">Suivre ma commande <ArrowRight size={16} /></Link>
                </div>
            </section>

            <section className="public-section bg-white">
                <div className="public-container grid gap-12 lg:grid-cols-[.85fr_1.15fr]">
                    <div>
                        <Header kicker="Pourquoi Madina Import" title="Proche du terrain. Proche de vos enjeux." />
                        <p className="mt-5 max-w-lg text-sm leading-7 text-[#5E5E5E]">Une méthode claire, du besoin initial jusqu’à la remise à Madagascar.</p>
                    </div>
                    <div>
                        <p className="public-kicker">Questions fréquentes</p>
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
                <div className="public-container grid gap-12 lg:grid-cols-[.75fr_1.25fr]">
                    <div>
                        <Header kicker="Parlons de votre projet" title="Une demande claire est le début d’un bon parcours." />
                        <a href={whatsapp} target="_blank" rel="noreferrer" className="public-text-link mt-7">Nous contacter sur WhatsApp <ArrowRight size={16} /></a>
                    </div>
                    <div className="rounded-[4px] border border-black/[.07] bg-white p-6 shadow-[0_12px_40px_rgba(23,23,23,.05)] sm:p-8">
                        <PublicContactForm flash={flash?.success} />
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
            <h2 className={`mt-3 text-[28px] font-bold leading-[1.16] tracking-[-.025em] text-[#171717] sm:text-[36px] ${centered ? 'mx-auto' : ''}`}>{title}</h2>
            {text && <p className={`mt-4 max-w-2xl text-sm leading-7 text-[#5E5E5E] ${centered ? 'mx-auto' : ''}`}>{text}</p>}
        </div>
    );
}
