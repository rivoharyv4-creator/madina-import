import { CalendarRange, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Props={value?:string|null;onChange:(value:string)=>void;required?:boolean};
const months=Array.from({length:12},(_,month)=>({
 short:new Intl.DateTimeFormat('fr-FR',{month:'short'}).format(new Date(2026,month,1)).replace('.',''),
 long:new Intl.DateTimeFormat('fr-FR',{month:'long'}).format(new Date(2026,month,1)),
}));
const pad=(value:number)=>String(value).padStart(2,'0');
const parse=(value?:string|null)=>{if(!value)return null;const [year,month]=value.slice(0,7).split('-').map(Number);return year&&month>=1&&month<=12?{year,month:month-1}:null;};

export default function PremiumMonthPicker({value,onChange,required=false}:Props){
 const selected=parse(value),today=new Date();
 const [open,setOpen]=useState(false);
 const [year,setYear]=useState(()=>selected?.year??today.getFullYear());
 const root=useRef<HTMLDivElement>(null);
 useEffect(()=>{if(selected)setYear(selected.year);},[value]);
 useEffect(()=>{const close=(event:MouseEvent)=>{if(root.current&&!root.current.contains(event.target as Node))setOpen(false);};document.addEventListener('mousedown',close);return()=>document.removeEventListener('mousedown',close);},[]);
 const label=selected?`${months[selected.month].long} ${selected.year}`:'Sélectionner un mois';
 const choose=(month:number)=>{onChange(`${year}-${pad(month+1)}`);setOpen(false);};
 const chooseCurrent=()=>{setYear(today.getFullYear());onChange(`${today.getFullYear()}-${pad(today.getMonth()+1)}`);setOpen(false);};

 return <div ref={root} className="relative">
  <button type="button" onClick={()=>setOpen(current=>!current)} aria-haspopup="dialog" aria-expanded={open} aria-required={required} className={`field flex min-h-[45px] w-full items-center gap-3 text-left transition ${open?'border-[#BD2433] ring-4 ring-[#BD2433]/10':''}`}>
   <span className={`grid size-8 shrink-0 place-items-center rounded-lg ${selected?'bg-[#FCF108]/30 text-[#817900]':'bg-gray-100 text-gray-400'}`}><CalendarRange size={16}/></span>
   <span className={`flex-1 text-sm capitalize ${selected?'font-semibold text-gray-800':'text-gray-400'}`}>{label}</span>
   {selected&&<span role="button" tabIndex={0} aria-label="Effacer le mois" onClick={event=>{event.stopPropagation();onChange('');}} onKeyDown={event=>{if(event.key==='Enter'){event.stopPropagation();onChange('');}}} className="grid size-7 place-items-center rounded-lg text-gray-300 hover:bg-gray-100 hover:text-gray-600"><X size={14}/></span>}
  </button>
  {open&&<div role="dialog" aria-label="Choisir un mois" className="absolute left-0 top-[calc(100%+10px)] z-50 w-[320px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_24px_70px_rgba(47,47,47,.2)]">
   <div className="relative overflow-hidden bg-[#2F2F2F] px-5 pb-5 pt-4 text-white"><span className="absolute -right-8 -top-10 size-28 rounded-full bg-[#FCF108]/10"/><p className="text-[10px] font-bold uppercase tracking-[.2em] text-[#FCF108]">Période de salaire</p><p className="mt-2 text-lg font-bold capitalize">{selected?label:'Choisissez un mois'}</p></div>
   <div className="p-4">
    <div className="mb-4 flex items-center justify-between"><button type="button" onClick={()=>setYear(current=>current-1)} aria-label="Année précédente" className="grid size-9 place-items-center rounded-xl border border-gray-200 text-gray-500 transition hover:border-[#FCF108] hover:bg-[#FCF108]/15 hover:text-gray-900"><ChevronLeft size={17}/></button><strong className="text-base text-gray-800">{year}</strong><button type="button" onClick={()=>setYear(current=>current+1)} aria-label="Année suivante" className="grid size-9 place-items-center rounded-xl border border-gray-200 text-gray-500 transition hover:border-[#FCF108] hover:bg-[#FCF108]/15 hover:text-gray-900"><ChevronRight size={17}/></button></div>
    <div className="grid grid-cols-3 gap-2">{months.map((month,index)=>{const active=selected?.year===year&&selected.month===index,isCurrent=today.getFullYear()===year&&today.getMonth()===index;return <button type="button" key={month.long} onClick={()=>choose(index)} className={`relative rounded-xl px-2 py-3 text-xs font-semibold capitalize transition ${active?'bg-[#BD2433] text-white shadow-md shadow-[#BD2433]/25':'text-gray-700 hover:bg-[#FCF108]/25'} ${isCurrent&&!active?'ring-1 ring-inset ring-[#BD2433]/40 text-[#BD2433]':''}`}>{month.short}{isCurrent&&<i className={`absolute bottom-1 left-1/2 size-1 -translate-x-1/2 rounded-full ${active?'bg-[#FCF108]':'bg-[#BD2433]'}`}/>}</button>})}</div>
    <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3"><button type="button" onClick={()=>{onChange('');setOpen(false);}} className="text-xs font-semibold text-gray-400 hover:text-[#BD2433]">Effacer</button><button type="button" onClick={chooseCurrent} className="rounded-xl bg-[#FCF108] px-4 py-2 text-xs font-bold text-[#2F2F2F] transition hover:bg-[#f4e900]">Mois actuel</button></div>
   </div>
  </div>}
 </div>;
}
