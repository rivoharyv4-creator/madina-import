import PublicContactForm from '@/Components/PublicContactForm';
import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, usePage } from '@inertiajs/react';
import { ArrowUpRight, MapPin, MessageCircle, Navigation } from 'lucide-react';

export default function Contact({ publicConfig }: { publicConfig: PublicConfig }) {
    const { flash } = usePage().props as any;
    const whatsapp = `https://wa.me/${publicConfig.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Bonjour Madina Import, je souhaite parler de mon projet.')}`;
    const mapQuery = `Madina Import, ${publicConfig.address}, Madagascar`;
    const mapEmbedUrl = `https://www.google.com/maps?q=${encodeURIComponent(mapQuery)}&output=embed`;
    const directionsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(mapQuery)}`;

    return (
        <PublicLayout config={publicConfig}>
            <Head title="Contact">
                <meta name="description" content="Présentez votre projet d’importation à l’équipe Madina Import en Chine et à Madagascar." />
            </Head>

            <section className="public-section">
                <div className="public-container">
                    <div className="grid gap-14 lg:grid-cols-[.8fr_1.2fr]">
                        <div>
                            <p className="public-kicker">Un projet à faire avancer ?</p>
                            <h1 className="mt-5 text-4xl font-extrabold tracking-[-.04em] sm:text-[42px]">Parlons-en simplement.</h1>
                            <p className="mt-6 max-w-xl leading-8 text-[#5E5E5E]">Décrivez votre besoin et les contraintes déjà connues. Notre équipe vous recontactera pour clarifier la suite.</p>
                            <div className="mt-10 space-y-4 border-y border-black/10 py-7">
                                <ContactLine label="Madagascar" value={publicConfig.madagascar_phone} />
                                <ContactLine label="Chine" value={publicConfig.china_phone} />
                            </div>
                            <a href={whatsapp} target="_blank" rel="noreferrer" className="public-button mt-7">
                                <MessageCircle size={17} /> Nous contacter sur WhatsApp <ArrowUpRight size={16} />
                            </a>
                            {publicConfig.facebook_url && (
                                <a href={publicConfig.facebook_url} target="_blank" rel="noreferrer" className="public-text-link mt-5">
                                    Retrouvez-nous sur Facebook <ArrowUpRight size={16} />
                                </a>
                            )}
                            <div className="mt-10 flex gap-3 text-sm text-[#5E5E5E]">
                                <MapPin className="shrink-0 text-[#C8102E]" />
                                <p>Présence opérationnelle en Chine et accompagnement des projets à Madagascar.</p>
                            </div>
                        </div>

                        <div className="border border-black/10 bg-white p-6 sm:p-9">
                            <h2 className="text-2xl font-extrabold">Démarrer une demande</h2>
                            <p className="mb-7 mt-2 text-sm text-[#5E5E5E]">Les champs nous aident à orienter votre demande dès le premier échange.</p>
                            <PublicContactForm flash={flash?.success} />
                        </div>
                    </div>

                    <div className="contact-map mt-14 overflow-hidden rounded-[18px] border border-black/10 bg-white">
                        <div className="grid lg:grid-cols-[.72fr_1.28fr]">
                            <div className="contact-map-copy p-6 sm:p-8 lg:p-10">
                                <span className="contact-map-icon grid size-12 place-items-center rounded-2xl">
                                    <MapPin size={23} aria-hidden="true" />
                                </span>
                                <p className="public-kicker mt-6">Nous trouver</p>
                                <h2 className="mt-3 text-2xl font-extrabold tracking-tight">Madina Import à Ambatomainty</h2>
                                <p className="contact-map-address mt-4 text-sm font-semibold leading-6">{publicConfig.address}</p>
                                <p className="contact-map-note mt-4 text-sm leading-6">
                                    Sur la route principale vers Ambatomainty, passez devant Jovena. Madina Import se trouve à droite, environ 50 m avant la zone de l’église EKAR Kristy Mpanjaka.
                                </p>
                                <a href={directionsUrl} target="_blank" rel="noreferrer" className="public-button mt-7">
                                    <Navigation size={16} /> Ouvrir l’itinéraire
                                </a>
                            </div>

                            <div className="contact-map-frame min-h-[360px] lg:min-h-[440px]">
                                <iframe
                                    src={mapEmbedUrl}
                                    title="Localisation Google Maps de Madina Import à Ambatomainty"
                                    className="h-full min-h-[360px] w-full border-0 lg:min-h-[440px]"
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                    allowFullScreen
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

function ContactLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-5">
            <span className="text-sm text-[#5E5E5E]">{label}</span>
            <strong>{value}</strong>
        </div>
    );
}
