import CustomerShell from '@/Components/CustomerShell';
import { PublicConfig } from '@/Layouts/PublicLayout';
import { money } from '@/lib/cart';
import { Link } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ChevronRight, Clock3, Coins, FileText, Home, LogOut, XCircle } from 'lucide-react';
export function publicStatus(order:any):string { if(order.status==='cancelled')return 'Commande annulée';return ({awaiting_submission:'En attente de paiement',under_review:'Paiement en cours de vérification',confirmed:'Commande confirmée',rejected:'Paiement non validé — nouvelle preuve requise'} as any)[order.payment_status]||order.status; }
function orderDate(value: string) {
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(date);
}

export default function Orders({ orders, publicConfig }: { orders: any[]; publicConfig: PublicConfig }) {
    const header = <div className="customer-order mb-8">
        <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
        <nav aria-label="Fil d’Ariane" className="order-muted flex flex-wrap items-center gap-2 text-sm">
            <Link href="/" className="inline-flex items-center gap-2"><Home size={16} aria-hidden="true"/>Mon compte</Link>
            <ChevronRight size={16} aria-hidden="true"/><span aria-current="page">Mes commandes</span>
        </nav>
        <Link href={route('logout')} method="post" as="button" className="customer-order-details inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold transition hover:text-[#C8102E] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C8102E]">
            <LogOut size={18} aria-hidden="true" /> Déconnexion
        </Link>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-4">
            <h1 className="text-3xl font-extrabold">Mes commandes</h1>
            <span className="order-tint order-muted rounded-full px-4 py-2 text-sm font-bold">{orders.length} {orders.length === 1 ? 'commande' : 'commandes'}</span>
        </div>
        <p className="order-muted mt-3">Retrouvez ici toutes vos commandes et suivez leur statut.</p>
    </div>;

    return <CustomerShell title="Mes commandes" publicConfig={publicConfig} header={header}>
        <div className="customer-order min-h-[50vh] space-y-5">
            {!orders.length && <div className="commerce-panel py-12 text-center">
                <FileText size={36} className="order-muted mx-auto mb-4" aria-hidden="true"/>
                <p className="font-bold">Vous n’avez pas encore de commande.</p>
                <Link href="/catalogue" className="public-button mt-6">Découvrir le catalogue<ChevronRight size={16} aria-hidden="true"/></Link>
            </div>}
            {orders.map(order => {
                const confirmed = order.payment_status === 'confirmed' && order.status !== 'cancelled';
                const failed = order.status === 'cancelled' || order.payment_status === 'rejected';
                const StatusIcon = confirmed ? CheckCircle2 : failed ? XCircle : Clock3;
                return <article key={order.id} className="commerce-panel customer-order-card">
                    <div className="customer-order-number flex min-w-0 items-center gap-5">
                        <span className="customer-order-document grid size-16 shrink-0 place-items-center rounded-xl"><FileText size={30} aria-hidden="true"/></span>
                        <div className="min-w-0"><p className="order-muted mb-2 text-sm">Numéro de commande</p><h2 className="break-all font-bold">{order.number}</h2></div>
                    </div>
                    <div className="customer-order-field flex items-start gap-3">
                        <CalendarDays size={22} className="order-muted shrink-0" aria-hidden="true"/>
                        <div><p className="order-muted mb-2 text-sm">Date de commande</p><p className="font-bold">{orderDate(order.ordered_at || order.created_at)}</p></div>
                    </div>
                    <div className="customer-order-field flex items-start gap-3">
                        <Coins size={22} className="order-muted shrink-0" aria-hidden="true"/>
                        <div><p className="order-muted mb-2 text-sm">Total</p><p className="whitespace-nowrap font-bold">{money(Number(order.client_total))}</p></div>
                    </div>
                    <div className="customer-order-actions space-y-3">
                        <p className={`order-status inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-center text-sm ${confirmed ? 'order-status-confirmed' : ''}`}><StatusIcon size={18} className="shrink-0" aria-hidden="true"/>{publicStatus(order)}</p>
                        <Link href={`/mes-commandes/${order.number}`} aria-label={`Voir les détails de la commande ${order.number}`} className="customer-order-details flex min-h-11 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold">Voir les détails<ChevronRight size={16} aria-hidden="true"/></Link>
                    </div>
                </article>;
            })}
        </div>
    </CustomerShell>;
}
