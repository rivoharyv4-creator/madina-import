import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

type Props={value?:string|null;onChange:(value:string)=>void;required?:boolean};
const weekdays=['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
const monthTitle=new Intl.DateTimeFormat('fr-FR',{month:'long',year:'numeric'});
const fullDate=new Intl.DateTimeFormat('fr-FR',{day:'2-digit',month:'long',year:'numeric'});
const pad=(value:number)=>String(value).padStart(2,'0');
const serialize=(date:Date)=>`${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}`;
const parse=(value?:string|null)=>{if(!value)return null;const [year,month,day]=value.slice(0,10).split('-').map(Number);return year&&month&&day?new Date(year,month-1,day):null;};
const sameDay=(left:Date|null,right:Date|null)=>!!left&&!!right&&left.getFullYear()===right.getFullYear()&&left.getMonth()===right.getMonth()&&left.getDate()===right.getDate();

export default function PremiumDatePicker({value,onChange,required=false}:Props){
 const selected=parse(value),today=new Date();
 const [open,setOpen]=useState(false);
 const [cursor,setCursor]=useState(()=>selected||today);
 const root=useRef<HTMLDivElement>(null);
 useEffect(()=>{if(selected)setCursor(selected);},[value]);
 useEffect(()=>{const close=(event:MouseEvent)=>{if(root.current&&!root.current.contains(event.target as Node))setOpen(false);};document.addEventListener('mousedown',close);return()=>document.removeEventListener('mousedown',close);},[]);
 const days=useMemo(()=>{const first=new Date(cursor.getFullYear(),cursor.getMonth(),1),offset=(first.getDay()+6)%7,start=new Date(cursor.getFullYear(),cursor.getMonth(),1-offset);return Array.from({length:42},(_,index)=>new Date(start.getFullYear(),start.getMonth(),start.getDate()+index));},[cursor]);
 const move=(amount:number)=>setCursor(new Date(cursor.getFullYear(),cursor.getMonth()+amount,1));
 const choose=(date:Date)=>{onChange(serialize(date));setCursor(date);setOpen(false);};

 return <div ref={root} className="relative">
  <button type="button" onClick={()=>setOpen(current=>!current)} aria-haspopup="dialog" aria-expanded={open} aria-required={required} className={`field flex min-h-[45px] w-full items-center gap-3 text-left transition ${open?'border-[#BD2433] ring-4 ring-[#BD2433]/10':''}`}>
   <span className={`grid size-8 shrink-0 place-items-center rounded-lg ${selected?'bg-[#FCF108]/30 text-[#817900]':'bg-gray-100 text-gray-400'}`}><CalendarDays size={16}/></span>
   <span className={`flex-1 text-sm ${selected?'font-semibold text-gray-800':'text-gray-400'}`}>{selected?fullDate.format(selected):'Sélectionner une date'}</span>
   {selected&&<span role="button" tabIndex={0} aria-label="Effacer la date" onClick={event=>{event.stopPropagation();onChange('');}} onKeyDown={event=>{if(event.key==='Enter'){event.stopPropagation();onChange('');}}} className="grid size-7 place-items-center rounded-lg text-gray-300 hover:bg-gray-100 hover:text-gray-600"><X size={14}/></span>}
  </button>
  {open&&<div role="dialog" aria-label="Choisir une date" className="absolute left-0 top-[calc(100%+10px)] z-50 w-[320px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_24px_70px_rgba(47,47,47,.2)]">
   <div className="relative overflow-hidden bg-[#2F2F2F] px-5 pb-5 pt-4 text-white"><span className="absolute -right-8 -top-10 size-28 rounded-full bg-[#FCF108]/10"/><p className="text-[10px] font-bold uppercase tracking-[.2em] text-[#FCF108]">Sélection de date</p><p className="mt-2 text-lg font-bold capitalize">{selected?fullDate.format(selected):'Choisissez un jour'}</p></div>
   <div className="p-4"><div className="mb-4 flex items-center justify-between"><button type="button" onClick={()=>move(-1)} aria-label="Mois précédent" className="grid size-9 place-items-center rounded-xl border border-gray-200 text-gray-500 transition hover:border-[#FCF108] hover:bg-[#FCF108]/15 hover:text-gray-900"><ChevronLeft size={17}/></button><strong className="text-sm capitalize text-gray-800">{monthTitle.format(cursor)}</strong><button type="button" onClick={()=>move(1)} aria-label="Mois suivant" className="grid size-9 place-items-center rounded-xl border border-gray-200 text-gray-500 transition hover:border-[#FCF108] hover:bg-[#FCF108]/15 hover:text-gray-900"><ChevronRight size={17}/></button></div>
    <div className="grid grid-cols-7 gap-1">{weekdays.map(day=><span key={day} className="pb-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-400">{day}</span>)}{days.map(day=>{const active=sameDay(day,selected),isToday=sameDay(day,today),outside=day.getMonth()!==cursor.getMonth();return <button type="button" key={serialize(day)} onClick={()=>choose(day)} className={`relative grid aspect-square place-items-center rounded-xl text-xs font-semibold transition ${active?'bg-[#BD2433] text-white shadow-md shadow-[#BD2433]/25':outside?'text-gray-300 hover:bg-gray-50':'text-gray-700 hover:bg-[#FCF108]/25'} ${isToday&&!active?'ring-1 ring-inset ring-[#BD2433]/40 text-[#BD2433]':''}`}>{day.getDate()}{isToday&&<i className={`absolute bottom-1 size-1 rounded-full ${active?'bg-[#FCF108]':'bg-[#BD2433]'}`}/>}</button>})}</div>
    <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3"><button type="button" onClick={()=>{onChange('');setOpen(false);}} className="text-xs font-semibold text-gray-400 hover:text-[#BD2433]">Effacer</button><button type="button" onClick={()=>choose(today)} className="rounded-xl bg-[#FCF108] px-4 py-2 text-xs font-bold text-[#2F2F2F] transition hover:bg-[#f4e900]">Aujourd’hui</button></div>
   </div>
  </div>}
 </div>;
}
