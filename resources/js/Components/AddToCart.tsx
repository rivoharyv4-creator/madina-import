import { addCart } from '@/lib/cart';
import { CatalogProduct } from './PublicProductCard';
import { useEffect, useState } from 'react';
import { Check, ShoppingCart } from 'lucide-react';
export default function AddToCart({ product, iconOnly = false }: { product: CatalogProduct; iconOnly?: boolean }) {
    const [message, setMessage] = useState('');
    const [added, setAdded] = useState(false);
    const [animation, setAnimation] = useState(0);
    useEffect(() => {
        if (!animation) return;
        const timer = window.setTimeout(() => setAdded(false), 1200);
        return () => window.clearTimeout(timer);
    }, [animation]);
    if (!product.id || !product.price || product.availability === 'Rupture') return null;
    return <div className={iconOnly ? 'shrink-0' : 'mt-4'}><button type="button" aria-label={`Ajouter ${product.name} au panier`} title={added ? 'Produit ajouté au panier' : 'Ajouter au panier'} className={iconOnly ? 'grid size-9 place-items-center rounded-full bg-[#C8102E] text-white transition hover:bg-[#a50d26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C8102E]' : 'public-button'} onClick={() => { try { addCart({ id: product.id!, name: product.name, price: product.price!, image_url: product.image_url }); setMessage('Produit ajouté au panier.'); setAdded(true); setAnimation(value => value + 1); } catch { setAdded(false); setMessage('Le stockage du navigateur est indisponible.'); } }}>{iconOnly ? <span key={animation} className={added ? 'cart-add-feedback' : 'inline-flex'}>{added ? <Check size={16} aria-hidden="true"/> : <ShoppingCart size={16} aria-hidden="true"/>}</span> : 'Ajouter au panier'}</button><p className={iconOnly ? 'sr-only' : 'mt-2 text-sm'} role="status">{message}</p></div>;
}
