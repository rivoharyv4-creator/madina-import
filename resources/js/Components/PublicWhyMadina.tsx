import { useEffect, useRef } from 'react';

const strengths = [
    { icon: '/icons/madina-3d/china-presence.webp', title: 'Présence physique en Chine', text: 'Visites d’usines, contrôle qualité avant expédition et intervention sur place en cas de problème.' },
    { icon: '/icons/madina-3d/chinese-communication.webp', title: 'Communication directe en chinois', text: 'Des échanges précis et une connaissance des pratiques locales pour faciliter les négociations.' },
    { icon: '/icons/madina-3d/supplier-network.webp', title: 'Un réseau de fournisseurs établi', text: 'Des contacts existants pour accélérer les recherches et identifier les partenaires adaptés à votre besoin.' },
    { icon: '/icons/madina-3d/complete-support.webp', title: 'Un accompagnement complet', text: 'Sourcing, comparaison, négociation, vérification, paiement en RMB, transport et dédouanement selon le projet.' },
];

const sectors = [
    { icon: '/icons/madina-3d/sector-bakery.webp', label: 'Boulangerie' },
    { icon: '/icons/madina-3d/sector-restaurant.webp', label: 'Restauration' },
    { icon: '/icons/madina-3d/sector-hotel.webp', label: 'Hôtellerie' },
    { icon: '/icons/madina-3d/sector-commerce.webp', label: 'Commerce' },
    { icon: '/icons/madina-3d/sector-industry.webp', label: 'Petite industrie' },
];

export default function PublicWhyMadina() {
    const section = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!section.current || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        // Content stays visible by default; only animate elements as they enter view.
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('why-entered');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });
        section.current.querySelectorAll('[data-why-reveal], [data-why-decor]').forEach((element) => observer.observe(element));
        return () => observer.disconnect();
    }, []);

    return (
        <section ref={section} aria-labelledby="why-madina-title" className="public-section why-madina">
            <img
                src="/brand/madina-china-left.svg"
                className="why-decor why-decor-china"
                data-why-decor
                width={560}
                height={720}
                alt=""
                aria-hidden="true"
                draggable="false"
            />
            <img
                src="/brand/madina-madagascar-right.svg"
                className="why-decor why-decor-madagascar"
                data-why-decor
                width={560}
                height={720}
                alt=""
                aria-hidden="true"
                draggable="false"
            />
            <div className="public-container why-content">
                <div className="why-grid">
                    <div className="why-heading" data-why-reveal>
                        <p className="public-kicker why-accent">POURQUOI MADINA IMPORT</p>
                        <h2 id="why-madina-title" className="mt-3 text-[27px] font-bold leading-[1.18] tracking-[-.025em] sm:text-[34px]">Présents en Chine. Engagés dans votre projet.</h2>
                        <p className="why-description mt-4 text-sm leading-7">Une connaissance du terrain et un accompagnement concret pour vos achats professionnels.</p>
                    </div>
                    <figure className="why-photo" data-why-reveal>
                        <img src="/brand/china-operations.webp" width={1448} height={1086} loading="lazy" decoding="async" alt="Illustration de deux professionnels vérifiant des marchandises dans un entrepôt, près d’un conteneur." />
                        <div className="why-experience">
                            <p className="text-[40px] font-extrabold leading-none tracking-tight sm:text-5xl">+5 ans</p>
                            <p className="mt-1 text-xl font-bold leading-tight tracking-tight">d’expérience</p>
                            <p className="why-description mt-2 max-w-[210px] text-sm leading-5">Sur le terrain, entre la Chine et Madagascar.</p>
                        </div>
                    </figure>
                    <ul className="why-strengths">
                        {strengths.map(({ icon, title, text }, index) => (
                            <li key={title} className="why-strength flex gap-4 py-5 first:pt-0 last:pb-0" data-why-reveal style={{ animationDelay: `${index * 55}ms` }}>
                                <span className="why-icon grid shrink-0 place-items-center">
                                    <img src={icon} width={256} height={256} loading="lazy" decoding="async" alt="" aria-hidden="true" />
                                </span>
                                <div className="min-w-0">
                                    <h3 className="text-base font-bold leading-6">{title}</h3>
                                    <p className="why-description mt-1.5 text-sm leading-6">{text}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
                <div className="why-sectors mt-10 rounded-xl px-4 py-6 sm:mt-12 sm:px-6 sm:py-7" data-why-reveal>
                    <h3 className="text-center text-base font-bold">Des équipements pour votre activité</h3>
                    <ul className="mt-5 grid grid-cols-2 gap-y-5 sm:grid-cols-3 lg:grid-cols-5">
                        {sectors.map(({ icon, label }) => (
                            <li key={label} className="why-sector flex flex-col items-center justify-center gap-2 px-2 text-center text-sm font-semibold sm:flex-row sm:gap-3">
                                <img className="why-sector-icon shrink-0" src={icon} width={256} height={256} loading="lazy" decoding="async" alt="" aria-hidden="true" />{label}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}
