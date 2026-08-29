import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Boxes, CalendarDays, Container, Hash, MapPin, Package, Pencil, ReceiptText, Ship, Truck } from 'lucide-react';

const money=(value:any)=>value===null||value===''?'—':new Intl.NumberFormat('de-DE',{maximumFractionDigits:0}).format(Number(value))+' Ar';
const number=(value:any,decimals=3)=>value===null||value===''?'—':new Intl.NumberFormat('fr-FR',{maximumFractionDigits:decimals}).format(Number(value));
const date=(value:any)=>value?new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR'):'—';
const statusLabels:Record<string,string>={commande_lancee:'Commande lancée',en_attente:'En attente de livraison',arrive_en_chine:'Arrivé dépôt Chine',expedie:'Expédié',en_transit:'En transit',arrive_madagascar:'Arrivé Madagascar',remis_client:'Remis au client'};

export default function ShipmentShow({shipment,products}:{shipment:any;products:any[]}){
 const status=statusLabels[shipment.status]||String(shipment.status||'—').replaceAll('_',' ');
 return <AuthenticatedLayout
  header={<><Link href="/modules/logistique" className="mb-3 inline-flex items-center gap-2 text-xs font-semibold text-gray-400 hover:text-[#BD2433]"><ArrowLeft size={14}/>Retour au suivi logistique</Link><p className="eyebrow">Détails du suivi</p><h1 className="page-title">{shipment.order_number}</h1><p className="mt-1 text-sm text-gray-400">Toutes les informations logistiques liées à cette commande.</p></>}
  action={<div className="flex flex-wrap gap-2"><Link href={`/modules/commandes/${shipment.order_id}`} className="btn-secondary"><Package size={16}/>Voir la commande</Link><Link href={`/modules/logistique/${shipment.id}/edit`} className="btn-primary"><Pencil size={16}/>Modifier</Link></div>}
 >
  <Head title={`Suivi ${shipment.order_number}`}/>
  <section className="panel overflow-hidden !p-0">
   <div className="flex flex-wrap items-center justify-between gap-4 bg-[#2F2F2F] px-6 py-5 text-white">
    <div><p className="text-[10px] font-bold uppercase tracking-[.18em] text-white/45">Commande liée</p><Link href={`/modules/commandes/${shipment.order_id}`} className="mt-1 inline-block text-xl font-black hover:text-[#FCF108]">{shipment.order_number}</Link><p className="mt-1 text-xs text-white/55">Client : {shipment.client_name}</p></div>
    <span className="inline-flex whitespace-nowrap rounded-full bg-[#FCF108] px-4 py-2 text-xs font-bold text-[#2F2F2F]">{status}</span>
   </div>
   <div className="grid gap-0 md:grid-cols-2">
    <Section title="1. Liaison" icon={Package}><Detail label="Numéro de commande" value={shipment.order_number}/><Detail label="Client" value={shipment.client_name}/></Section>
    <Section title="2. Statut" icon={MapPin}><Detail label="Statut actuel" value={status}/></Section>
    <Section title="3. Suivi" icon={Truck}><Detail label="Tracking number" value={shipment.tracking}/><Detail label="Transitaire" value={shipment.forwarder}/><Detail label="Référence conteneur" value={shipment.container_reference}/></Section>
    <Section title="4. Dates" icon={CalendarDays}><Detail label="Départ de Chine" value={date(shipment.china_departure_at)}/><Detail label="Arrivée au dépôt Chine" value={date(shipment.china_warehouse_at)}/><Detail label="Arrivage prévu à Madagascar" value={date(shipment.expected_madagascar_at)}/><Detail label="Arrivage réel à Madagascar" value={date(shipment.arrived_madagascar_at)}/></Section>
    <Section title="5. Volume" icon={Boxes}><Detail label="CBM / volume" value={shipment.cbm===null?'—':`${number(shipment.cbm)} CBM`}/><Detail label="Nombre de colis" value={number(shipment.package_count,0)}/><Detail label="Nombre de cartons" value={number(shipment.carton_count,0)}/></Section>
    <Section title="6. Coût" icon={ReceiptText}><Detail label="Frais de fret" value={money(shipment.cost)} prominent/></Section>
   </div>
  </section>
  <section className="panel mt-5"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-xl bg-[#FCF108]/20 text-[#817900]"><Container size={19}/></span><div><h2 className="font-bold">Produits de la commande</h2><p className="text-xs text-gray-400">{products.length} article(s) lié(s) à cette expédition</p></div></div>{products.length?<div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{products.map((product,index)=><article key={index} className="flex items-center gap-3 rounded-xl border border-gray-200 p-3">{product.photo_url?<img src={product.photo_url} alt={product.name} className="size-14 rounded-lg object-cover"/>:<span className="grid size-14 shrink-0 place-items-center rounded-lg bg-gray-100 text-gray-400"><Ship size={18}/></span>}<div className="min-w-0"><strong className="block truncate text-sm">{product.name}</strong><span className="text-xs text-gray-400">Quantité : {number(product.quantity)}</span></div></article>)}</div>:<p className="mt-5 rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-400">Aucun produit lié.</p>}</section>
 </AuthenticatedLayout>;
}

function Section({title,icon:Icon,children}:{title:string;icon:any;children:any}){
 return <div className="border-b border-gray-100 p-6 md:border-r"><div className="mb-5 flex items-center gap-2"><Icon size={17} className="text-[#BD2433]"/><h2 className="text-sm font-bold">{title}</h2></div><dl className="grid gap-4 sm:grid-cols-2">{children}</dl></div>;
}

function Detail({label,value,prominent=false}:{label:string;value:any;prominent?:boolean}){
 return <div><dt className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400"><Hash size={10}/>{label}</dt><dd className={`mt-1.5 ${prominent?'text-xl font-black text-[#BD2433]':'text-sm font-semibold text-gray-700'}`}>{value||'—'}</dd></div>;
}
