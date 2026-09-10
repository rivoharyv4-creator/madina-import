import { MapPin, Navigation } from 'lucide-react';

export default function PublicLocationMap({ address, compact = false }: { address: string; compact?: boolean }) {
    const mapQuery = `Madina Import, ${address}, Madagascar`;
    const mapEmbedUrl = `https://www.google.com/maps?q=${encodeURIComponent(mapQuery)}&output=embed&t=k&z=18`;
    const directionsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(mapQuery)}`;

    if (compact) {
        return (
            <div className="contact-map contact-map-compact relative overflow-hidden rounded-[12px] border border-black/10 bg-white">
                <iframe
                    src={mapEmbedUrl}
                    title="Localisation Google Maps de Madina Import à Ambatomainty"
                    className="h-[380px] w-full border-0"
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                    allowFullScreen
                />
                <a href={directionsUrl} target="_blank" rel="noreferrer" className="contact-map-route absolute bottom-4 left-4 flex w-fit items-center justify-center gap-1.5 rounded-md bg-[#C8102E] px-3 py-2 text-[11px] font-bold text-white">
                    <Navigation size={13} /> Ouvrir l’itinéraire
                </a>
            </div>
        );
    }

    return (
        <div className="contact-map overflow-hidden rounded-[18px] border border-black/10 bg-white">
            <div className="grid lg:grid-cols-[.72fr_1.28fr]">
                <div className="contact-map-copy p-6 sm:p-8 lg:p-10">
                    <span className="contact-map-icon grid size-12 place-items-center rounded-2xl">
                        <MapPin size={23} aria-hidden="true" />
                    </span>
                    <p className="public-kicker mt-6">Nous trouver</p>
                    <h2 className="mt-3 text-2xl font-extrabold tracking-tight">Madina Import à Ambatomainty</h2>
                    <p className="contact-map-address mt-4 text-sm font-semibold leading-6">{address}</p>
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
    );
}
