import CustomerShell from '@/Components/CustomerShell';
import { PublicConfig } from '@/Layouts/PublicLayout';
import { money, useCart, writeCart } from '@/lib/cart';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ImageOff, Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react';

export default function Cart({ publicConfig }: { publicConfig: PublicConfig }) {
    const items = useCart();
    const count = items.reduce((sum, item) => sum + item.quantity, 0);
    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const updateQuantity = (id: number, quantity: number) => {
        if (Number.isInteger(quantity) && quantity >= 1 && quantity <= 10000) {
            writeCart(items.map(item => item.id === id ? { ...item, quantity } : item));
        }
    };

    return <CustomerShell title="Votre panier" publicConfig={publicConfig}>
        <div className="premium-cart">
            {!items.length ? <div className="commerce-panel cart-empty text-center">
                <span className="cart-empty-icon mx-auto mb-5 grid size-16 place-items-center rounded-full"><ShoppingBag size={28} aria-hidden="true"/></span>
                <h2 className="text-xl font-bold">Votre panier est vide</h2>
                <p className="cart-muted mx-auto my-5 max-w-md leading-7">Découvrez notre sélection de produits pour préparer votre commande.</p>
                <Link href="/catalogue" className="public-button">Découvrir le catalogue <ArrowRight size={16} aria-hidden="true"/></Link>
            </div> : <>
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px] xl:gap-8">
                    <div className="min-w-0">
                        <div className="commerce-panel cart-products">
                            <div className="cart-table-heading cart-row-grid text-sm font-bold" aria-hidden="true">
                                <span>Produit</span><span className="text-center">Quantité</span><span className="text-right">Total</span><span className="text-center">Action</span>
                            </div>
                            <ul>
                                {items.map(item => <li key={item.id} className="cart-product-row cart-row-grid">
                                    <div className="cart-product-info flex min-w-0 items-center gap-4">
                                        <div className="cart-product-image grid size-20 shrink-0 place-items-center overflow-hidden rounded-xl">
                                            {item.image_url ? <img src={item.image_url} alt="" loading="lazy" className="size-full object-contain p-2"/> : <ImageOff size={24} className="cart-muted" aria-hidden="true"/>}
                                        </div>
                                        <div className="min-w-0"><h2 className="break-words font-bold leading-6">{item.name}</h2><p className="cart-muted mt-1 text-sm">{money(item.price)} / unité</p></div>
                                    </div>
                                    <div className="cart-quantity-cell">
                                        <span className="cart-muted mb-2 block text-xs sm:hidden">Quantité</span>
                                        <div className="cart-quantity flex h-10 items-center rounded-full border">
                                            <button type="button" disabled={item.quantity <= 1} onClick={() => updateQuantity(item.id, item.quantity - 1)} aria-label={`Diminuer la quantité de ${item.name}`} className="grid size-9 shrink-0 place-items-center rounded-full"><Minus size={14} aria-hidden="true"/></button>
                                            <input type="number" min="1" max="10000" aria-label={`Quantité pour ${item.name}`} value={item.quantity} onChange={e => updateQuantity(item.id, Number(e.target.value))} className="cart-quantity-input min-w-0 flex-1 text-center text-sm font-bold"/>
                                            <button type="button" disabled={item.quantity >= 10000} onClick={() => updateQuantity(item.id, item.quantity + 1)} aria-label={`Augmenter la quantité de ${item.name}`} className="grid size-9 shrink-0 place-items-center rounded-full"><Plus size={14} aria-hidden="true"/></button>
                                        </div>
                                    </div>
                                    <strong className="cart-line-total text-right" aria-label={`Total pour ${item.name}`}><span className="cart-muted mb-2 block text-xs font-normal sm:hidden">Total</span>{money(item.price * item.quantity)}</strong>
                                    <button type="button" onClick={() => writeCart(items.filter(row => row.id !== item.id))} aria-label={`Supprimer ${item.name}`} title="Supprimer le produit" className="cart-remove grid size-10 place-items-center rounded-full"><Trash2 size={17} aria-hidden="true"/></button>
                                </li>)}
                            </ul>
                        </div>
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-4 text-sm">
                            <Link href="/catalogue" className="cart-back flex items-center gap-2 font-bold"><ArrowLeft size={16} aria-hidden="true"/>Continuer mes achats</Link>
                            <button type="button" onClick={() => { if (window.confirm('Vider votre panier ?')) writeCart([]); }} className="cart-muted flex items-center gap-2 underline underline-offset-4"><Trash2 size={14} aria-hidden="true"/>Vider le panier</button>
                        </div>
                    </div>
                    <aside className="commerce-panel cart-summary lg:sticky lg:top-24" aria-labelledby="cart-summary-title">
                        <h2 id="cart-summary-title" className="font-bold">Résumé de la commande</h2>
                        <p className="cart-muted mt-2 text-sm">{count} {count > 1 ? 'articles' : 'article'} dans votre panier</p>
                        <dl className="my-6 space-y-4 text-sm">
                            <div className="flex justify-between gap-4"><dt className="cart-muted">Sous-total</dt><dd className="text-right font-bold">{money(subtotal)}</dd></div>
                            <div className="flex justify-between gap-4"><dt className="cart-muted">Livraison</dt><dd className="text-right">À préciser à la commande</dd></div>
                        </dl>
                        <div className="cart-summary-total flex flex-wrap items-center justify-between gap-3 border-t pt-5"><span className="text-sm font-bold">Total indicatif</span><strong className="text-xl font-bold" aria-live="polite" aria-atomic="true">{money(subtotal)}</strong></div>
                        <Link href="/commande" className="public-button mt-6 flex min-h-12 w-full justify-center gap-2">Finaliser la commande <ArrowRight size={16} aria-hidden="true"/></Link>
                    </aside>
                </div>
            </>}
        </div>
    </CustomerShell>;
}
