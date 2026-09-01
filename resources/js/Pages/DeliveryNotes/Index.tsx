import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FileDown, Pencil, Plus, Search, Truck } from 'lucide-react';
import { FormEvent, useState } from 'react';

const labels:Record<string,string>={a_livrer:'À livrer',livraison_en_cours:'Livraison en cours',livree:'Livrée',livraison_partielle:'Livraison partielle',annulee:'Annulée'};

export default function Index({notes,query}:{notes:any;query?:string}){
 const [search,setSearch]=useState(query||'');
 const submit=(event:FormEvent)=>{event.preventDefault();router.get('/modules/bons-livraison',search?{q:search}:{},{preserveState:true});};
 return <AuthenticatedLayout header={<><p className="eyebrow">Livraison</p><h1 className="page-title">Bons de livraison</h1><p className="mt-1 text-sm text-gray-400">{notes.total} bon(s) de livraison</p></>} action={<Link href="/modules/bons-livraison/create" className="btn-primary"><Plus size={17}/>Nouveau BL</Link>}>
  <Head title="Bons de livraison"/>
  <section className="panel overflow-hidden !p-0">
   <form onSubmit={submit} className="relative border-b border-gray-100 p-4"><Search size={16} className="absolute left-7 top-1/2 -translate-y-1/2 text-gray-400"/><input className="field pl-9" value={search} onChange={event=>setSearch(event.target.value)} placeholder="Rechercher un BL, une commande ou un client…"/></form>
   <div className="overflow-x-auto"><table className="w-full min-w-[820px] text-left"><thead><tr><th>N° du BL</th><th>Commande</th><th>Date</th><th>Client</th><th>Colis</th><th>Statut</th><th className="text-center">Actions</th></tr></thead><tbody>{notes.data.map((note:any)=><tr key={note.id}><td className="font-bold">{note.number}</td><td>{note.order_number}</td><td>{new Date(`${note.delivered_at}T00:00:00`).toLocaleDateString('fr-FR')}</td><td>{note.client_name}</td><td>{note.package_count}</td><td><span className="status">{labels[note.status]||note.status}</span></td><td><div className="flex justify-center gap-2"><a href={`/modules/bons-livraison/${note.id}/pdf`} className="grid size-9 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-yellow-50" title="Télécharger le PDF"><FileDown size={16}/></a><Link href={`/modules/bons-livraison/${note.id}/edit`} className="grid size-9 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-red-50" title="Modifier"><Pencil size={15}/></Link></div></td></tr>)}</tbody></table>{!notes.data.length&&<div className="grid place-items-center px-6 py-20 text-center text-gray-400"><Truck size={30}/><p className="mt-3 text-sm">Aucun bon de livraison.</p></div>}</div>
   {notes.last_page>1&&<div className="flex justify-end gap-2 border-t border-gray-100 p-4"><button className="btn-secondary" disabled={notes.current_page===1} onClick={()=>router.get(notes.prev_page_url)}>Précédent</button><button className="btn-secondary" disabled={notes.current_page===notes.last_page} onClick={()=>router.get(notes.next_page_url)}>Suivant</button></div>}
  </section>
 </AuthenticatedLayout>;
}
