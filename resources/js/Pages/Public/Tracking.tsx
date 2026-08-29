import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, useForm } from '@inertiajs/react';
import { Check, ChevronRight, ClipboardCheck, Clock3, Handshake, Hash, MapPinCheck, Navigation, PackageCheck, PackageSearch, Search, ShieldCheck, ShoppingCart, Truck, Warehouse } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Tracking = {
    number: string;
    matched_tracking?: string;
    status: string;
    shipping_mode?: string;
    updated_at: string;
    items: { name: string; status: string }[];
    shipments: any[];
    steps: { label: string; state: 'complete' | 'current' | 'upcoming' }[];
};

const date = (value?: string) => value ? new Date(value).toLocaleDateString('fr-FR') : '—';
const trackingStepIcons=[ClipboardCheck,ShoppingCart,Warehouse,PackageCheck,Navigation,MapPinCheck,Handshake];

export default function TrackingPage({ tracking, lookupError, publicConfig }: { tracking: Tracking | null; lookupError?: string; publicConfig: PublicConfig }) {
    const { data, setData, post, processing, errors } = useForm({ order_number: '', tracking_number: '' });
    const [selectedShipmentIndex, setSelectedShipmentIndex] = useState<number | null>(null);
    const matchedIndex = tracking?.matched_tracking ? tracking.shipments.findIndex(shipment => String(shipment.tracking).toLowerCase() === String(tracking.matched_tracking).toLowerCase()) : -1;
    const activeIndex = selectedShipmentIndex===null ? Math.max(0,matchedIndex) : Math.min(selectedShipmentIndex,Math.max(0,(tracking?.shipments.length||1)-1));
    const activeShipment = tracking?.shipments[activeIndex] || tracking?.shipments[0];
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/suivi', { preserveScroll: true });
    };

    return (
        <PublicLayout config={publicConfig}>
            <Head title="Suivre une commande">
                <meta name="description" content="Accédez au suivi sécurisé de votre commande Madina Import." />
            </Head>

            <section className="tracking-hero border-b border-black/10 bg-white px-4 py-16 text-[#171717] sm:py-24">
                <div className="tracking-card mx-auto max-w-[760px] rounded-[28px] border border-black/[.07] bg-[#F7F7F7] px-5 py-10 shadow-[0_22px_70px_rgba(23,23,23,.10)] sm:px-14 sm:py-14">
                    <div className="mx-auto max-w-xl text-center">
                        <span className="mx-auto grid size-12 place-items-center rounded-full bg-[#C8102E]/10 text-[#C8102E]">
                            <ShieldCheck size={23} />
                        </span>
                        <h1 className="mt-5 text-4xl font-black tracking-[-.045em] sm:text-5xl">Suivi de colis</h1>
                        <p className="mt-3 text-sm leading-6 text-black/55">Saisissez les informations transmises par votre interlocuteur Madina Import pour consulter votre commande.</p>
                    </div>

                    <div className="mx-auto mt-8 flex w-fit items-center gap-2 rounded-full border border-[#C8102E]/25 bg-white px-4 py-2 text-xs font-bold text-[#C8102E]">
                        <Hash size={15} /> Numéro de commande + Tracking number
                    </div>

                    <form onSubmit={submit} className="mx-auto mt-8 grid max-w-[500px] gap-5">
                        <label>
                            <span className="mb-2 block text-xs font-bold">Numéro de commande</span>
                            <span className="relative block">
                                <Hash className="absolute left-4 top-1/2 -translate-y-1/2 text-black/35" size={19} />
                                <input value={data.order_number} onChange={e => setData('order_number', e.target.value)} className="public-field !rounded-xl !py-4 !pl-12" placeholder="Exemple : MI-2026-001" required />
                            </span>
                            {errors.order_number && <small className="mt-1 block text-[#C8102E]">{errors.order_number}</small>}
                        </label>
                        <label>
                            <span className="mb-2 block text-xs font-bold">Tracking number</span>
                            <span className="relative block">
                                <PackageSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-black/35" size={18} />
                                <input value={data.tracking_number} onChange={e => setData('tracking_number', e.target.value)} className="public-field !rounded-xl !py-4 !pl-12 font-mono" placeholder="Saisissez votre Tracking number" autoComplete="off" required />
                            </span>
                            {errors.tracking_number && <small className="mt-1 block text-[#C8102E]">{errors.tracking_number}</small>}
                        </label>
                        {lookupError && <p className="rounded-xl bg-[#C8102E]/8 px-4 py-3 text-sm font-semibold text-[#C8102E]">{lookupError}</p>}
                        <button disabled={processing} className="public-button mt-1 !w-full !justify-center !rounded-full !py-4">
                            <Search size={17} /> {processing ? 'Vérification…' : 'Rechercher mon colis'}
                        </button>
                    </form>
                </div>
            </section>

            {tracking && (
                <section className="tracking-result-zone overflow-hidden py-14 sm:py-20">
                    <div className="public-container tracking-result-enter">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div><p className="text-2xl font-black tracking-[-.03em]">{tracking.shipments.length} résultat{tracking.shipments.length > 1 ? 's' : ''} trouvé{tracking.shipments.length > 1 ? 's' : ''}</p><p className="tracking-result-muted mt-1 text-xs">Sélectionnez une expédition pour consulter son détail.</p></div>
                            <span className="tracking-order-chip rounded-full border px-4 py-2 text-xs">Commande <strong className="ml-1">{tracking.number}</strong></span>
                        </div>

                        {!!tracking.shipments.length && <div className="mt-7 grid gap-3">
                            {tracking.shipments.map((shipment,index)=>{const meta=statusMeta(shipment.status);return <button type="button" key={`${shipment.tracking}-${index}`} onClick={()=>setSelectedShipmentIndex(index)} style={{animationDelay:`${index*90}ms`}} className={`tracking-result-row tracking-shipment-row group flex w-full items-center justify-between gap-5 rounded-2xl border px-5 py-4 text-left transition duration-300 hover:-translate-y-0.5 ${activeIndex===index?'selected':''}`}>
                                <span><strong className="block font-mono text-sm">#{shipment.tracking||`EXP-${index+1}`}</strong><small className="tracking-result-muted mt-1 block">{shipment.container_reference||`Expédition ${index+1}`}</small></span><StatusBadge label={meta.label} tone={meta.tone}/>
                            </button>})}
                        </div>}

                        {activeShipment && <div key={`${activeShipment.tracking}-${activeIndex}`} className="tracking-detail-enter tracking-detail-border mt-10 border-t pt-10">
                            <div className="tracking-current-card rounded-[26px] border p-6 sm:p-8">
                                <div className="flex flex-wrap items-start justify-between gap-5"><div><p className="tracking-result-muted text-xs font-bold uppercase tracking-[.18em]">Statut actuel</p><h2 className="mt-3 text-2xl font-black">{statusMeta(activeShipment.status).label}</h2><p className="tracking-result-muted mt-2 font-mono text-xs">Tracking number : #{activeShipment.tracking}</p></div><StatusBadge label={statusMeta(activeShipment.status).label} tone={statusMeta(activeShipment.status).tone}/></div>
                                <p className="tracking-result-copy mt-6 max-w-2xl text-sm leading-7">{statusMessage(activeShipment.status)}</p>
                                <p className="tracking-result-subtle mt-4 flex items-center gap-2 text-xs"><Clock3 size={14}/>Dernière mise à jour : {date(activeShipment.updated_at||tracking.updated_at)}</p>
                            </div>

                            <div className="tracking-result-panel mt-7 rounded-[26px] border p-6 sm:p-8">
                                <div className="flex items-center justify-between"><h3 className="text-lg font-black">Suivi</h3><span className="tracking-result-subtle text-[10px] font-bold uppercase tracking-[.17em]">Progression en direct</span></div>
                                <ol className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                                    {tracking.steps.map((step,index)=>{const StepIcon=trackingStepIcons[index]||PackageCheck;return <li key={step.label} style={{animationDelay:`${index*75}ms`}} className={`tracking-journey-card tracking-step-${step.state} relative flex min-h-[148px] flex-col rounded-2xl border p-3.5 ${step.state}`}>
                                        {index<tracking.steps.length-1&&<span className="tracking-journey-arrow absolute -right-[14px] top-1/2 z-10 hidden size-5 -translate-y-1/2 items-center justify-center rounded-full lg:flex"><ChevronRight size={12} strokeWidth={2.4}/></span>}
                                        <div className="flex items-center justify-between"><span className="tracking-step-number text-[9px] font-black uppercase tracking-[.16em]">Étape {String(index+1).padStart(2,'0')}</span>{step.state==='complete'&&<span className="tracking-complete-mark grid size-5 place-items-center rounded-full"><Check size={11} strokeWidth={3}/></span>}</div>
                                        <span className="tracking-journey-icon mt-4 grid size-10 place-items-center rounded-xl"><StepIcon size={19} strokeWidth={1.8}/></span>
                                        <strong className="mt-3 text-[11px] leading-4">{step.label}</strong>
                                        <small className="tracking-step-state mt-auto pt-2 text-[8px] font-black uppercase tracking-[.14em]">{step.state==='complete'?'Terminé':step.state==='current'?'En cours':'À venir'}</small>
                                    </li>})}
                                </ol>
                            </div>

                            <div className="mt-7">
                                <div className="tracking-result-panel rounded-[26px] border p-6 sm:p-7"><div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-xl bg-sky-500/10 text-sky-500"><Truck size={17}/></span><h3 className="font-black">Transport</h3></div><dl className="tracking-result-divide mt-5 divide-y"><DarkInfo label="Identifiant" value={activeShipment.container_reference||activeShipment.tracking}/><DarkInfo label="Transitaire" value={activeShipment.forwarder||'À confirmer'}/><DarkInfo label="Mode" value={activeShipment.mode||tracking.shipping_mode||'À confirmer'}/><DarkInfo label="Départ de Chine" value={date(activeShipment.china_departure_at)}/><DarkInfo label="Arrivée prévue" value={date(activeShipment.expected_madagascar_at)}/><DarkInfo label="Volume" value={activeShipment.cbm?`${activeShipment.cbm} CBM`:'—'}/><DarkInfo label="Colis / cartons" value={`${activeShipment.package_count||0} / ${activeShipment.carton_count||0}`}/></dl></div>
                            </div>
                        </div>}
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}

function statusMeta(status?:string):{label:string;tone:'green'|'blue'|'amber'} {
    const value=String(status||'en_attente');
    const labels:Record<string,string>={commande_lancee:'Commande lancée',en_attente:'En attente de livraison',arrive_en_chine:'Reçu au dépôt Chine',expedie:'Expédié',en_transit:'En transit',arrive_madagascar:'Arrivé à Madagascar',remis_client:'Prêt au retrait'};
    return {label:labels[value]||value.replaceAll('_',' '),tone:['arrive_madagascar','remis_client'].includes(value)?'green':value==='en_transit'||value==='expedie'?'blue':'amber'};
}

function statusMessage(status?:string):string {
    return {commande_lancee:'Votre commande est confirmée et sa préparation logistique commence.',en_attente:'Votre colis est en préparation avant sa prise en charge.',arrive_en_chine:'Votre colis a été reçu et contrôlé dans notre dépôt en Chine.',expedie:'Votre colis a quitté notre dépôt et son acheminement a commencé.',en_transit:'Votre colis est actuellement en route vers Madagascar.',arrive_madagascar:'Votre colis est arrivé à Madagascar et passe les dernières étapes de traitement.',remis_client:'Votre colis est prêt. Préparez votre référence avant de vous déplacer.'}[String(status)]||'Le suivi de votre colis a été mis à jour.';
}

function StatusBadge({label,tone}:{label:string;tone:'green'|'blue'|'amber'}) {
    const styles={green:'border-emerald-500/20 bg-emerald-500/10 text-emerald-400',blue:'border-sky-500/20 bg-sky-500/10 text-sky-400',amber:'border-amber-400/20 bg-amber-400/10 text-amber-300'};
    return <span className={`tracking-status-badge tone-${tone} inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 py-1.5 text-[11px] font-bold ${styles[tone]}`}><span className="size-1.5 rounded-full bg-current shadow-[0_0_10px_currentColor]"/>{label}</span>;
}

function DarkInfo({label,value}:{label:string;value:any}) {
    return <div className="flex items-center justify-between gap-5 py-3.5 text-sm"><dt className="tracking-result-muted">{label}</dt><dd className="text-right font-bold capitalize">{value||'—'}</dd></div>;
}
