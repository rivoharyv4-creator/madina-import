import CustomerShell from '@/Components/CustomerShell';
import { PublicConfig } from '@/Layouts/PublicLayout';
import { money, useCart } from '@/lib/cart';
import { Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { ArrowRight, ChevronRight, ImageOff, Info, ShoppingCart, UserRound } from 'lucide-react';
export default function Checkout({ customer, publicConfig }: { customer:{name:string;email:string;phone?:string|null};publicConfig:PublicConfig }) {
    const items=useCart(); const [preview,setPreview]=useState<any>(null);const [error,setError]=useState('');
    const form=useForm({checkout_key:crypto.randomUUID(),phone:customer.phone||'',address:'',items:items.map(i=>({id:i.id,quantity:i.quantity}))});
    useEffect(()=>{form.setData('items',items.map(i=>({id:i.id,quantity:i.quantity})));setPreview(null);setError('');if(!items.length)return;const controller=new AbortController();axios.post('/commande/apercu',{items:items.map(i=>({id:i.id,quantity:i.quantity}))},{signal:controller.signal}).then(r=>setPreview(r.data)).catch(e=>{if(!axios.isCancel(e))setError(Object.values(e.response?.data?.errors||{}).flat().join(' ')||'Impossible de vérifier le panier. Réessayez.');});return()=>controller.abort();},[items]);
    const header = <div className="customer-order mb-8">
        <nav aria-label="Fil d’Ariane" className="order-muted mb-6 flex flex-wrap items-center gap-2 text-sm">
            <Link href="/">Accueil</Link><ChevronRight size={14} aria-hidden="true"/><Link href="/panier">Panier</Link><ChevronRight size={14} aria-hidden="true"/><span aria-current="page">Finaliser votre commande</span>
        </nav>
        <h1 className="text-3xl font-extrabold">Finaliser votre commande</h1>
        <p className="order-muted mt-3">Vérifiez vos informations et créez votre commande. Le paiement s’effectue en dehors du site.</p>
    </div>;

    return <CustomerShell title="Finaliser votre commande" publicConfig={publicConfig} header={header}>
        {!items.length ? <div className="commerce-panel"><p>Votre panier est vide.</p><Link href="/catalogue" className="public-button mt-5">Revenir au catalogue<ArrowRight size={16} aria-hidden="true"/></Link></div> :
            <form className="customer-order grid items-start gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]" onSubmit={e => { e.preventDefault(); if (preview && !error && !form.processing) form.post('/commande'); }}>
                <section className="commerce-panel h-full" aria-labelledby="checkout-info-title">
                    <div className="mb-7 flex items-start gap-4">
                        <span className="checkout-emblem grid size-12 shrink-0 place-items-center rounded-full"><UserRound size={24} aria-hidden="true"/></span>
                        <div><h2 id="checkout-info-title" className="text-xl font-bold">Vos informations</h2><p className="order-muted mt-1 text-sm leading-6">Ces informations nous permettront de vous contacter concernant votre commande.</p></div>
                    </div>
                    <div className="order-tint mb-6 rounded-lg p-4"><p className="break-words font-bold">{customer.name}</p><p className="order-muted mt-1 break-all">{customer.email}</p></div>
                    <div className="order-divider space-y-7 border-t pt-6">
                        <div><label htmlFor="checkout-phone" className="block font-medium">Téléphone <span className="checkout-required" aria-hidden="true">*</span></label>
                            <input id="checkout-phone" required type="tel" autoComplete="tel" maxLength={30} className="mt-2 block h-12 w-full" value={form.data.phone} onChange={e=>form.setData('phone',e.target.value)} aria-describedby="checkout-phone-help" aria-invalid={Boolean(form.errors.phone)}/>
                            <p id="checkout-phone-help" className="order-muted mt-2 text-sm">Nous vous contacterons si besoin au sujet de votre commande.</p>
                        </div>
                        <div><label htmlFor="checkout-address" className="block font-medium">Adresse de livraison <span className="checkout-required" aria-hidden="true">*</span></label>
                            <textarea id="checkout-address" required autoComplete="street-address" rows={4} maxLength={255} className="mt-2 block w-full" value={form.data.address} onChange={e=>form.setData('address',e.target.value)} aria-describedby="checkout-address-help" aria-invalid={Boolean(form.errors.address)}/>
                            <p id="checkout-address-help" className="order-muted mt-2 text-sm">Indiquez l’adresse complète où vous souhaitez être livré.</p>
                        </div>
                    </div>
                </section>
                <section className="commerce-panel min-w-0" aria-labelledby="checkout-summary-title">
                    <div className="mb-6 flex items-start gap-4">
                        <span className="checkout-emblem grid size-12 shrink-0 place-items-center rounded-full"><ShoppingCart size={24} aria-hidden="true"/></span>
                        <div><h2 id="checkout-summary-title" className="text-xl font-bold">Récapitulatif de la commande</h2><p className="order-muted mt-1 text-sm leading-6">Vérifiez les articles et le total avant de créer votre commande.</p></div>
                    </div>
                    {error ? <p role="alert" className="checkout-required">{error}</p> : !preview ? <p role="status" className="order-muted py-5">Vérification des prix et disponibilités…</p> : <>
                        <ul>{preview.items.map((item:any) => {
                            const image = items.find(cartItem => cartItem.id === item.id)?.image_url;
                            return <li key={item.id} className="order-divider flex items-center gap-4 border-b py-5">
                                <span className="order-tint grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg">{image ? <img src={image} alt="" className="size-full object-contain p-1"/> : <ImageOff size={24} className="order-muted" aria-hidden="true"/>}</span>
                                <div className="min-w-0 flex-1"><p className="break-words font-bold">{item.name}</p><p className="order-muted mt-1 text-sm">{item.quantity} × {money(item.unit_price)}</p></div>
                                <strong className="shrink-0 text-sm">{money(item.subtotal)}</strong>
                            </li>;
                        })}</ul>
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 text-xl font-bold"><span>Total</span><strong className="checkout-required" aria-live="polite">{money(preview.total)} (MGA)</strong></div>
                    </>}
                    <div className="order-tint mt-7 flex items-start gap-3 rounded-lg p-5">
                        <Info size={24} className="order-muted mt-0.5 shrink-0" aria-hidden="true"/>
                        <div><h3 className="font-bold">Paiement et validation</h3><p className="order-muted mt-1 text-sm leading-6">Après avoir créé votre commande, le paiement s’effectue en dehors du site. Votre paiement sera ensuite vérifié manuellement par notre équipe avant la confirmation de votre commande.</p><p className="order-muted mt-2 text-sm leading-6">Vous recevrez un message une fois la vérification effectuée.</p></div>
                    </div>
                    <button type="submit" disabled={form.processing || !preview || Boolean(error)} className="public-button mt-6 flex min-h-12 w-full justify-center gap-3">{form.processing ? 'Création en cours…' : 'Créer la commande'}<ArrowRight size={18} aria-hidden="true"/></button>
                </section>
            </form>}
    </CustomerShell>;
}
