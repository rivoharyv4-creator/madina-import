import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Download, ExternalLink, Eye, Inbox, Pencil, Plus, Search, Star, UsersRound } from 'lucide-react';
import { FormEvent, useState } from 'react';

const moneyKeys=['total','amount','price','value','salary'];
const display=(key:string,value:any)=>{
 if(value===null||value===undefined||value==='') return '—';
 if(key==='active') return value?'Actif':'Inactif';
 if(moneyKeys.some(item=>key.includes(item))) return new Intl.NumberFormat('fr-FR',{maximumFractionDigits:0}).format(Number(value))+' Ar';
 if(key.endsWith('_at')||key==='month'||key==='valid_until') return new Date(value).toLocaleDateString('fr-FR');
 return String(value).replaceAll('_',' ');
};

export default function Index({module,config,rows,query,flash}:{module:string;config:any;rows:any[];query?:string;flash?:string}){
 const [q,setQ]=useState(query||'');
 const submit=(event:FormEvent)=>{event.preventDefault();router.get('/modules/'+module,{q},{preserveState:true});};
 return <AuthenticatedLayout
  header={<><p className="eyebrow">Gestion</p><h1 className="page-title">{config.title}</h1><p className="mt-1 text-sm text-gray-400">{rows.length} enregistrement(s) affiché(s)</p></>}
  action={(config.primary||config.related_action)?<div className="flex flex-wrap items-center gap-2">{config.related_action&&<Link href={config.related_action.href} className="btn-secondary"><UsersRound size={17}/>{config.related_action.label}</Link>}{config.primary&&<Link href={`/modules/${module}/create`} className="btn-primary"><Plus size={17}/>{config.primary}</Link>}</div>:undefined}
 >
  <Head title={config.title}/>
  {flash&&<div className="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"><CheckCircle2 size={18}/>{flash}</div>}
  <section className="panel overflow-hidden !p-0">
   <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4">
    <form onSubmit={submit} className="relative min-w-[240px] flex-1"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"/><input value={q} onChange={event=>setQ(event.target.value)} className="field pl-9" placeholder={`Rechercher dans ${config.title.toLowerCase()}…`}/></form>
    <a href={`/modules/${module}/export`} className="btn-secondary"><Download size={16}/>Exporter CSV</a>
   </div>
   <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left"><thead><tr>{Object.values(config.columns).map((column:any)=><th key={column}>{column}</th>)}{config.editable&&<th className="w-28 text-center">Actions</th>}</tr></thead><tbody>{rows.map((row:any)=><tr key={row.id}>{Object.keys(config.columns).map(key=><td key={key}>{key==='photo_path'?(row[key]?<img src={`/product-photo/${String(row[key]).split('/').pop()}`} alt={row.name||'Produit'} className="size-12 rounded-xl border border-gray-200 object-cover"/>:<span className="text-gray-300">Aucune</span>):key==='proof_path'?(row[key]?<a href={row[key]} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 rounded-lg bg-[#FCF108]/20 px-2.5 py-1.5 text-xs font-semibold text-[#665f00] hover:bg-[#FCF108]/35"><ExternalLink size={13}/>Voir</a>:<span className="text-gray-300">Aucun</span>):key==='quality_rating'?<span className="inline-flex items-center gap-0.5" title={`${row[key]} sur 5`}>{[1,2,3,4,5].map(star=><Star key={star} size={15} className={star<=Number(row[key])?'fill-[#FCF108] text-[#D5C900]':'fill-gray-50 text-gray-200'}/>)}</span>:<span className={key==='status'||key==='active'?'status':''}>{display(key,row[key])}</span>}</td>)}{config.editable&&<td><div className="flex justify-center gap-2">{module==='commandes'&&<Link href={`/modules/commandes/${row.id}`} title="Voir l’aperçu" aria-label="Voir l’aperçu" className="grid size-9 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-[#FCF108] hover:bg-yellow-50 hover:text-[#817900]"><Eye size={16}/></Link>}<Link href={`/modules/${module}/${row.id}/edit`} title="Modifier" aria-label="Modifier" className="grid size-9 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-[#BD2433]/30 hover:bg-red-50 hover:text-[#BD2433]"><Pencil size={15}/></Link></div></td>}</tr>)}</tbody></table>
    {!rows.length&&<div className="grid place-items-center px-6 py-20 text-center"><span className="grid size-14 place-items-center rounded-2xl bg-[#F8F8F6] text-gray-400"><Inbox size={24}/></span><h3 className="mt-4 font-semibold">Aucun enregistrement</h3><p className="mt-1 max-w-sm text-sm text-gray-400">Les nouvelles opérations apparaîtront ici après leur enregistrement.</p></div>}
   </div>
  </section>
 </AuthenticatedLayout>;
}
