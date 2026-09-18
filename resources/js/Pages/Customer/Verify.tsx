import CustomerShell from '@/Components/CustomerShell';
import { PublicConfig } from '@/Layouts/PublicLayout';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
export default function Verify({ maskedEmail, retryAfter, publicConfig }: { maskedEmail:string; retryAfter:number; publicConfig:PublicConfig }) {
    const form=useForm({code:''}); const resend=useForm({}); const [seconds,setSeconds]=useState(retryAfter);
    useEffect(()=>setSeconds(retryAfter),[retryAfter]);
    useEffect(()=>{const timer=setInterval(()=>setSeconds(s=>Math.max(0,s-1)),1000);return()=>clearInterval(timer);},[]);
    return <CustomerShell title="Confirmez votre adresse e-mail" publicConfig={publicConfig}><div className="commerce-panel mx-auto max-w-lg"><p>Nous avons envoyé un code à six chiffres à votre adresse e-mail.</p><p className="my-4">{maskedEmail}</p><form onSubmit={e=>{e.preventDefault();form.post('/verify-email');}}><label className="block">Code de confirmation<input autoComplete="one-time-code" inputMode="numeric" maxLength={20} required className="my-4 block w-full text-center text-3xl tracking-widest" value={form.data.code} onChange={e=>form.setData('code',e.target.value)}/></label><button disabled={form.processing} className="public-button">{form.processing?'Vérification…':'Confirmer mon adresse'}</button></form><button className="mt-6 underline" disabled={seconds>0||resend.processing} onClick={()=>resend.post('/email/verification-notification',{onSuccess:()=>setSeconds(60)})}>Renvoyer le code{seconds>0?` (${seconds} s)`:''}</button></div></CustomerShell>;
}
