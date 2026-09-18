import AddToCart from './AddToCart';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, ImageOff } from 'lucide-react';

export type CatalogProduct = { id?: number; slug: string; reference: string; name: string; category?: string | null; short_description?: string | null; availability: string; price?: number | null; image_url?: string | null };
const money = (value: number) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value) + ' Ar';

export default function PublicProductCard({ product }: { product: CatalogProduct }) {
    const availabilityStyle = product.availability === 'Disponible de suite' ? 'bg-white text-emerald-700' : product.availability === 'Sur commande' ? 'bg-[#FFE600] text-black' : 'bg-white text-[#C8102E]';
    const href = `/catalogue/${product.slug}`;
    return <article className="group overflow-hidden border border-black/10 bg-white transition duration-300 hover:-translate-y-1 hover:border-[#FFE600] hover:shadow-[0_18px_50px_rgba(23,23,23,.09)]">
        <Link href={href} className="block">
            <div className="relative grid aspect-[4/3] place-items-center overflow-hidden bg-[#F6F5F1]">
                {product.image_url ? <img src={product.image_url} alt={product.name} loading="lazy" className="size-full object-contain p-5 transition duration-500 group-hover:scale-[1.035]"/> : <ImageOff className="text-black/20" size={32}/>}
                <span className={`absolute left-4 top-4 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider ${availabilityStyle}`}>{product.availability}</span>
            </div>
            <div className="px-5 pt-5">
                <div className="flex items-center justify-between gap-3 text-[10px] font-bold uppercase tracking-[.16em] text-[#5E5E5E]"><span>{product.category || 'Sélection Madina'}</span><span>{product.reference}</span></div>
                <h3 className="mt-3 text-lg font-extrabold tracking-tight">{product.name}</h3>
                {product.short_description && <p className="mt-2 line-clamp-2 text-sm leading-6 text-[#5E5E5E]">{product.short_description}</p>}
            </div>
        </Link>
        <div className="mt-5 flex items-end justify-between gap-3 px-5 pb-5">
            <strong className="text-sm">{product.price !== null && product.price !== undefined ? money(product.price) : 'Prix sur demande'}</strong>
            <div className="flex items-center gap-2">
                <AddToCart product={product} iconOnly/>
                <Link href={href} aria-label={`Voir ${product.name}`} title="Voir le produit" className="grid size-9 place-items-center rounded-full bg-[#171717] text-white transition hover:bg-[#C8102E] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C8102E]"><ArrowUpRight size={16} aria-hidden="true"/></Link>
            </div>
        </div>
    </article>;
}
