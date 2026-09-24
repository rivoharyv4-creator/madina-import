import { useForm } from '@inertiajs/react';
import { CircleAlert, Copy, Landmark, Smartphone, UploadCloud } from 'lucide-react';
import { useRef, useState } from 'react';

type Account = { id: number; method: string; display_name: string; account_holder: string; account_number: string; additional_instructions?: string; proof_required: boolean };

function PaymentMark({ method }: { method: string }) {
    const name = method.toLowerCase();
    const logo = name.includes('mvola') ? '/images/payments/mvola.svg' : name.includes('airtel') ? '/images/payments/airtel-money.png' : name.includes('orange') ? '/images/payments/orange-money.jpg' : null;
    if (logo) return <img src={logo} alt="" className="manual-payment-logo h-14 w-full max-w-[112px] rounded object-contain"/>;
    return /ban|virement/.test(name) ? <Landmark size={40} className="order-muted" aria-hidden="true"/> : <Smartphone size={40} className="order-muted" aria-hidden="true"/>;
}

export default function ManualPaymentForm({ number, accounts }: { number: string; accounts: Account[] }) {
    const form = useForm({ submission_key: crypto.randomUUID(), payment_account_id: '', transaction_reference: '', proof: null as File | null });
    const [copied, setCopied] = useState('');
    const [fileError, setFileError] = useState('');
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const selected = accounts.find(account => String(account.id) === form.data.payment_account_id);
    const chooseFile = (file?: File) => {
        setFileError('');
        if (!file) { form.setData('proof', null); return; }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            setFileError('Choisissez une image JPEG, PNG ou WebP de 5 Mo maximum.');
            form.setData('proof', null);
            if (fileInput.current) fileInput.current.value = '';
            return;
        }
        form.setData('proof', file);
        form.clearErrors('proof');
    };

    return <form className="commerce-panel space-y-5" onSubmit={event => {
        event.preventDefault();
        if (form.processing || fileError) return;
        form.post(`/mes-commandes/${number}/paiement`, { forceFormData: true, onSuccess: () => {
            form.reset(); form.setData('submission_key', crypto.randomUUID()); setCopied('');
            if (fileInput.current) fileInput.current.value = '';
        } });
    }}>
        <div className="space-y-4">
            <h2 className="text-xl font-bold">Vérification du paiement</h2>
            <div className="manual-payment-notice flex items-start gap-3 rounded-lg border px-3 py-3">
                <CircleAlert size={24} className="shrink-0" aria-hidden="true"/>
                <p className="text-sm leading-6"><strong>Remarque :</strong> si vous êtes un nouveau client, votre paiement sera vérifié manuellement. Cette opération peut prendre de 24 à 48 heures en moyenne. Aucune action n’est nécessaire de votre part. Vous recevrez un e-mail de confirmation lorsque la vérification sera terminée. Merci pour votre compréhension.</p>
            </div>
        </div>
        <p className="text-sm leading-6">Effectuez le paiement en dehors du site, puis transmettez votre référence et une preuve de paiement si demandée.<br/>L’envoi de ces éléments ne confirme pas le paiement. <strong>Il s’agit d’une demande de vérification manuelle.</strong></p>
        {!accounts.length && <p role="status">Les moyens de paiement sont temporairement indisponibles. Contactez Madina Import.</p>}
        <fieldset disabled={form.processing}>
            <legend className="mb-3 text-sm font-bold">Choisissez votre moyen de paiement</legend>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {accounts.map(account => <label key={account.id} className={`manual-payment-option flex cursor-pointer flex-col items-center gap-3 rounded-lg border p-3 text-center ${selected?.id === account.id ? 'is-selected' : ''}`}>
                    <input type="radio" name="payment_account_id" required value={account.id} checked={selected?.id === account.id} onChange={() => { form.setData('payment_account_id', String(account.id)); setCopied(''); }} className="manual-payment-radio size-4"/>
                    <span className="flex h-14 items-center justify-center"><PaymentMark method={account.method}/></span>
                    <span className="text-sm font-bold">{account.display_name}</span>
                </label>)}
            </div>
            {form.errors.payment_account_id && <p className="checkout-required mt-2 text-sm">{form.errors.payment_account_id}</p>}
        </fieldset>
        {selected && <div className="order-tint rounded-lg p-4">
            <h3 className="mb-4 text-sm font-bold">Détails du compte destinataire ({selected.method})</h3>
            <div className="grid grid-cols-2 items-center gap-x-4 gap-y-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:gap-x-5">
                <div className="min-w-0 flex-1"><p className="order-muted text-sm">Titulaire</p><p className="mt-1 break-words font-bold">{selected.account_holder}</p></div>
                <div className="order-divider min-w-0 border-l pl-4 sm:pl-5"><p className="order-muted text-sm">Numéro</p><p className="mt-1 break-all font-bold">{selected.account_number}</p></div>
                <button type="button" disabled={form.processing} className="manual-payment-copy col-span-2 inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-bold sm:col-span-1" onClick={async () => {
                    try { await navigator.clipboard.writeText(selected.account_number); setCopied('Numéro copié.'); }
                    catch { setCopied('Copiez le numéro affiché ci-dessus.'); }
                }}><Copy size={17} aria-hidden="true"/>Copier le numéro</button>
            </div>
            {copied && <p role="status" className="mt-3 text-sm">{copied}</p>}
            {selected.additional_instructions && <p className="order-muted mt-3 whitespace-pre-line text-sm">{selected.additional_instructions}</p>}
        </div>}
        <div><label htmlFor="payment-reference" className="mb-2 block text-sm font-medium">Référence de transaction</label>
            <input id="payment-reference" required disabled={form.processing} minLength={3} maxLength={120} placeholder="Ex. : 7F3A9K, 123456789, etc." className="block w-full" value={form.data.transaction_reference} onChange={event => form.setData('transaction_reference', event.target.value)} aria-describedby="payment-reference-help" aria-invalid={Boolean(form.errors.transaction_reference)}/>
            <p id="payment-reference-help" className="order-muted mt-2 text-xs">Saisissez la référence fournie {selected ? `par ${selected.method} ` : ''}après votre paiement.</p>
            {form.errors.transaction_reference && <p className="checkout-required mt-2 text-sm">{form.errors.transaction_reference}</p>}
        </div>
        <div><label htmlFor="payment-proof" className="mb-2 block text-sm font-medium">Preuve de paiement {selected?.proof_required ? '(obligatoire)' : '(facultative)'}</label>
            <div className={`manual-payment-upload relative rounded-lg border-2 border-dashed p-5 text-center ${dragging ? 'is-dragging' : ''}`} onDragOver={event => { event.preventDefault(); if (!form.processing) setDragging(true); }} onDragLeave={() => setDragging(false)} onDrop={event => {
                event.preventDefault(); setDragging(false); if (form.processing) return;
                const file = event.dataTransfer.files[0];
                if (file && fileInput.current) { const transfer = new DataTransfer(); transfer.items.add(file); fileInput.current.files = transfer.files; }
                chooseFile(file);
            }}>
                <UploadCloud size={30} className="order-muted mx-auto mb-2" aria-hidden="true"/>
                <p className="break-all text-sm font-bold">{form.data.proof?.name || 'Cliquez pour ajouter une preuve de paiement'}</p><p className="order-muted mt-1 text-xs">ou glissez-déposez un fichier ici</p>
                <input ref={fileInput} id="payment-proof" className="absolute inset-0 size-full cursor-pointer opacity-0" type="file" disabled={form.processing} accept="image/jpeg,image/png,image/webp" required={Boolean(selected?.proof_required)} onChange={event => chooseFile(event.target.files?.[0])} aria-describedby="payment-proof-help" aria-invalid={Boolean(fileError || form.errors.proof)}/>
            </div>
            <p id="payment-proof-help" className="order-muted mt-2 text-xs">JPEG, PNG ou WebP · 5 Mo maximum</p>
            {(fileError || form.errors.proof) && <p role="alert" className="checkout-required mt-2 text-sm">{fileError || form.errors.proof}</p>}
        </div>
        <button className="public-button min-h-11" disabled={form.processing || !accounts.length || Boolean(fileError)}>{form.processing ? 'Envoi en cours…' : 'J’ai effectué le paiement'}</button>
    </form>;
}
