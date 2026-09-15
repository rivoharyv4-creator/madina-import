import PublicContactForm from '@/Components/PublicContactForm';
import PublicLocationMap from '@/Components/PublicLocationMap';
import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, usePage } from '@inertiajs/react';
import { ArrowUpRight, MapPin } from 'lucide-react';

export default function Contact({ publicConfig }: { publicConfig: PublicConfig }) {
    const { flash } = usePage().props as any;
    const whatsapp = `https://wa.me/${publicConfig.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Bonjour Madina Import, je souhaite parler de mon projet.')}`;

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
                            <a href={whatsapp} target="_blank" rel="noreferrer" className="public-button mt-7 !bg-[#25D366] hover:!bg-[#1EAD55]">
                                <WhatsAppIcon /> WhatsApp <ArrowUpRight size={16} />
                            </a>
                            {publicConfig.facebook_url && (
                                <a href={publicConfig.facebook_url} target="_blank" rel="noreferrer" className="public-text-link mt-5 !text-[#1877F2] hover:!text-[#1264D6]">
                                    <FacebookIcon /> Facebook <ArrowUpRight size={16} />
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

                    <div className="mt-14"><PublicLocationMap address={publicConfig.address} /></div>
                </div>
            </section>
        </PublicLayout>
    );
}

function WhatsAppIcon() {
    return (
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.149-.173.198-.297.298-.496.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.075-.792.372-.272.297-1.04 1.016-1.04 2.479s1.065 2.875 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.895 9.825 9.825 0 0 1 2.893 6.99c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
        </svg>
    );
}

function FacebookIcon() {
    return (
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
            <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073c0 6.026 4.388 11.022 10.125 11.927v-8.436H7.078v-3.491h3.047V9.413c0-3.026 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.969H15.83c-1.491 0-1.956.931-1.956 1.884v2.268h3.328l-.532 3.491h-2.796V24C19.612 23.095 24 18.099 24 12.073Z" />
        </svg>
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
