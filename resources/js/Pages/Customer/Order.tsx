import CustomerShell from '@/Components/CustomerShell';
import ManualPaymentForm from '@/Components/ManualPaymentForm';
import { PublicConfig } from '@/Layouts/PublicLayout';
import { money, writeCart } from '@/lib/cart';
import { Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Check, ChevronRight, Clock3, CreditCard, Download, ImageOff, UserRound, X } from 'lucide-react';
import { useEffect } from 'react';
import { publicStatus } from './Orders';

function dateLabel(value?: string) {
    if (!value) return 'En attente';
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export default function Order({ order, items, accounts, submissions, cartCreated, publicConfig }: { order: any; items: any[]; accounts: any[]; submissions: any[]; cartCreated?: string; publicConfig: PublicConfig }) {
    useEffect(() => { if (cartCreated && cartCreated === order.checkout_key) writeCart([]); }, [cartCreated, order.checkout_key]);
    const canSubmit = order.status === 'pending_payment' && ['awaiting_submission', 'rejected'].includes(order.payment_status);
    const confirmed = order.payment_status === 'confirmed';
    const cancelled = order.status === 'cancelled';
    const latest = submissions[submissions.length - 1];
    const approved = submissions.find(s => s.status === 'confirmed');
    const paymentDate = approved?.reviewed_at || order.payment_confirmed_at;
    const updatedAt = latest?.reviewed_at || latest?.submitted_at || order.payment_confirmed_at || order.created_at;
    const count = items.reduce((sum, item) => sum + Number(item.quantity), 0);
    const steps = [
        { label: 'Commande créée', date: order.created_at, complete: true },
        { label: 'Paiement confirmé', date: paymentDate, complete: confirmed },
        { label: 'Commande confirmée', date: order.payment_confirmed_at, complete: confirmed && !cancelled },
    ];
    const statusMessage = cancelled ? 'Cette commande a été annulée.' : confirmed ? 'Votre paiement a été reçu et votre commande est confirmée.' : order.payment_status === 'under_review' ? 'Votre justificatif a bien été reçu. Madina Import vérifie actuellement votre paiement.' : order.payment_status === 'rejected' ? 'Veuillez transmettre une nouvelle preuve de paiement.' : 'Effectuez votre paiement pour confirmer votre commande.';
    const header = <div className="customer-order mb-6">
        <nav aria-label="Fil d’Ariane" className="order-muted mb-5 flex flex-wrap items-center gap-2 text-sm">
            <Link href="/">Accueil</Link><ChevronRight size={14} aria-hidden="true"/><Link href="/mes-commandes">Mes commandes</Link><ChevronRight size={14} aria-hidden="true"/><span className="break-all" aria-current="page">{order.number}</span>
        </nav>
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0"><h1 className="break-words text-3xl font-extrabold">Commande {order.number}</h1><p className="order-muted mt-2 text-sm">Passée le {dateLabel(order.ordered_at || order.created_at)}</p></div>
            <span className={`order-status inline-flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-bold ${confirmed && !cancelled ? 'order-status-confirmed' : ''}`}>
                {cancelled ? <X size={18} aria-hidden="true"/> : confirmed ? <Check size={18} aria-hidden="true"/> : <Clock3 size={18} aria-hidden="true"/>}{publicStatus(order)}
            </span>
        </div>
    </div>;

    return <CustomerShell title={`Commande ${order.number}`} publicConfig={publicConfig} header={header}>
        <div className="customer-order grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
            <div className="min-w-0 space-y-6">
                <section className="commerce-panel" aria-label="Progression de la commande">
                    <ol className="order-progress grid grid-cols-3 gap-2 text-center text-sm">
                        {steps.map(step => <li key={step.label} className={step.complete ? 'is-complete' : ''}>
                            <span className="order-step-icon relative z-10 mx-auto mb-2 grid size-7 place-items-center rounded-full">{step.complete ? <Check size={17} aria-hidden="true"/> : <Clock3 size={15} aria-hidden="true"/>}</span>
                            <p className="font-bold">{step.label}</p><p className="order-muted mt-1">{dateLabel(step.date)}</p>
                        </li>)}
                    </ol>
                </section>
                <section className="commerce-panel min-w-0" aria-labelledby="order-items-title">
                    <h2 id="order-items-title" className="mb-5 text-xl font-bold">Articles commandés</h2>
                    <div className="overflow-x-auto">
                        <table className="order-items w-full text-left text-sm">
                            <thead className="order-tint order-muted"><tr><th className="rounded-l-lg p-3 font-normal">Produit</th><th className="p-3 font-normal">Référence</th><th className="p-3 text-right font-normal">Prix unitaire</th><th className="p-3 text-center font-normal">Quantité</th><th className="rounded-r-lg p-3 text-right font-normal">Sous-total</th></tr></thead>
                            <tbody>{items.map(item => <tr key={item.id}>
                                <td className="p-3"><div className="flex items-center gap-3"><span className="order-tint grid size-14 shrink-0 place-items-center overflow-hidden rounded-lg">{item.image_url ? <img src={item.image_url} alt="" loading="lazy" className="size-full object-contain p-1"/> : <ImageOff size={22} className="order-muted" aria-hidden="true"/>}</span><strong>{item.name}</strong></div></td>
                                <td className="order-muted p-3">{item.sku}</td><td className="whitespace-nowrap p-3 text-right">{money(Number(item.unit_price))}</td><td className="p-3 text-center">{Number(item.quantity)}</td><td className="whitespace-nowrap p-3 text-right">{money(Number(item.client_total))}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                </section>
                <section className="commerce-panel" aria-labelledby="order-timeline-title">
                    <h2 id="order-timeline-title" className="mb-5 text-xl font-bold">Chronologie</h2>
                    <ol className="order-timeline text-sm">
                        <li><span className="order-timeline-icon"><Check size={15} aria-hidden="true"/></span><time className="order-muted">{dateLabel(order.created_at)}</time><div><h3 className="font-bold">Commande créée</h3><p className="order-muted mt-1">Votre commande a bien été enregistrée.</p></div></li>
                        {submissions.map(submission => <li key={submission.id}>
                            <span className="order-timeline-icon">{submission.status === 'rejected' ? <X size={15} aria-hidden="true"/> : submission.status === 'confirmed' ? <Check size={15} aria-hidden="true"/> : <Clock3 size={15} aria-hidden="true"/>}</span>
                            <time className="order-muted">{dateLabel(submission.submitted_at)}</time>
                            <div><h3 className="font-bold">{submission.status === 'under_review' ? 'Paiement en cours de vérification' : submission.status === 'confirmed' ? 'Paiement confirmé' : 'Paiement refusé'}</h3>
                                <p className="order-muted mt-1 break-words">{submission.account_snapshot.display_name} · Référence : {submission.transaction_reference}</p>
                                {submission.has_proof && <a className="order-outline-button mt-2 inline-flex items-center gap-2 rounded border px-3 py-1.5" href={`/paiement-preuves/${submission.id}`}><Download size={15} aria-hidden="true"/>Télécharger le justificatif (si fourni)</a>}
                                {submission.rejection_reason && <p className="mt-2">Motif : {submission.rejection_reason}</p>}
                            </div>
                        </li>)}
                        {latest?.reviewed_at && <li><span className="order-timeline-icon">{latest.status === 'confirmed' ? <Check size={15} aria-hidden="true"/> : <X size={15} aria-hidden="true"/>}</span><time className="order-muted">{dateLabel(latest.reviewed_at)}</time><div><h3 className="font-bold">Vérifié par l’administration</h3><p className="order-muted mt-1">{latest.status === 'confirmed' ? 'Le paiement a été vérifié.' : 'Le paiement n’a pas été validé.'}</p></div></li>}
                        {order.payment_confirmed_at && <li><span className="order-timeline-icon"><Check size={15} aria-hidden="true"/></span><time className="order-muted">{dateLabel(order.payment_confirmed_at)}</time><div><h3 className="font-bold">Confirmation</h3><p className="order-muted mt-1">Votre commande est confirmée{cancelled ? ', puis a été annulée.' : ' et en cours de traitement.'}</p></div></li>}
                    </ol>
                </section>
                {canSubmit && <ManualPaymentForm number={order.number} accounts={accounts}/>}
            </div>
            <aside className="commerce-panel min-w-0 lg:sticky lg:top-24" aria-labelledby="order-summary-title">
                <h2 id="order-summary-title" className="mb-5 text-xl font-bold">Résumé de la commande</h2>
                <dl><div className="order-divider flex justify-between gap-4 border-b pb-4 text-sm"><dt className="order-muted">Sous-total ({count} {count === 1 ? 'article' : 'articles'})</dt><dd className="whitespace-nowrap font-bold">{money(Number(order.client_total))}</dd></div>
                    <div className="order-tint mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg p-3"><dt className="font-bold">Montant total</dt><dd className="text-xl font-bold">{money(Number(order.client_total))} (MGA)</dd></div>
                </dl>
                <div className={`order-status mt-5 flex items-start gap-3 rounded-lg p-4 ${confirmed && !cancelled ? 'order-status-confirmed' : ''}`}>
                    {confirmed && !cancelled ? <Check size={25} className="shrink-0" aria-hidden="true"/> : <Clock3 size={25} className="shrink-0" aria-hidden="true"/>}
                    <div><h3 className="font-bold">{publicStatus(order)}</h3><p className="mt-1 text-sm leading-6">{statusMessage}</p></div>
                </div>
                <dl className="order-tint mt-4 space-y-6 rounded-lg p-5 text-sm">
                    <div className="flex gap-4"><CalendarDays size={22} className="order-muted shrink-0" aria-hidden="true"/><div><dt className="order-muted">Date de commande</dt><dd className="mt-1">{dateLabel(order.ordered_at || order.created_at)}</dd></div></div>
                    <div className="flex gap-4"><CreditCard size={22} className="order-muted shrink-0" aria-hidden="true"/><div className="min-w-0"><dt className="order-muted">Référence de paiement</dt><dd className="mt-1 break-all">{latest?.transaction_reference || 'Non renseignée'}</dd></div></div>
                    {latest?.reviewed_at && <div className="flex gap-4"><UserRound size={22} className="order-muted shrink-0" aria-hidden="true"/><div><dt className="order-muted">Vérifié par</dt><dd className="mt-1">Administration Madina</dd></div></div>}
                    <div className="flex gap-4"><Clock3 size={22} className="order-muted shrink-0" aria-hidden="true"/><div><dt className="order-muted">Dernier événement</dt><dd className="mt-1">{dateLabel(updatedAt)}</dd></div></div>
                </dl>
                <Link href="/mes-commandes" className="order-outline-button mt-6 flex min-h-12 items-center justify-center gap-2 rounded-lg border px-4 py-3 text-sm font-bold"><ArrowLeft size={17} aria-hidden="true"/>Retour à mes commandes</Link>
            </aside>
        </div>
    </CustomerShell>;
}
