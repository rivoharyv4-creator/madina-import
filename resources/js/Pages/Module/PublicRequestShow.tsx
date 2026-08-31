import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BriefcaseBusiness, CalendarDays, CheckCircle2, Mail, MessageSquareText, Phone, UserRound } from 'lucide-react';

const labels:Record<string,string>={revendeur:'Revendeur',entrepreneur:'Entrepreneur',particulier:'Particulier',hotel:'Hôtel',entreprise:'Entreprise'};
const status=(value:string)=>String(value||'nouvelle').replaceAll('_',' ');

export default function PublicRequestShow({request}:{request:any}){
 const receivedAt=request.created_at?new Date(request.created_at).toLocaleString('fr-FR',{dateStyle:'long',timeStyle:'short'}):'—';
 return <AuthenticatedLayout header={<><Link href="/modules/demandes" className="mb-3 inline-flex items-center gap-2 text-xs font-semibold text-gray-400 hover:text-[#BD2433]"><ArrowLeft size={14}/>Retour aux demandes publiques</Link><p className="eyebrow">Demande publique</p><h1 className="page-title">{request.name}</h1><p className="mt-1 text-sm text-gray-400">Détails du formulaire envoyé depuis le site public.</p></>}>
  <Head title={`Demande de ${request.name}`}/>
  <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
   <section className="panel">
    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-5"><div><p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Besoin exprimé</p><h2 className="mt-2 text-xl font-black">{request.need}</h2></div><span className="status capitalize">{status(request.status)}</span></div>
    <div className="mt-6"><div className="mb-3 flex items-center gap-2 text-[#BD2433]"><MessageSquareText size={18}/><h3 className="text-sm font-bold text-[#2F2F2F]">Message du demandeur</h3></div><p className="whitespace-pre-wrap rounded-xl bg-[#F8F8F6] p-5 text-sm leading-7 text-gray-700">{request.message}</p></div>
   </section>
   <aside className="panel space-y-5">
    <Detail icon={UserRound} label="Nom" value={request.name}/>
    <Detail icon={Phone} label="Contact" value={request.contact}/>
    <Detail icon={BriefcaseBusiness} label="Profil" value={labels[request.client_type]||request.client_type}/>
    <Detail icon={CalendarDays} label="Reçue le" value={receivedAt}/>
    <Detail icon={CheckCircle2} label="Consentement" value={request.consent?'Accepté':'Non renseigné'}/>
    {String(request.contact).includes('@')&&<a href={`mailto:${request.contact}`} className="btn-primary w-full justify-center"><Mail size={16}/>Répondre par e-mail</a>}
   </aside>
  </div>
 </AuthenticatedLayout>;
}

function Detail({icon:Icon,label,value}:{icon:any;label:string;value:any}){
 return <div className="flex items-start gap-3"><span className="grid size-9 shrink-0 place-items-center rounded-lg bg-[#FCF108]/20 text-[#817900]"><Icon size={16}/></span><div className="min-w-0"><span className="block text-[10px] font-bold uppercase tracking-wider text-gray-400">{label}</span><strong className="mt-1 block break-words text-sm text-gray-700">{value||'—'}</strong></div></div>;
}
