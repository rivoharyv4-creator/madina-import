import { ArrowRight, Check, Factory, SearchCheck, Ship, type LucideIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';

type Offer = {
    number: string;
    title: string;
    description: string;
    services: string[];
    icon: LucideIcon;
    badge?: string;
    featured?: boolean;
    sectors?: string[];
};

const offers: Offer[] = [
    {
        number: '01',
        title: 'Sourcing & vérification',
        description: 'Pour celui qui sait ce qu’il veut, mais ne maîtrise pas encore les fournisseurs chinois.',
        services: [
            'Recherche de fournisseurs',
            'Comparaison des options',
            'Négociation des prix',
            'Vérification de la qualité',
            'Vérification de la conformité',
        ],
        icon: SearchCheck,
    },
    {
        number: '02',
        title: 'Import clé en main',
        description: 'Pour le client qui souhaite déléguer presque toutes les étapes de son importation.',
        services: [
            'Sourcing du produit',
            'Achat et paiement en RMB',
            'Transport international',
            'Formalités douanières selon le projet',
            'Suivi de commande',
            'Accompagnement jusqu’à la livraison',
        ],
        icon: Ship,
        badge: 'ACCOMPAGNEMENT COMPLET',
        featured: true,
    },
    {
        number: '03',
        title: 'Projet équipement professionnel',
        description: 'Pour les professionnels qui souhaitent équiper une boulangerie, un restaurant, un hôtel, un commerce ou une petite industrie.',
        services: [
            'Analyse des besoins',
            'Sélection des machines adaptées',
            'Budget estimatif détaillé',
            'Recherche des fournisseurs',
            'Coordination complète de l’import',
        ],
        icon: Factory,
        sectors: ['Boulangerie', 'Restauration', 'Hôtellerie', 'Commerce', 'Petite industrie'],
    },
];

export default function PublicOffers() {
    const section = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!section.current || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('offers-entered');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        section.current.querySelectorAll('[data-offers-reveal]').forEach((element) => observer.observe(element));
        return () => observer.disconnect();
    }, []);

    return (
        <section ref={section} id="offres" aria-labelledby="offers-title" className="public-section offers-section scroll-mt-[72px]">
            <div className="public-container">
                <header className="mx-auto max-w-3xl text-center" data-offers-reveal>
                    <p className="public-kicker offers-accent">NOS OFFRES</p>
                    <h2 id="offers-title" className="mt-3 text-[27px] font-bold leading-[1.18] tracking-[-.025em] sm:text-[34px]">
                        Un accompagnement adapté à votre projet.
                    </h2>
                    <p className="offers-muted mx-auto mt-4 max-w-2xl text-sm leading-7">
                        De la recherche d’un fournisseur jusqu’à la livraison de vos équipements, choisissez le niveau d’accompagnement qui correspond à votre besoin.
                    </p>
                </header>

                <div className="offers-grid mt-10">
                    {offers.map((offer, index) => (
                        <OfferCard key={offer.number} offer={offer} index={index} />
                    ))}
                </div>

                <aside className="offers-cta mt-10" aria-labelledby="offers-cta-title" data-offers-reveal style={{ animationDelay: '420ms' }}>
                    <div>
                        <h3 id="offers-cta-title" className="text-lg font-bold tracking-tight sm:text-xl">
                            Vous ne savez pas quelle offre correspond à votre projet ?
                        </h3>
                        <p className="offers-muted mt-2 text-sm leading-6">
                            Présentez-nous votre besoin. Nous vous orienterons vers l’accompagnement le plus adapté.
                        </p>
                    </div>
                    <a href="/contact" className="public-button offers-cta-button">
                        Parler de mon projet <ArrowRight size={16} aria-hidden="true" />
                    </a>
                </aside>
            </div>
        </section>
    );
}

function OfferCard({ offer, index }: { offer: Offer; index: number }) {
    const Icon = offer.icon;

    return (
        <article
            className={`offer-card ${offer.featured ? 'offer-card-featured' : ''}`}
            aria-labelledby={`offer-title-${offer.number}`}
            data-offers-reveal
            style={{ animationDelay: `${140 + index * 120}ms` }}
        >
            <span className="offer-watermark" aria-hidden="true">{offer.number}</span>
            <div className="offer-card-content">
                <div className="flex items-start justify-between gap-4">
                    <span className="offer-icon" aria-hidden="true"><Icon size={25} strokeWidth={1.8} /></span>
                    <span className="offer-number" aria-hidden="true">{offer.number}</span>
                </div>

                <div className="offer-heading">
                    {offer.badge && <p className="offer-badge">{offer.badge}</p>}
                    <h3 id={`offer-title-${offer.number}`} className="mt-4 text-xl font-bold leading-7 tracking-tight">{offer.title}</h3>
                    <p className="offers-muted mt-3 text-sm leading-6">{offer.description}</p>
                </div>

                <div className="offer-divider" />

                <ul className="offer-services" aria-label={`Services inclus dans l’offre ${offer.title}`}>
                    {offer.services.map((service) => (
                        <li key={service}>
                            <span className="offer-check" aria-hidden="true"><Check size={13} strokeWidth={3} /></span>
                            <span>{service}</span>
                        </li>
                    ))}
                </ul>

                {offer.sectors && (
                    <p className="offer-sectors" aria-label="Secteurs concernés">{offer.sectors.join(' · ')}</p>
                )}
            </div>
        </article>
    );
}
