import { useForm } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import { FormEvent } from 'react';

export default function PublicContactForm({compact=false,flash}:{compact?:boolean;flash?:string}){
 const {data,setData,post,processing,errors,reset}=useForm({name:'',contact:'',client_type:'entrepreneur',need:'',message:'',consent:false,website:''});
 const submit=(event:FormEvent)=>{event.preventDefault();post('/contact',{preserveScroll:true,onSuccess:()=>reset()});};
 return <form onSubmit={submit} className={`grid gap-4 ${compact?'':'md:grid-cols-2'}`}>
  {flash&&<div className="flex items-center gap-2 rounded-sm bg-emerald-50 p-4 text-sm font-semibold text-emerald-700 md:col-span-2"><CheckCircle2 size={18}/>{flash}</div>}
  <Field label="Nom" error={errors.name}><input className="public-field !rounded-xl !py-4" value={data.name} onChange={e=>setData('name',e.target.value)} required/></Field>
  <Field label="Téléphone ou WhatsApp" error={errors.contact}><input className="public-field !rounded-xl !py-4" value={data.contact} onChange={e=>setData('contact',e.target.value)} required/></Field>
  <Field label="Votre profil" error={errors.client_type}><select className="public-field !rounded-xl !py-4" value={data.client_type} onChange={e=>setData('client_type',e.target.value)}><option value="entrepreneur">Entrepreneur</option><option value="entreprise">Entreprise</option><option value="revendeur">Revendeur</option><option value="hotel">Hôtel</option><option value="particulier">Particulier</option></select></Field>
  <Field label="Votre besoin" error={errors.need}><input className="public-field !rounded-xl !py-4" value={data.need} onChange={e=>setData('need',e.target.value)} placeholder="Produit, machine, sourcing…" required/></Field>
  <div className="hidden" aria-hidden="true"><label>Site web<input tabIndex={-1} autoComplete="off" value={data.website} onChange={e=>setData('website',e.target.value)}/></label></div>
  <div className="md:col-span-2"><Field label="Parlez-nous de votre projet" error={errors.message}><textarea className="public-field min-h-32 !rounded-xl !py-4" value={data.message} onChange={e=>setData('message',e.target.value)} required/></Field></div>
  <label className="flex items-start gap-3 text-xs leading-5 text-[#5E5E5E] md:col-span-2"><input type="checkbox" className="mt-1 rounded border-black/20 text-[#C8102E] focus:ring-[#C8102E]" checked={data.consent} onChange={e=>setData('consent',e.target.checked)} required/><span>J’accepte d’être contacté par Madina Import au sujet de cette demande.</span></label>{errors.consent&&<p className="text-xs text-[#C8102E] md:col-span-2">{errors.consent}</p>}
  <button disabled={processing} className="public-button md:col-span-2 md:w-fit">{processing?'Envoi…':'Envoyer ma demande'}<ArrowRight size={16}/></button>
 </form>;
}

function Field({label,error,children}:{label:string;error?:string;children:React.ReactNode}){return <label className="block"><span className="mb-2 block text-xs font-bold uppercase tracking-wider text-[#5E5E5E]">{label}</span>{children}{error&&<span className="mt-1 block text-xs text-[#C8102E]">{error}</span>}</label>}
