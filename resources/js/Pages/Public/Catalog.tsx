import PublicProductCard, { CatalogProduct } from '@/Components/PublicProductCard';
import PublicLayout, { PublicConfig } from '@/Layouts/PublicLayout';
import { Head, router } from '@inertiajs/react';
import { Search, SlidersHorizontal } from 'lucide-react';
import { FormEvent, useState } from 'react';

type CatalogProps = {
    products: CatalogProduct[];
    categories: string[];
    filters: { q: string; category: string };
    pagination: { current_page: number; last_page: number };
    publicConfig: PublicConfig;
};

export default function Catalog({ products, categories, filters, pagination, publicConfig }: CatalogProps) {
    const [q, setQ] = useState(filters.q || '');
    const apply = (event?: FormEvent, page?: number) => {
        event?.preventDefault();
        router.get('/catalogue', { q, category: filters.category || undefined, page }, { preserveState: true });
    };
    const category = (value: string) => router.get('/catalogue', { q: q || undefined, category: value || undefined }, { preserveState: true });

    return (
        <PublicLayout config={publicConfig}>
            <Head title="Catalogue">
                <meta name="description" content="Découvrez les produits actuellement publiés et disponibles chez Madina Import à Madagascar." />
            </Head>

            <section className="border-b border-black/10 bg-white py-20 text-[#171717]">
                <div className="public-container">
                    <p className="public-kicker">Stock à Madagascar</p>
                    <h1 className="mt-5 text-5xl font-black tracking-[-.045em] sm:text-6xl">Produits disponibles</h1>
                    <p className="mt-5 max-w-2xl leading-8 text-[#5E5E5E]">Une sélection issue directement de notre stock. Pour un besoin spécifique, notre équipe peut également lancer une recherche en Chine.</p>
                </div>
            </section>

            <section className="public-section">
                <div className="public-container">
                    <div className="flex flex-col gap-4 border-b border-black/10 pb-8 lg:flex-row lg:items-center">
                        <form onSubmit={apply} className="relative flex-1">
                            <label htmlFor="catalog-search" className="sr-only">Rechercher un produit</label>
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-black/35" size={19} />
                            <input id="catalog-search" value={q} onChange={e => setQ(e.target.value)} placeholder="Produit, catégorie ou référence" className="public-field !py-3.5 !pl-12" />
                        </form>
                        <div className="flex flex-wrap items-center gap-2">
                            <SlidersHorizontal size={17} className="mr-1 text-black/40" />
                            <button onClick={() => category('')} className={`catalog-filter ${!filters.category ? 'active' : ''}`}>Tout</button>
                            {categories.map(item => <button key={item} onClick={() => category(item)} className={`catalog-filter ${filters.category === item ? 'active' : ''}`}>{item}</button>)}
                        </div>
                    </div>

                    {products.length ? (
                        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {products.map(product => <PublicProductCard key={product.slug} product={product} />)}
                        </div>
                    ) : (
                        <div className="mt-10 border border-dashed border-black/20 bg-white px-6 py-20 text-center">
                            <h2 className="text-2xl font-black">Aucun produit trouvé</h2>
                            <p className="mt-3 text-[#5E5E5E]">Essayez une autre recherche ou contactez-nous pour un sourcing personnalisé.</p>
                        </div>
                    )}

                    {pagination.last_page > 1 && (
                        <div className="mt-10 flex justify-center gap-2">
                            {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map(page => (
                                <button key={page} onClick={() => apply(undefined, page)} className={`grid size-11 place-items-center border text-sm font-bold ${page === pagination.current_page ? 'border-[#171717] bg-[#171717] text-white' : 'border-black/10 bg-white'}`}>{page}</button>
                            ))}
                        </div>
                    )}
                </div>
            </section>
        </PublicLayout>
    );
}
