import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { money } from '@/lib/cart';
import { useEffect, useRef, useState } from 'react';
import { CheckCircle2, ChevronDown, ChevronUp, Clock3, Download, Landmark, ListChecks, Pencil, Plus, Save, ShoppingCart, Trash2, WalletCards, XCircle } from 'lucide-react';

const empty = { method: 'MVola', display_name: '', account_holder: '', account_number: '', additional_instructions: '', is_active: true, proof_required: false, sort_order: 0 };
function dateLabel(value?: string) {
    if (!value) return '—';
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}
function Status({ status }: { status: string }) {
    const positive = ['confirmed', 'completed', 'active'].includes(status);
    const negative = ['cancelled', 'rejected', 'inactive'].includes(status);
    const Icon = positive ? CheckCircle2 : negative ? XCircle : Clock3;
    const label = ({ confirmed: 'Confirmée', completed: 'Terminée', active: 'Actif', inactive: 'Inactif', cancelled: 'Annulée', rejected: 'Refusé', under_review: 'À vérifier', processing: 'En préparation', pending_payment: 'En attente de paiement' } as Record<string, string>)[status] || status;
    return <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${positive ? 'bg-emerald-50 text-emerald-700' : negative ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700'}`}><Icon size={14} aria-hidden="true"/>{label}</span>;
}
function Method({ method }: { method: string }) {
    const name = method.toLowerCase();
    const image = name.includes('mvola') ? '/images/payments/mvola.svg' : name.includes('airtel') ? '/images/payments/airtel-money.png' : name.includes('orange') ? '/images/payments/orange-money.jpg' : null;
    return image ? <img src={image} alt={method} className="h-8 w-20 rounded bg-white object-contain p-1"/> : <span className="inline-flex items-center gap-2"><Landmark size={16} aria-hidden="true"/>{method}</span>;
}
const tableClass = 'w-full text-left text-xs [&_th]:whitespace-nowrap [&_th]:bg-gray-50 [&_th]:px-4 [&_th]:py-3 [&_th]:font-semibold [&_th]:text-gray-500 [&_td]:px-4 [&_td]:py-3 [&_tbody_tr]:border-t [&_tbody_tr]:border-gray-100';

export default function ManualPayments({ accounts, submissions, orders }: { accounts: any[]; submissions: any[]; orders: any[] }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const editor = useRef<HTMLDivElement>(null);
    const state = useForm({ status: '' });
    const account = useForm(empty);
    const review = useForm({ decision: 'confirmed', rejection_reason: '' });
    const [editing, setEditing] = useState<number | null>(null);
    const [pending, setPending] = useState<any>(null);
    const [view, setView] = useState<'payments' | 'accounts'>('payments');
    const [showEditor, setShowEditor] = useState(false);
    useEffect(() => { if (pending && dialog.current && !dialog.current.open) dialog.current.showModal(); }, [pending]);
    useEffect(() => { if (showEditor) editor.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, [showEditor, editing]);
    const openAccount = (value?: any) => {
        account.clearErrors(); setEditing(value?.id ?? null);
        account.setData(value ? { method: value.method, display_name: value.display_name, account_holder: value.account_holder, account_number: value.account_number, additional_instructions: value.additional_instructions || '', is_active: Boolean(value.is_active), proof_required: Boolean(value.proof_required), sort_order: value.sort_order } : { ...empty });
        setShowEditor(true);
    };
    return <AuthenticatedLayout header={<><p className="eyebrow">Gestion des paiements</p><h1 className="page-title">{view === 'payments' ? 'Paiements manuels' : 'Comptes de réception'}</h1><p className="mt-1 text-xs text-gray-400">Suivez les paiements reçus via virement ou mobile money et gérez vos comptes de réception.</p></>}>
        <Head title={view === 'payments' ? 'Paiements manuels' : 'Comptes de réception'}/>
        <div className="space-y-5 text-sm">
            <nav aria-label="Gestion des paiements" className="flex flex-wrap gap-2 border-b border-gray-200 pb-4">
                <button type="button" aria-pressed={view === 'payments'} onClick={() => setView('payments')} className={view === 'payments' ? 'btn-primary' : 'btn-secondary'}><WalletCards size={16} aria-hidden="true"/>Paiements manuels</button>
                <button type="button" aria-pressed={view === 'accounts'} onClick={() => setView('accounts')} className={view === 'accounts' ? 'btn-primary' : 'btn-secondary'}><Landmark size={16} aria-hidden="true"/>Comptes de réception</button>
            </nav>
            {Object.values({ ...account.errors, ...review.errors, ...state.errors }).map((error, index) => <p role="alert" key={index} className="text-red-700">{error}</p>)}
            {view === 'payments' ? <>
                <section className="panel !p-0 overflow-hidden">
                    <div className="panel-head p-4"><div className="flex gap-3"><ShoppingCart size={22} className="text-[#BD2433]" aria-hidden="true"/><div><h2>Commandes en ligne</h2><p>Commandes en attente de paiement ou déjà réglées par paiement manuel.</p></div></div><span className="rounded bg-gray-100 px-2 py-1 text-xs text-gray-500">{orders.length} {orders.length === 1 ? 'commande' : 'commandes'}</span></div>
                    <div className="overflow-x-auto"><table className={tableClass}><thead><tr><th>Référence commande</th><th>Montant</th><th>Statut</th><th>Date de création</th><th>Action</th></tr></thead><tbody>
                        {!orders.length && <tr><td colSpan={5} className="text-center text-gray-400">Aucune commande en ligne.</td></tr>}
                        {orders.map(order => <tr key={order.id}><td className="font-semibold">{order.number}</td><td className="whitespace-nowrap">{money(Number(order.client_total))}</td><td><Status status={order.status}/></td><td>{dateLabel(order.created_at)}</td><td>
                            {(order.status === 'confirmed' || order.status === 'processing' || (order.status === 'pending_payment' && ['awaiting_submission', 'rejected'].includes(order.payment_status))) ? <select className="field min-w-[160px] !text-xs" aria-label={`Modifier le statut de ${order.number}`} disabled={state.processing} value="" onChange={event => {
                                const status = event.target.value; if (!status || !confirm('Confirmer le changement de statut ?')) return;
                                state.transform(() => ({ status })); state.patch(`/admin/commandes-web/${order.id}`);
                            }}><option value="">Changer le statut</option>{order.status === 'confirmed' ? <option value="processing">En préparation</option> : order.status === 'processing' ? <option value="completed">Terminée</option> : <option value="cancelled">Annuler</option>}</select> : '—'}
                        </td></tr>)}
                    </tbody></table></div>
                </section>
                <section className="panel !p-0 overflow-hidden">
                    <div className="panel-head p-4"><div className="flex gap-3"><ListChecks size={22} className="text-[#BD2433]" aria-hidden="true"/><div><h2>Soumissions et historique des tentatives</h2><p>Paiements envoyés par les clients et vérifiés manuellement par votre équipe.</p></div></div></div>
                    <div className="overflow-x-auto"><table className={tableClass}><thead><tr><th>Référence commande</th><th>Client</th><th>Méthode</th><th>Montant</th><th>Référence transaction</th><th>Reçu le</th><th>Vérifié le</th><th>Preuve</th><th>Statut / actions</th></tr></thead><tbody>
                        {!submissions.length && <tr><td colSpan={9} className="text-center text-gray-400">Aucune soumission de paiement.</td></tr>}
                        {submissions.map(submission => <tr key={submission.id}>
                            <td>{submission.number}</td><td>{submission.name}</td><td><p>{submission.account_snapshot.method}</p><p className="mt-1 text-gray-400">{submission.account_snapshot.account_holder}<br/>{submission.account_snapshot.account_number}</p></td><td className="whitespace-nowrap">{money(Number(submission.client_total))}</td><td className="break-all">{submission.transaction_reference}</td><td>{dateLabel(submission.submitted_at)}</td><td>{dateLabel(submission.reviewed_at)}{submission.reviewed_at && <p className="mt-1 text-gray-400">Vérifié par #{submission.reviewed_by}</p>}</td>
                            <td>{submission.has_proof ? <a href={`/paiement-preuves/${submission.id}`} className="inline-flex items-center gap-1 text-blue-600 underline"><Download size={14} aria-hidden="true"/>Télécharger</a> : '—'}</td>
                            <td><Status status={submission.status}/>{submission.rejection_reason && <p className="mt-2 text-red-600">Motif : {submission.rejection_reason}</p>}{submission.status === 'under_review' && <div className="mt-3 flex flex-wrap gap-2"><button disabled={review.processing} className="btn-primary !text-xs" onClick={() => { setPending(submission); review.clearErrors(); review.setData({ decision: 'confirmed', rejection_reason: '' }); }}>Confirmer</button><button disabled={review.processing} className="btn-secondary !text-xs text-red-600" onClick={() => { setPending(submission); review.clearErrors(); review.setData({ decision: 'rejected', rejection_reason: '' }); }}>Refuser</button></div>}</td>
                        </tr>)}
                    </tbody></table></div>
                </section>
            </> : <>
                <section className="panel !p-0 overflow-hidden">
                    <div className="panel-head p-4"><div className="flex gap-3"><Landmark size={22} className="text-[#BD2433]" aria-hidden="true"/><div><h2>Comptes de réception</h2><p>Comptes pour les paiements manuels : virement, mobile money, etc.</p></div></div><button type="button" disabled={account.processing} className="btn-primary" onClick={() => openAccount()}><Plus size={16} aria-hidden="true"/>Ajouter un compte</button></div>
                    <div className="overflow-x-auto"><table className={tableClass}><thead><tr><th>Méthode</th><th>Nom affiché</th><th>Titulaire</th><th>Numéro / IBAN</th><th>Statut</th><th>Ordre</th><th>Actions</th></tr></thead><tbody>
                        {!accounts.length && <tr><td colSpan={7} className="text-center text-gray-400">Aucun compte de réception.</td></tr>}
                        {accounts.map(value => <tr key={value.id}><td><Method method={value.method}/></td><td>{value.display_name}</td><td>{value.account_holder}</td><td className="break-all">{value.account_number}</td><td><Status status={value.is_active ? 'active' : 'inactive'}/></td><td>{value.sort_order}</td><td><div className="flex gap-2"><button disabled={account.processing} className="btn-secondary !text-xs" onClick={() => openAccount(value)}><Pencil size={14} aria-hidden="true"/>Modifier</button><button disabled={account.processing} className="btn-secondary !text-xs text-red-600" onClick={() => { if (confirm('Supprimer ce compte de paiement ? Son historique sera conservé.')) account.delete(`/admin/comptes-paiement/${value.id}`, { onSuccess: () => { if (editing === value.id) { setEditing(null); setShowEditor(false); account.reset(); } } }); }}><Trash2 size={14} aria-hidden="true"/>Supprimer</button></div><p className="mt-2 text-[10px] text-gray-400">Modifié par {value.updated_by_name} (#{value.updated_by}), {dateLabel(value.updated_at)}</p></td></tr>)}
                    </tbody></table></div>
                </section>
                <section className="panel" ref={editor}>
                    <button type="button" className="panel-head w-full text-left" disabled={account.processing} aria-expanded={showEditor} aria-controls="account-editor" onClick={() => setShowEditor(!showEditor)}><span className="flex gap-3"><Plus size={22} className="text-[#BD2433]" aria-hidden="true"/><span><span className="block text-[15px] font-bold">{editing ? 'Modifier le compte de réception' : 'Ajouter un compte de réception'}</span><span className="mt-0.5 block text-xs text-gray-400">Configurez un compte pour recevoir les paiements manuels.</span></span></span>{showEditor ? <ChevronUp size={18}/> : <ChevronDown size={18}/>}</button>
                    {showEditor && <form id="account-editor" className="mt-5 grid gap-4 md:grid-cols-2" onSubmit={event => {
                        event.preventDefault(); if (editing && !account.data.is_active && !confirm('Confirmer la désactivation de ce compte ?')) return;
                        const options = { onSuccess: () => { setEditing(null); account.reset(); setShowEditor(false); } };
                        if (editing) account.put(`/admin/comptes-paiement/${editing}`, options); else account.post('/admin/comptes-paiement', options);
                    }}>
                        {(['method', 'display_name', 'account_holder', 'account_number', 'additional_instructions', 'sort_order'] as const).map(field => <div key={field}><label htmlFor={`account-${field}`} className="mb-1.5 block text-xs font-semibold text-gray-600">{({ method: 'Méthode de paiement', display_name: 'Nom affiché', account_holder: 'Titulaire du compte', account_number: 'Numéro / IBAN', additional_instructions: 'Instructions (facultatif)', sort_order: 'Ordre d’affichage' })[field]}{!['additional_instructions', 'sort_order'].includes(field) && <span className="ml-1 text-[#BD2433]">*</span>}</label>
                            {field === 'additional_instructions' ? <textarea id={`account-${field}`} className="field min-h-20" placeholder="Ex. : Envoyez le paiement et indiquez la référence de votre commande." value={account.data[field]} onChange={event => account.setData(field, event.target.value)}/> : <input id={`account-${field}`} list={field === 'method' ? 'payment-methods' : undefined} className="field" type={field === 'sort_order' ? 'number' : 'text'} min={field === 'sort_order' ? 0 : undefined} required value={account.data[field]} onChange={event => account.setData(field, field === 'sort_order' ? Number(event.target.value) : event.target.value)}/>}
                            {field === 'sort_order' && <p className="mt-1 text-[10px] text-gray-400">Plus petit nombre = affiché en premier</p>}
                        </div>)}
                        <datalist id="payment-methods"><option value="MVola"/><option value="Airtel Money"/><option value="Orange Money"/><option value="Virement bancaire"/></datalist>
                        <label className="flex items-start gap-2 text-xs"><input className="rounded border-gray-300 text-[#BD2433]" type="checkbox" checked={account.data.is_active} onChange={event => account.setData('is_active', event.target.checked)}/><span className="font-semibold">Compte actif<small className="mt-1 block font-normal text-gray-400">Le compte est visible pour les clients.</small></span></label>
                        <label className="flex items-start gap-2 text-xs"><input className="rounded border-gray-300 text-[#BD2433]" type="checkbox" checked={account.data.proof_required} onChange={event => account.setData('proof_required', event.target.checked)}/><span className="font-semibold">Preuve de paiement obligatoire<small className="mt-1 block font-normal text-gray-400">Le client doit fournir une preuve lors de la soumission.</small></span></label>
                        <div className="flex gap-3 md:col-span-2"><button className="btn-primary" disabled={account.processing}><Save size={16} aria-hidden="true"/>{account.processing ? 'Enregistrement…' : 'Enregistrer le compte'}</button><button type="button" disabled={account.processing} className="btn-secondary" onClick={() => { setEditing(null); account.reset(); account.clearErrors(); setShowEditor(false); }}>Annuler</button></div>
                    </form>}
                </section>
            </>}
            {pending&&<dialog ref={dialog} aria-labelledby="review-title" onCancel={()=>setPending(null)} className="w-[calc(100%-2rem)] max-w-lg rounded-xl bg-white p-0 backdrop:bg-black/50"><form className="w-full max-w-lg rounded-xl bg-white p-6" onSubmit={e=>{e.preventDefault();review.post(`/admin/paiements-manuels/${pending.id}`,{onSuccess:()=>setPending(null)});}}><h2 id="review-title" className="text-[15px] font-bold">{review.data.decision==='confirmed'?'Confirmer ce paiement ?':'Refuser ce paiement ?'}</h2><div role="alert">{Object.values(review.errors).map((e,i)=><p key={i} className="text-red-700">{e}</p>)}</div><p className="my-4">{pending.number} · {money(Number(pending.client_total))}</p>{review.data.decision==='rejected'&&<label>Motif destiné au client<textarea autoFocus required minLength={5} className="field my-4" value={review.data.rejection_reason} onChange={e=>review.setData('rejection_reason',e.target.value)}/></label>}<div className="flex gap-5"><button autoFocus={review.data.decision==='confirmed'} disabled={review.processing} className="btn-primary">{review.processing?'Traitement…':'Confirmer l’action'}</button><button type="button" className="btn-secondary" onClick={()=>setPending(null)}>Annuler</button></div></form></dialog>}
        </div>
    </AuthenticatedLayout>;
}
