<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if($this->route('module')==='demandes-devis'&&!$this->route('id')){
            $this->merge(['request_date'=>today()->toDateString()]);
        }
        if($this->route('module')==='devis'){
            $client=$this->input('client_id')?DB::table('clients')->find($this->input('client_id')):null;
            $items=collect($this->input('items',[]))->map(function($item){
                $supplier=!empty($item['supplier_id'])?DB::table('suppliers')->find($item['supplier_id']):null;
                return [...$item,'supplier_name'=>$item['supplier_name']??$supplier?->name,'supplier_contact'=>$item['supplier_contact']??$supplier?->contact];
            })->all();
            $this->merge(['client_name'=>$this->input('client_name')?:$client?->name,'client_contact'=>$this->input('client_contact')?:$client?->contact,'client_type'=>$this->input('client_type')?:$client?->type?:'particulier','shipping_mode'=>$this->input('shipping_mode')?:'maritime','items'=>$items]);
        }
        if($this->route('module')==='commandes'){
            $legacy=['fonds_recus'=>'acompte_recu','achat_en_cours'=>'achat_lance'];
            if(isset($legacy[$this->input('status')])) $this->merge(['status'=>$legacy[$this->input('status')]]);
        }
        if($this->route('module')==='paiements'){
            $reason=$this->input('type');
            $legacyTypes=[
                'acompte_commande'=>'acompte',
                'solde_commande'=>'solde',
                'fournisseur_chine'=>'intermediaire',
                'fret_transport'=>'intermediaire',
                'frais_service'=>'intermediaire',
                'autre'=>'intermediaire',
                'acompte'=>'acompte',
                'solde'=>'solde',
                'intermediaire'=>'intermediaire',
                'remboursement'=>'remboursement',
            ];
            if(isset($legacyTypes[$reason])) $this->merge(['payment_reason'=>$reason,'type'=>$legacyTypes[$reason]]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $money = ['required', 'numeric', 'min:0', 'max:9999999999999999.99'];
        $optionalMoney = ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'];
        $photo = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        return match ($this->route('module')) {
            'clients' => ['name'=>['required','string','max:255'],'contact'=>['required','string','max:255'],'type'=>['required','in:revendeur,entrepreneur,particulier,hotel'],'address'=>['nullable','string','max:255'],'notes'=>['nullable','string'],'active'=>['boolean']],
            'fournisseurs' => ['name'=>['required','string','max:255'],'category'=>['nullable','string','max:255'],'moq'=>['nullable','integer','min:0'],'production_days'=>['nullable','integer','min:0'],'contact'=>['nullable','string','max:255'],'quality_rating'=>['required','integer','between:1,5'],'notes'=>['nullable','string'],'active'=>['boolean'],'products'=>['nullable','array'],'products.*.id'=>['nullable','integer'],'products.*.name'=>['required','string','max:255'],'products.*.specifications'=>['nullable','string'],'products.*.price'=>$optionalMoney,'products.*.local_delivery'=>$optionalMoney,'products.*.packaging'=>$optionalMoney,'products.*.cbm'=>['nullable','numeric','min:0'],'products.*.freight'=>$optionalMoney,'products.*.margin'=>$optionalMoney,'products.*.contact'=>['nullable','string','max:255'],'products.*.source_url'=>['nullable','url','max:2000'],'products.*.photo'=>$photo],
            'stock' => ['reference'=>['nullable','string','max:100',Rule::unique('inventory_products','reference')->ignore($this->route('id'))],'name'=>['required','string','max:255'],'slug'=>['nullable','string','max:255',Rule::unique('inventory_products','slug')->ignore($this->route('id'))],'photo'=>$photo,'gallery'=>['nullable','array','max:6'],'gallery.*'=>$photo,'quantity'=>['required','numeric','min:0'],'reserved_quantity'=>['nullable','numeric','min:0','lte:quantity'],'purchase_price'=>$money,'cbm'=>['nullable','numeric','min:0'],'freight'=>$optionalMoney,'sale_price'=>$money,'origin_order_ids'=>['nullable','array'],'origin_order_ids.*'=>['integer','distinct','exists:orders,id'],'alert_threshold'=>['nullable','numeric','min:0'],'is_published'=>['boolean'],'is_featured'=>['boolean'],'show_price'=>['boolean'],'category'=>['nullable','string','max:120'],'short_description'=>['nullable','string','max:320'],'catalog_description'=>['nullable','string','max:5000']],
            'catalogue' => ['slug'=>['nullable','string','max:255',Rule::unique('inventory_products','slug')->ignore($this->route('id'))],'gallery'=>['nullable','array','max:6'],'gallery.*'=>$photo,'is_published'=>['required','boolean'],'is_featured'=>['required','boolean'],'show_price'=>['required','boolean'],'category'=>['nullable','string','max:120'],'short_description'=>['nullable','string','max:320'],'catalog_description'=>['nullable','string','max:5000']],
            'demandes-devis' => ['client_name'=>['required','string','max:255'],'client_contact'=>['required','string','max:255'],'source'=>['required','in:facebook,bouche_a_oreille,autre'],'description'=>['required','string','max:5000'],'quantity'=>['required','integer','min:1'],'budget'=>$optionalMoney,'desired_deadline'=>['nullable','string','max:255'],'shipping_mode'=>['required','in:maritime,aerien,non_precise'],'status'=>['required','in:nouvelle_demande,en_cours_analyse,devis_envoye,sans_suite'],'request_date'=>['required','date'],'assigned_to'=>['nullable','exists:users,id'],'internal_note'=>['nullable','string']],
            'devis' => ['quote_request_id'=>['nullable','exists:quote_requests,id'],'client_id'=>['nullable','exists:clients,id'],'client_name'=>['required','string','max:255'],'client_contact'=>['required','string','max:255'],'client_type'=>['required','in:revendeur,entrepreneur,particulier,hotel'],'valid_until'=>['required','date','after_or_equal:today'],'shipping_mode'=>['required','in:aerien,maritime'],'shipping_delay'=>['nullable','string','max:255'],'bank_details'=>['nullable','string'],'payment_terms'=>['nullable','string'],'warranty'=>['nullable','string'],'notes'=>['nullable','string'],'status'=>['required','in:brouillon,envoye,negociation,accepte,refuse,sans_reponse,relance_1,relance_2'],'items'=>['required','array','min:1'],'items.*.id'=>['nullable','integer'],'items.*.name'=>['required','string','max:255'],'items.*.photo'=>$photo,'items.*.specifications'=>['nullable','string'],'items.*.quantity'=>['required','numeric','gt:0'],'items.*.supplier_id'=>['nullable','exists:suppliers,id'],'items.*.supplier_name'=>['nullable','required_without:items.*.supplier_id','string','max:255'],'items.*.supplier_contact'=>['nullable','string','max:255'],'items.*.source_url'=>['nullable','url','max:2000'],'items.*.supplier_price'=>$money,'items.*.china_delivery'=>$optionalMoney,'items.*.packaging'=>$optionalMoney,'items.*.weight'=>['nullable','numeric','min:0'],'items.*.cbm'=>['nullable','numeric','min:0'],'items.*.freight'=>$optionalMoney,'items.*.margin'=>$optionalMoney,'items.*.commission'=>$optionalMoney,'items.*.total'=>$money],
            'commandes' => ['quote_id'=>['nullable','exists:quotes,id'],'client_id'=>['nullable','required_without:new_client_name','exists:clients,id'],'new_client_name'=>['nullable','required_without:client_id','string','max:255'],'new_client_contact'=>['nullable','required_with:new_client_name','string','max:255'],'new_client_type'=>['nullable','required_with:new_client_name','in:revendeur,entrepreneur,particulier,hotel'],'new_client_address'=>['nullable','string','max:255'],'commission_enabled'=>['boolean'],'commission_rate'=>['nullable','numeric','between:0,100'],'deposit'=>$optionalMoney,'ordered_at'=>['required','date'],'shipping_mode'=>['nullable','in:aerien,maritime'],'status'=>['required','in:brouillon,demande_recue,attente_validation,confirmee,acompte_recu,achat_lance,achat_effectue'],'notes'=>['nullable','string'],'items'=>['required','array','min:1'],'items.*.id'=>['nullable','integer'],'items.*.quote_item_id'=>['nullable','exists:quote_items,id'],'items.*.name'=>['required','string','max:255'],'items.*.photo'=>$photo,'items.*.specifications'=>['nullable','string'],'items.*.quantity'=>['required','numeric','gt:0'],'items.*.supplier_id'=>['nullable','exists:suppliers,id'],'items.*.supplier_name'=>['nullable','string','max:255'],'items.*.supplier_contact'=>['nullable','string','max:255'],'items.*.source_url'=>['nullable','url','max:2000'],'items.*.supplier_price'=>$money,'items.*.china_delivery'=>$optionalMoney,'items.*.packaging'=>$optionalMoney,'items.*.weight'=>['nullable','numeric','min:0'],'items.*.cbm'=>['nullable','numeric','min:0'],'items.*.freight'=>$optionalMoney,'items.*.margin'=>$optionalMoney,'items.*.commission'=>$optionalMoney,'items.*.client_total'=>$money,'packages'=>['nullable','array'],'packages.*.reference'=>['required','string','max:100'],'packages.*.billing_unit'=>['required','in:kg,cbm'],'packages.*.weight_kg'=>['nullable','required_if:packages.*.billing_unit,kg','numeric','gt:0'],'packages.*.volume_cbm'=>['nullable','required_if:packages.*.billing_unit,cbm','numeric','gt:0'],'packages.*.notes'=>['nullable','string'],'packages.*.items'=>['required','array','min:1'],'packages.*.items.*.item_index'=>['required','integer','min:0'],'packages.*.items.*.quantity'=>['required','numeric','gt:0']],
            'paiements' => ['client_id'=>['nullable','required_without:new_client_name','exists:clients,id'],'new_client_name'=>['nullable','required_without:client_id','string','max:255'],'new_client_contact'=>['nullable','required_with:new_client_name','string','max:255'],'new_client_type'=>['nullable','required_with:new_client_name','in:revendeur,entrepreneur,particulier,hotel'],'new_client_address'=>['nullable','string','max:255'],'order_id'=>['nullable','exists:orders,id'],'invoice_id'=>['nullable','exists:invoices,id'],'paid_at'=>['required','date'],'amount'=>array_merge($money,['gt:0']),'allocated_amount'=>$optionalMoney,'method'=>['required','string','max:100'],'reference'=>['nullable','string','max:255',Rule::unique('client_payments','reference')->ignore($this->route('id'))],'payment_reason'=>['required','in:acompte_commande,solde_commande,fournisseur_chine,fret_transport,frais_service,autre,acompte,intermediaire,solde,remboursement'],'type'=>['required','in:acompte,intermediaire,solde,remboursement'],'payment_object'=>['required','string','max:255'],'notes'=>['nullable','required_if:payment_reason,autre','string']],
            'factures' => ['order_id'=>['required','exists:orders,id'],'type'=>['required','in:produits,frais'],'issued_at'=>['required','date'],'subtotal'=>$money,'paid_amount'=>$optionalMoney,'status'=>['required','in:brouillon,provisoire,finale,payee,partielle,annulee']],
            'achats' => ['supplier_id'=>['required','exists:suppliers,id'],'order_id'=>['required','exists:orders,id'],'paid_at'=>['required','date'],'amount'=>array_merge($money,['gt:0']),'method'=>['required','in:WeChat,Alipay,banque'],'reference'=>['nullable','string','max:255'],'proof'=>$photo,'proof_url'=>['nullable','url','max:2000'],'status'=>['required','in:paye,partiel,en_attente'],'notes'=>['nullable','string']],
            'logistique' => ['order_id'=>['required','exists:orders,id'],'tracking'=>['nullable','string','max:255'],'forwarder'=>['nullable','string','max:255'],'container_reference'=>['nullable','string','max:255'],'china_departure_at'=>['nullable','date'],'china_warehouse_at'=>['nullable','date'],'expected_madagascar_at'=>['nullable','date'],'arrived_madagascar_at'=>['nullable','date'],'cbm'=>['nullable','numeric','min:0'],'package_count'=>['nullable','integer','min:0'],'carton_count'=>['nullable','integer','min:0'],'cost'=>$optionalMoney,'status'=>['required','in:commande_lancee,en_attente,arrive_en_chine,expedie,en_transit,arrive_madagascar,remis_client']],
            'ventes' => ['inventory_product_id'=>['required','exists:inventory_products,id'],'sold_at'=>['required','date'],'quantity'=>['required','numeric','gt:0'],'unit_price'=>$money,'paid_amount'=>$optionalMoney,'payment_method'=>['nullable','string','max:100'],'buyer_name'=>['nullable','string','max:255'],'buyer_contact'=>['nullable','string','max:255'],'notes'=>['nullable','string']],
            'depenses' => ['category'=>['required','in:achat,logistique,marketing,transport,loyer_depot_chine,loyer_depot_madagascar,loyer_bureau,services_publics,salaire,IRSA,autre'],'amount'=>array_merge($money,['gt:0']),'spent_at'=>['required','date'],'type'=>['required','in:business,personnel'],'description'=>['required','string'],'order_id'=>['nullable','exists:orders,id'],'status'=>['required','string','max:50']],
            'employes' => ['name'=>['required','string','max:255'],'position'=>['required','string','max:255'],'monthly_salary'=>array_merge($money,['gt:0']),'irsa_mode'=>['required','in:pourcentage,fixe'],'irsa_value'=>$optionalMoney,'active'=>['boolean'],'left_at'=>['nullable','date'],'departure_reason'=>['nullable','string']],
            'salaires' => ['employee_id'=>['required','exists:employees,id'],'month'=>['required','date'],'gross_salary'=>array_merge($money,['gt:0']),'irsa_mode'=>['required','in:pourcentage,fixe'],'irsa_value'=>$optionalMoney,'paid_at'=>['nullable','date'],'status'=>['required','in:a_payer,paye']],
            'fiscalite' => ['type'=>['required','in:IRSA,impot_synthetique'],'period'=>['required','string','max:50'],'fiscal_year'=>['required','integer','between:2020,2100'],'calculation_base'=>['required','in:ca_facture,ca_encaisse,salaires_bruts'],'base_amount'=>$money,'rate'=>['required','numeric','between:0,100'],'declared_amount'=>$optionalMoney,'due_at'=>['nullable','date'],'status'=>['required','string','max:50']],
            default => abort(404),
        };
    }

    public function attributes(): array
    {
        return ['client_id'=>'client','supplier_id'=>'fournisseur','inventory_product_id'=>'produit','product_name'=>'nom du produit','ordered_at'=>'date de commande','paid_at'=>'date de paiement','valid_until'=>'date de validité'];
    }
}
