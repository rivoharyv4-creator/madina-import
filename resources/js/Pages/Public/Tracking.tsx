import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Box, Hash, MapPinCheck, Navigation, PackageSearch, Phone, Radio, RefreshCw, Search, ShieldCheck, UserRound } from 'lucide-react';
import { FormEvent, type ReactNode, useState } from 'react';

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
const resultSteps=['Entrepôt','Préparée','Transit','Arrivée','Remise'];

export default function TrackingPage({ tracking, lookupError, publicConfig }: { tracking: Tracking | null; lookupError?: string; publicConfig: PublicConfig }) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({ mode: 'number', order_number: '', tracking_number: '', recipient_name: '', phone: '' });
    const [lookupMode, setLookupMode] = useState<'number' | 'name'>('number');
    const [selectedShipmentIndex, setSelectedShipmentIndex] = useState<number | null>(null);
    const matchedIndex = tracking?.matched_tracking ? tracking.shipments.findIndex(shipment => String(shipment.tracking).toLowerCase() === String(tracking.matched_tracking).toLowerCase()) : -1;
    const activeIndex = selectedShipmentIndex===null ? Math.max(0,matchedIndex) : Math.min(selectedShipmentIndex,Math.max(0,(tracking?.shipments.length||1)-1));
    const activeShipment = tracking?.shipments[activeIndex] || tracking?.shipments[0];
    const activeStage = shipmentStage(activeShipment);
    const routeProgress = [.08,.28,.58,.82,1][activeStage];
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/suivi', { preserveScroll: true });
    };
    const changeLookupMode = (mode: 'number' | 'name') => {
        setLookupMode(mode);
        setData('mode', mode);
        clearErrors();
    };
    const refreshResult = () => {
        if(window.location.pathname.startsWith('/suivi/securise/')) {
            router.reload();
            return;
        }
        post('/suivi', { preserveScroll: true });
    };

    return (
        <PublicLayout config={publicConfig}>
            <Head title="Suivre une commande">
                <meta name="description" content="Accédez au suivi sécurisé de votre commande Madina Import." />
            </Head>

            {!tracking && <section className="tracking-hero flex flex-1 items-center justify-center px-4 py-14 text-[#171717] sm:py-20">
                <div className="tracking-card mx-auto max-w-[440px] overflow-hidden rounded-[10px] border px-6 py-9 sm:px-9 sm:py-10">
                    <div className="tracking-card-water" aria-hidden="true" />
                    <div className="tracking-card-content">
                        <div className="mx-auto max-w-sm text-center">
                            <span className="tracking-shield mx-auto grid size-[52px] place-items-center rounded-full text-[#C8102E]">
                                <ShieldCheck size={24} />
                            </span>
                            <h1 className="mt-5 text-[25px] font-extrabold tracking-[-.04em]">Suivi de colis</h1>
                            <p className="tracking-card-subtitle mx-auto mt-2 max-w-[340px] text-[13px] leading-5">Recherchez votre expédition par numéro de commande ou par nom du destinataire.</p>
                        </div>

                        <div className={`tracking-segment mt-7 grid grid-cols-2 gap-1 rounded-lg border p-1 ${lookupMode === 'name' ? 'mode-name' : ''}`} role="group" aria-label="Méthode de recherche">
                            <span className="tracking-segment-slider" aria-hidden="true" />
                            <button type="button" onClick={() => changeLookupMode('number')} className={`tracking-segment-button flex items-center justify-center gap-2 rounded-md px-2 py-2.5 text-xs font-bold ${lookupMode === 'number' ? 'active' : ''}`} aria-pressed={lookupMode === 'number'}>
                                <Hash size={14} /> Par numéro
                            </button>
                            <button type="button" onClick={() => changeLookupMode('name')} className={`tracking-segment-button flex items-center justify-center gap-2 rounded-md px-2 py-2.5 text-xs font-bold ${lookupMode === 'name' ? 'active' : ''}`} aria-pressed={lookupMode === 'name'}>
                                <UserRound size={14} /> Par nom
                            </button>
                        </div>

                        <form onSubmit={submit} className="mt-6 grid gap-4">
                            <div key={lookupMode} className="tracking-form-panel grid gap-4">
                                {lookupMode === 'number' ? <>
                                    <label>
                                        <span className="tracking-field-label mb-2 block text-xs font-bold">Numéro de commande</span>
                                        <span className="tracking-input-wrap relative block rounded-lg border">
                                            <Hash className="tracking-input-icon absolute left-4 top-1/2 -translate-y-1/2" size={16} />
                                            <input value={data.order_number} onChange={e => setData('order_number', e.target.value)} className="tracking-input w-full rounded-lg border-0 bg-transparent py-3.5 pl-11 pr-4 text-[13px] focus:ring-0" placeholder="Exemple : MI-2026-001" required autoFocus />
                                        </span>
                                        {errors.order_number && <small className="mt-1 block text-[#C8102E]">{errors.order_number}</small>}
                                    </label>
                                    <label>
                                        <span className="tracking-field-label mb-2 block text-xs font-bold">Tracking number</span>
                                        <span className="tracking-input-wrap relative block rounded-lg border">
                                            <PackageSearch className="tracking-input-icon absolute left-4 top-1/2 -translate-y-1/2" size={16} />
                                            <input value={data.tracking_number} onChange={e => setData('tracking_number', e.target.value)} className="tracking-input w-full rounded-lg border-0 bg-transparent py-3.5 pl-11 pr-4 font-mono text-[13px] focus:ring-0" placeholder="Saisissez votre tracking number" autoComplete="off" required />
                                        </span>
                                        {errors.tracking_number && <small className="mt-1 block text-[#C8102E]">{errors.tracking_number}</small>}
                                    </label>
                                </> : <>
                                    <label>
                                        <span className="tracking-field-label mb-2 block text-xs font-bold">Nom complet</span>
                                        <span className="tracking-input-wrap relative block rounded-lg border">
                                            <UserRound className="tracking-input-icon absolute left-4 top-1/2 -translate-y-1/2" size={16} />
                                            <input value={data.recipient_name} onChange={e => setData('recipient_name', e.target.value)} className="tracking-input w-full rounded-lg border-0 bg-transparent py-3.5 pl-11 pr-4 text-[13px] focus:ring-0" placeholder="Exemple : Rakoto Andrianina" autoComplete="name" required autoFocus />
                                        </span>
                                        {errors.recipient_name && <small className="mt-1 block text-[#C8102E]">{errors.recipient_name}</small>}
                                    </label>
                                    <label>
                                        <span className="tracking-field-label mb-2 block text-xs font-bold">Numéro de téléphone</span>
                                        <span className="tracking-input-wrap relative block rounded-lg border">
                                            <Phone className="tracking-input-icon absolute left-4 top-1/2 -translate-y-1/2" size={16} />
                                            <input type="tel" value={data.phone} onChange={e => setData('phone', e.target.value)} className="tracking-input w-full rounded-lg border-0 bg-transparent py-3.5 pl-11 pr-4 text-[13px] focus:ring-0" placeholder="+261 34 00 000 00" autoComplete="tel" required />
                                        </span>
                                        {errors.phone && <small className="mt-1 block text-[#C8102E]">{errors.phone}</small>}
                                    </label>
                                </>}
                            </div>
                            {lookupError && <p className="tracking-lookup-error rounded-lg border px-4 py-3 text-sm font-semibold text-[#C8102E]">{lookupError}</p>}
                            <button disabled={processing} className="tracking-submit mt-0.5 flex w-full items-center justify-center gap-2 rounded-lg bg-[#C8102E] px-5 py-3.5 text-sm font-extrabold text-white transition hover:bg-[#a90d27] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#C8102E]/25 disabled:opacity-60">
                                <Search size={17} /> {processing ? 'Vérification…' : 'Rechercher'}
                            </button>
                        </form>
                    </div>
                </div>
            </section>}

            {tracking && (
                <section className="tracking-result-showcase flex flex-1 items-center justify-center overflow-hidden px-4 py-14 sm:py-20">
                    {activeShipment ? <article key={`${activeShipment.tracking}-${activeIndex}`} className="tracking-result-card tracking-result-card-enter w-full max-w-[460px] overflow-hidden rounded-[10px] border px-6 py-8 sm:px-8">
                        <div className="tracking-result-content relative z-10">
                            <p className="tracking-result-kicker text-[10px] font-extrabold uppercase tracking-[.2em]">Madina Import · Fret {activeShipment.mode==='aerien'?'aérien':'maritime'}</p>
                            <h1 className="mt-4 flex items-center gap-2 text-[25px] font-extrabold tracking-[-.035em]">
                                {resultHeadline(activeShipment.status)}
                                <span className="tracking-live-dot grid size-4 shrink-0 place-items-center rounded-full" aria-label="Statut actif"><span className="size-1.5 rounded-full" /></span>
                            </h1>
                            <p className="tracking-result-reference mt-1 text-[12px]">Tracking #{activeShipment.tracking||`EXP-${activeIndex+1}`} · {activeShipment.forwarder||activeShipment.container_reference||'Madina Cargo'}</p>

                            {tracking.shipments.length>1&&<div className="tracking-result-switcher mt-4 flex flex-wrap gap-1.5" aria-label="Choisir une expédition">
                                {tracking.shipments.map((shipment,index)=><button key={`${shipment.tracking}-${index}`} type="button" onClick={()=>setSelectedShipmentIndex(index)} className={activeIndex===index?'active':''}>{index+1}</button>)}
                            </div>}

                            <div className="mt-8">
                                <svg className="tracking-route w-full overflow-visible" viewBox="0 0 392 82" role="img" aria-label="Trajet de Chine vers Madagascar">
                                    <path d="M12 60 Q196 -2 380 60" fill="none" stroke="currentColor" strokeWidth="2" strokeDasharray="4 6" className="tracking-route-pending" />
                                    <path d="M12 60 Q196 -2 380 60" fill="none" stroke="currentColor" strokeWidth="3" pathLength="100" className="tracking-route-complete">
                                        <animate attributeName="stroke-dasharray" from="0 100" to={`${routeProgress*100} 100`} dur="1.6s" fill="freeze" calcMode="spline" keySplines="0.16 1 0.3 1" />
                                    </path>
                                    <circle cx="12" cy="60" r="4" className="tracking-route-start" />
                                    <circle cx="380" cy="60" r="4" className="tracking-route-end" />
                                    <g className="tracking-route-vehicle">
                                        <path d="M-9 0h18L6 6H-5z" fill="currentColor" />
                                        <path d="M-4 0v-6h7l3 6M-1-6v-4h3v4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
                                        <animateMotion dur="1.6s" fill="freeze" path="M12 60 Q196 -2 380 60" keyPoints={`0;${routeProgress}`} keyTimes="0;1" calcMode="spline" keySplines="0.16 1 0.3 1" />
                                    </g>
                                </svg>
                                <div className="tracking-route-labels -mt-1 flex justify-between text-[10px]">
                                    <span>Chine</span>
                                    <span>Madagascar</span>
                                </div>
                            </div>

                            <dl className="mt-3 grid grid-cols-2 gap-2">
                                <ResultFact icon={<Navigation size={13} />} label="Départ" value={date(activeShipment.china_departure_at)} />
                                <ResultFact icon={<MapPinCheck size={13} />} label="Arrivée prévue" value={date(activeShipment.expected_madagascar_at)} />
                                <ResultFact icon={<Box size={13} />} label="Volume" value={activeShipment.cbm?`${activeShipment.cbm} CBM`:'—'} />
                                <ResultFact icon={<Radio size={13} />} label="Statut" value={statusMeta(activeShipment.status).label} accent />
                            </dl>

                            <ol className="mt-5 grid grid-cols-5 gap-1.5">
                                {resultSteps.map((label,index)=><li key={label} style={{animationDelay:`${.7+index*.1}s`}} className={`tracking-result-step ${index<activeStage?'complete':index===activeStage?'current':'upcoming'} flex min-h-9 items-center justify-center rounded-md border px-1 text-center text-[9px] font-bold`}>{label}</li>)}
                            </ol>

                            <div className="tracking-result-actions mt-5 grid grid-cols-2 gap-2">
                                <Link href="/suivi" className="tracking-result-action flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-[11px] font-bold">
                                    <Search size={14} /> Nouvelle recherche
                                </Link>
                                <button type="button" onClick={refreshResult} disabled={processing} className="tracking-result-action flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-[11px] font-bold disabled:opacity-50">
                                    <RefreshCw size={14} className={processing?'animate-spin':''} /> Actualiser
                                </button>
                            </div>
                        </div>
                    </article> : <div className="tracking-empty-result rounded-xl border px-6 py-8 text-center"><strong>Suivi en préparation</strong><p className="mt-2 text-sm">Aucune expédition n’est encore associée à cette commande.</p></div>}
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

function resultHeadline(status?:string):string {
    return {commande_lancee:'Commande confirmée',en_attente:'Préparation de votre colis',arrive_en_chine:'Arrivé à l’entrepôt en Chine',expedie:'Expédié vers Madagascar',en_transit:'En transit vers Madagascar',arrive_madagascar:'Arrivé à Madagascar',remis_client:'Remis au client'}[String(status)]||statusMeta(status).label;
}

function shipmentStage(shipment?:any):number {
    if(!shipment) return 0;
    if(shipment.delivered_at||shipment.status==='remis_client') return 4;
    if(shipment.arrived_madagascar_at||shipment.status==='arrive_madagascar') return 3;
    if(shipment.china_departure_at||['expedie','en_transit'].includes(shipment.status)) return 2;
    if(shipment.china_warehouse_at||shipment.supplier_sent_at||shipment.status==='arrive_en_chine') return 1;
    return 0;
}

function ResultFact({icon,label,value,accent=false}:{icon:ReactNode;label:string;value:any;accent?:boolean}) {
    return <div className="tracking-result-fact rounded-md border px-3.5 py-3"><dt className="flex items-center gap-2 text-[9px] font-medium uppercase tracking-wide"><span className="tracking-result-fact-icon grid size-6 place-items-center rounded-md">{icon}</span>{label}</dt><dd className={`mt-1.5 text-[13px] font-extrabold ${accent?'accent':''}`}>{value||'—'}</dd></div>;
}
