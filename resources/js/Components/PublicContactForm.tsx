import { useForm } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

export default function PublicContactForm({compact=false,flash}:{compact?:boolean;flash?:string}){
 const {data,setData,post,processing,progress,errors,reset,setError,clearErrors}=useForm({name:'',contact:'',client_type:'entrepreneur',need:'',message:'',consent:false,website:'',reference_image:null as File|null});
 const imageInput=useRef<HTMLInputElement>(null);
 const [preview,setPreview]=useState<string|null>(null);
 useEffect(()=>{
  if(!data.reference_image){setPreview(null);return;}
  const url=URL.createObjectURL(data.reference_image);setPreview(url);
  return ()=>URL.revokeObjectURL(url);
 },[data.reference_image]);
 const removeImage=()=>{setData('reference_image',null);clearErrors('reference_image');if(imageInput.current)imageInput.current.value='';};
 const submit=(event:FormEvent)=>{event.preventDefault();post('/contact',{preserveScroll:true,forceFormData:true,onSuccess:()=>{reset();if(imageInput.current)imageInput.current.value='';}});};
 return <form onSubmit={submit} className={`grid gap-4 ${compact?'':'md:grid-cols-2'}`}>
  {flash&&<div className="flex items-center gap-2 rounded-sm bg-emerald-50 p-4 text-sm font-semibold text-emerald-700 md:col-span-2"><CheckCircle2 size={18}/>{flash}</div>}
  <Field label="Nom" error={errors.name}><input className="public-field !rounded-xl !py-4" value={data.name} onChange={e=>setData('name',e.target.value)} required/></Field>
  <Field label="Téléphone ou WhatsApp" error={errors.contact}><input className="public-field !rounded-xl !py-4" value={data.contact} onChange={e=>setData('contact',e.target.value)} required/></Field>
  <Field label="Votre profil" error={errors.client_type}><select className="public-field !rounded-xl !py-4" value={data.client_type} onChange={e=>setData('client_type',e.target.value)}><option value="entrepreneur">Entrepreneur</option><option value="entreprise">Entreprise</option><option value="revendeur">Revendeur</option><option value="hotel">Hôtel</option><option value="particulier">Particulier</option></select></Field>
  <Field label="Votre besoin" error={errors.need}><input className="public-field !rounded-xl !py-4" value={data.need} onChange={e=>setData('need',e.target.value)} placeholder="Produit, machine, sourcing…" required/></Field>
  <div className="hidden" aria-hidden="true"><label>Site web<input tabIndex={-1} autoComplete="off" value={data.website} onChange={e=>setData('website',e.target.value)}/></label></div>
  <div className="md:col-span-2"><Field label="Parlez-nous de votre projet" error={errors.message}><textarea className="public-field min-h-32 !rounded-xl !py-4" value={data.message} onChange={e=>setData('message',e.target.value)} required/></Field></div>
  <div className="md:col-span-2">
   <Field label="Image de référence (facultatif)" error={errors.reference_image}>
    <span className="mb-2 block text-sm leading-6 text-[#5E5E5E]">Ajoutez une photo du produit recherché. JPG, PNG ou WebP — 2 Mo maximum.</span>
    <input ref={imageInput} type="file" accept="image/jpeg,image/png,image/webp" disabled={processing} aria-invalid={!!errors.reference_image} className="public-field !rounded-xl text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#C8102E] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white" onChange={event=>{
     const file=event.target.files?.[0];clearErrors('reference_image');
     if(!file){setData('reference_image',null);return;}
     if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>2*1024*1024){
      setData('reference_image',null);event.target.value='';setError('reference_image','Choisissez une image JPG, PNG ou WebP de 2 Mo maximum.');return;
     }
     setData('reference_image',file);
    }}/>
   </Field>
   {preview&&<div className="mt-3 flex flex-wrap items-center gap-4"><img src={preview} alt="Aperçu de votre image de référence" className="h-28 w-36 rounded-lg border border-black/10 object-contain"/><button type="button" disabled={processing} onClick={removeImage} className="public-button-secondary">Retirer l’image</button></div>}
  </div>
  <label className="flex items-start gap-3 text-xs leading-5 text-[#5E5E5E] md:col-span-2"><input type="checkbox" className="mt-1 rounded border-black/20 text-[#C8102E] focus:ring-[#C8102E]" checked={data.consent} onChange={e=>setData('consent',e.target.checked)} required/><span>J’accepte d’être contacté par Madina Import au sujet de cette demande.</span></label>{errors.consent&&<p className="text-xs text-[#C8102E] md:col-span-2">{errors.consent}</p>}
  <button disabled={processing} className="public-button md:col-span-2 md:w-fit">{processing?`Envoi…${progress ? ` ${progress.percentage}%` : ''}`:'Envoyer ma demande'}<ArrowRight size={16}/></button>
 </form>;
}

function Field({label,error,children}:{label:string;error?:string;children:React.ReactNode}){return <label className="block"><span className="mb-2 block text-xs font-bold uppercase tracking-wider text-[#5E5E5E]">{label}</span>{children}{error&&<span className="mt-1 block text-xs text-[#C8102E]">{error}</span>}</label>}
