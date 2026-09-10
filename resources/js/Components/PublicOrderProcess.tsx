import { useEffect, useRef } from 'react';

type ProcessChapter = {
    title: string;
    description: string;
    icon: string;
    start: number;
    steps: { title: string; detail?: string }[];
};

const chapters: ProcessChapter[] = [
    {
        title: 'Préparer votre projet',
        description: 'Nous précisons votre besoin et recherchons les fournisseurs adaptés.',
        icon: '/icons/madina-3d/process-search.webp',
        start: 1,
        steps: [
            { title: 'Demande de devis', detail: 'Photo, quantité, exigences et détails, utilisation, budget prévu.' },
            { title: 'Recherche fournisseur' },
            { title: 'Envoi du devis' },
        ],
    },
    {
        title: 'Confirmer votre commande',
        description: 'Vous validez les conditions avant le lancement de l’achat.',
        icon: '/icons/madina-3d/process-confirm.webp',
        start: 4,
        steps: [
            { title: 'Commande' },
            { title: 'Facture' },
            { title: 'Validation' },
            { title: 'Paiement de l’acompte' },
            { title: 'Commande auprès du fournisseur' },
        ],
    },
    {
        title: 'Acheminer et livrer',
        description: 'Votre commande est suivie jusqu’à sa livraison à Madagascar.',
        icon: '/icons/madina-3d/process-delivery.webp',
        start: 9,
        steps: [
            { title: 'Expédition' },
            { title: 'Suivi de commande' },
            { title: 'Arrivée à Madagascar' },
            { title: 'Livraison' },
        ],
    },
];

export default function PublicOrderProcess() {
    const section = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!section.current || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        // Visible by default: a missing observer never leaves the content hidden.
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add(entry.target.hasAttribute('data-process-port') ? 'process-port-entered' : 'process-entered');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.1 });
        section.current.querySelectorAll('[data-process-reveal]').forEach((element) => observer.observe(element));
        return () => observer.disconnect();
    }, []);

    return (
        <section ref={section} aria-labelledby="order-process-title" className="public-section order-process">
            <img src="/brand/madina-port-filigrane.svg" width={1200} height={460} alt="" aria-hidden="true" className="process-port" data-process-port data-process-reveal />
            <div className="public-container process-content">
                <div className="mx-auto max-w-3xl text-center" data-process-reveal>
                    <p className="public-kicker process-accent">NOTRE PROCESSUS</p>
                    <h2 id="order-process-title" className="mt-3 text-[27px] font-bold leading-[1.18] tracking-[-.025em] sm:text-[34px]">De votre demande à la livraison.</h2>
                    <p className="process-description mt-4 text-sm leading-7">Un parcours en trois phases pour comprendre chaque étape de votre importation.</p>
                </div>
                <div className="process-chapters mt-10">
                    {chapters.map((chapter, index) => <Chapter key={chapter.start} chapter={chapter} index={index} />)}
                </div>
                <div className="process-signature-zone">
                    <p className="process-signature">Le monde<br />plus proche de vous.</p>
                </div>
            </div>
        </section>
    );
}

function Chapter({ chapter, index }: { chapter: ProcessChapter; index: number }) {
    return (
        <article className="process-chapter" aria-labelledby={`process-chapter-${chapter.start}`} data-process-reveal style={{ animationDelay: `${index * 100}ms` }}>
            <div className="flex items-center justify-between gap-4" aria-hidden="true">
                <img src={chapter.icon} width={256} height={256} alt="" aria-hidden="true" className="process-chapter-icon" />
                <span className="process-chapter-number text-5xl font-bold leading-none tabular-nums">{String(index + 1).padStart(2, '0')}</span>
            </div>
            <h3 id={`process-chapter-${chapter.start}`} className="text-xl font-bold leading-7 tracking-tight">{chapter.title}</h3>
            <p className="process-description text-sm leading-6">{chapter.description}</p>
            <ol start={chapter.start} className="process-steps">
                {chapter.steps.map((step) => (
                    <li key={step.title}>
                        <span className="font-semibold">{step.title}</span>
                        {step.detail && <p className="process-description mt-2 text-sm font-normal leading-6">{step.detail}</p>}
                    </li>
                ))}
            </ol>
        </article>
    );
}
