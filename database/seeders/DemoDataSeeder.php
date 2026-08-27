<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /** Add 20 realistic demo records to every business section. */
    public function run(): void
    {
        $manager = User::where('email', env('ADMIN_EMAIL', 'manager@madina-import.mg'))->firstOrFail();

        DB::transaction(function () use ($manager): void {
            $now = now();
            $productNames = ['Table pliante', 'Caméra solaire', 'Fauteuil de bureau', 'Lavabo moderne', 'Lampe LED', 'Étagère métallique', 'Robinet cuisine', 'Mini projecteur', 'Chaise scandinave', 'Ventilateur mural'];

            for ($i = 1; $i <= 20; $i++) {
                $suffix = str_pad((string) ($i + 4), 3, '0', STR_PAD_LEFT);
                DB::table('clients')->updateOrInsert(['number' => "CLI-DEMO-2026-$suffix"], [
                    'name' => "Client Démo $suffix", 'contact' => '+261 34 70 '.str_pad((string) $i, 3, '0', STR_PAD_LEFT).' 00',
                    'type' => ['particulier', 'revendeur', 'entrepreneur', 'hotel'][($i - 1) % 4], 'address' => ['Antananarivo', 'Toamasina', 'Mahajanga', 'Antsirabe'][($i - 1) % 4],
                    'notes' => 'Donnée de démonstration', 'active' => true, 'credit_balance' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);

                $supplierName = "Fournisseur Démo $suffix";
                DB::table('suppliers')->updateOrInsert(['name' => $supplierName], [
                    'category' => ['Mobilier', 'Électronique', 'Sanitaire', 'Éclairage'][($i - 1) % 4], 'moq' => 5 + $i,
                    'production_days' => 10 + ($i % 15), 'contact' => "WeChat: demo-$suffix", 'quality_rating' => 3 + ($i % 3),
                    'notes' => 'Fournisseur de démonstration', 'active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $clients = DB::table('clients')->where('number', 'like', 'CLI-DEMO-2026-%')->orderBy('number')->get();
            $suppliers = DB::table('suppliers')->where('name', 'like', 'Fournisseur Démo %')->orderBy('name')->get();

            for ($i = 1; $i <= 20; $i++) {
                $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $client = $clients[($i + 3) % $clients->count()];
                $supplier = $suppliers[$i - 1];
                $product = $productNames[($i - 1) % count($productNames)]." Démo $suffix";
                $quantity = 2 + ($i % 9);
                $unitCost = 55000 + ($i * 7500);
                $margin = 30000 + ($i * 2500);
                $freight = 25000 + ($i * 3000);
                $lineTotal = ($unitCost * $quantity) + $freight + $margin;
                $date = $now->copy()->subDays(20 - $i)->toDateString();

                DB::table('supplier_products')->updateOrInsert(['supplier_id' => $supplier->id, 'name' => $product], [
                    'specifications' => 'Modèle de démonstration, finition standard', 'price' => $unitCost, 'local_delivery' => 10000,
                    'packaging' => 5000, 'cbm' => 0.05 + ($i / 100), 'freight' => $freight, 'margin' => $margin,
                    'contact' => $supplier->contact, 'photo_path' => null, 'source_url' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);

                $quoteNumber = "DV-DEMO-2026-$suffix";
                DB::table('quotes')->updateOrInsert(['number' => $quoteNumber], [
                    'client_id' => $client->id, 'client_name' => $client->name, 'contact' => $client->contact, 'client_type' => $client->type,
                    'sent_at' => $date, 'valid_until' => $now->copy()->addDays(10 + $i)->toDateString(), 'shipping_mode' => $i % 3 ? 'maritime' : 'aerien',
                    'shipping_delay' => $i % 3 ? '35 à 50 jours' : '7 à 12 jours', 'bank_details' => null, 'payment_terms' => '50 % à la commande, solde avant livraison',
                    'warranty' => 'Garantie selon le fabricant', 'status' => ['envoye', 'negociation', 'accepte', 'brouillon'][($i - 1) % 4],
                    'supplier_estimate' => $unitCost * $quantity, 'logistics_estimate' => $freight, 'margin' => $margin, 'total' => $lineTotal,
                    'currency' => 'MGA', 'notes' => 'Devis de démonstration', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $quoteId = DB::table('quotes')->where('number', $quoteNumber)->value('id');
                DB::table('quote_items')->updateOrInsert(['quote_id' => $quoteId, 'name' => $product], [
                    'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'supplier_contact' => $supplier->contact,
                    'specifications' => 'Modèle de démonstration, finition standard', 'quantity' => $quantity, 'source_url' => null, 'photo_path' => null,
                    'supplier_price' => $unitCost, 'china_delivery' => 10000, 'packaging' => 5000, 'estimated_weight' => 8 + $i,
                    'estimated_cbm' => 0.05 + ($i / 100), 'estimated_freight' => $freight, 'margin' => $margin, 'commission' => 0,
                    'total' => $lineTotal, 'created_at' => $now, 'updated_at' => $now,
                ]);

                $orderNumber = "MI-DEMO-2026-$suffix";
                $deposit = round($lineTotal * (($i % 3) + 1) / 4);
                DB::table('orders')->updateOrInsert(['number' => $orderNumber], [
                    'client_id' => $client->id, 'quote_id' => $quoteId, 'manager_id' => $manager->id, 'origin' => 'devis', 'ordered_at' => $date,
                    'shipping_mode' => $i % 3 ? 'maritime' : 'aerien', 'cbm' => 0.05 + ($i / 100), 'freight' => $freight,
                    'supplier_total' => $unitCost * $quantity, 'commission_enabled' => false, 'commission_base' => 0, 'commission_rate' => 8,
                    'commission_amount' => 0, 'margin' => $margin, 'client_total' => $lineTotal, 'deposit' => $deposit,
                    'balance_due' => $lineTotal - $deposit, 'status' => ['demande_recue', 'confirmee', 'acompte_recu', 'achat_lance', 'achat_effectue'][($i - 1) % 5],
                    'notes' => 'Commande de démonstration', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $orderId = DB::table('orders')->where('number', $orderNumber)->value('id');
                DB::table('order_items')->updateOrInsert(['order_id' => $orderId, 'name' => $product], [
                    'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'supplier_contact' => $supplier->contact,
                    'specifications' => 'Modèle de démonstration, finition standard', 'quantity' => $quantity, 'source_url' => null, 'photo_path' => null,
                    'supplier_price' => $unitCost, 'china_delivery' => 10000, 'packaging' => 5000, 'weight' => 8 + $i,
                    'cbm' => 0.05 + ($i / 100), 'freight' => $freight, 'margin' => $margin, 'commission' => 0,
                    'client_total' => $lineTotal, 'status' => 'achat_lance', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $orderItemId = DB::table('order_items')->where('order_id', $orderId)->where('name', $product)->value('id');

                $packageId = DB::table('order_packages')->updateOrInsert(['order_id' => $orderId, 'reference' => "COL-DEMO-$suffix"], [
                    'billing_unit' => 'cbm', 'weight_kg' => 8 + $i, 'volume_cbm' => 0.05 + ($i / 100), 'notes' => 'Colis démo', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $packageId = DB::table('order_packages')->where('order_id', $orderId)->where('reference', "COL-DEMO-$suffix")->value('id');
                DB::table('order_package_items')->updateOrInsert(['order_package_id' => $packageId, 'order_item_id' => $orderItemId], ['quantity' => $quantity, 'created_at' => $now, 'updated_at' => $now]);

                $invoiceNumber = "FA-DEMO-2026-$suffix";
                DB::table('invoices')->updateOrInsert(['number' => $invoiceNumber], [
                    'order_id' => $orderId, 'client_id' => $client->id, 'type' => 'produits', 'status' => $deposit ? 'partielle' : 'brouillon',
                    'issued_at' => $date, 'subtotal' => $lineTotal, 'paid_amount' => $deposit, 'balance_due' => $lineTotal - $deposit,
                    'lines' => json_encode([['label' => $product, 'quantity' => $quantity, 'unit_price' => round($lineTotal / $quantity, 2), 'amount' => $lineTotal]]),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $invoiceId = DB::table('invoices')->where('number', $invoiceNumber)->value('id');

                DB::table('client_payments')->updateOrInsert(['reference' => "PAY-DEMO-$suffix"], [
                    'client_id' => $client->id, 'order_id' => $orderId, 'invoice_id' => $invoiceId, 'paid_at' => $date,
                    'amount' => $deposit, 'allocated_amount' => $deposit, 'method' => ['MVola', 'Orange Money', 'Virement bancaire'][($i - 1) % 3],
                    'type' => 'acompte_commande', 'status' => 'valide', 'notes' => 'Paiement de démonstration', 'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('supplier_payments')->updateOrInsert(['reference' => "ACH-DEMO-$suffix"], [
                    'supplier_id' => $supplier->id, 'paid_at' => $date, 'amount' => $unitCost * $quantity, 'method' => ['WeChat', 'Alipay', 'banque'][($i - 1) % 3],
                    'proof_path' => null, 'proof_url' => null, 'status' => 'paye', 'notes' => 'Achat de démonstration', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $supplierPaymentId = DB::table('supplier_payments')->where('reference', "ACH-DEMO-$suffix")->value('id');
                DB::table('supplier_payment_allocations')->updateOrInsert(['supplier_payment_id' => $supplierPaymentId, 'order_id' => $orderId], ['amount' => $unitCost * $quantity]);

                DB::table('shipments')->updateOrInsert(['order_id' => $orderId, 'tracking' => "TRACK-DEMO-$suffix"], [
                    'supplier_sent_at' => $date, 'china_warehouse_at' => $date, 'weight' => 8 + $i, 'cbm' => 0.05 + ($i / 100),
                    'cost' => $freight, 'mode' => $i % 3 ? 'maritime' : 'aerien', 'forwarder' => 'Madina Cargo Démo',
                    'china_departure_at' => $date, 'expected_madagascar_at' => $now->copy()->addDays(10 + $i)->toDateString(),
                    'arrived_madagascar_at' => null, 'delivered_at' => null, 'status' => 'en_transit', 'created_at' => $now, 'updated_at' => $now,
                ]);

                $reference = "PRD-DEMO-$suffix";
                $stockQuantity = 10 + $i;
                DB::table('inventory_products')->updateOrInsert(['reference' => $reference], [
                    'name' => $product, 'photo_path' => null, 'quantity' => $stockQuantity, 'purchase_price' => $unitCost,
                    'sale_price' => round($lineTotal / $quantity, 2), 'stock_value' => $stockQuantity * $unitCost,
                    'entered_at' => $date, 'exited_at' => null, 'alert_threshold' => 5, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $inventoryId = DB::table('inventory_products')->where('reference', $reference)->value('id');
                DB::table('stock_movements')->updateOrInsert(['inventory_product_id' => $inventoryId, 'type' => 'entree', 'moved_at' => $now->copy()->subDays(20 - $i)], [
                    'quantity' => $stockQuantity, 'before_quantity' => 0, 'after_quantity' => $stockQuantity, 'notes' => 'Stock initial démo', 'user_id' => $manager->id,
                ]);
                DB::table('monthly_inventories')->updateOrInsert(['inventory_product_id' => $inventoryId, 'month' => $now->copy()->startOfMonth()->toDateString()], [
                    'recorded_quantity' => $stockQuantity, 'counted_quantity' => $stockQuantity, 'difference' => 0,
                    'validated_quantity' => $stockQuantity, 'validated_at' => $date, 'notes' => 'Inventaire démo', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $saleQuantity = 1 + ($i % 3);
                $unitPrice = round($lineTotal / $quantity, 2);
                DB::table('local_sales')->updateOrInsert(['inventory_product_id' => $inventoryId, 'sold_at' => $date], [
                    'quantity' => $saleQuantity, 'unit_price' => $unitPrice, 'total' => $saleQuantity * $unitPrice,
                    'paid_amount' => $saleQuantity * $unitPrice, 'balance_due' => 0, 'payment_method' => 'Espèces', 'status' => 'paye',
                    'buyer_name' => "Acheteur Démo $suffix", 'buyer_contact' => '+261 32 60 000 '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'notes' => 'Vente de démonstration', 'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('expenses')->updateOrInsert(['source_type' => 'demo', 'source_id' => $i], [
                    'category' => ['achat', 'logistique', 'marketing', 'transport', 'loyer_bureau'][($i - 1) % 5],
                    'amount' => 50000 + ($i * 10000), 'spent_at' => $date, 'type' => 'business', 'description' => "Dépense de démonstration $suffix",
                    'order_id' => $i % 2 ? $orderId : null, 'status' => 'paye', 'created_at' => $now, 'updated_at' => $now,
                ]);

                $employeeName = "Employé Démo $suffix";
                DB::table('employees')->updateOrInsert(['name' => $employeeName], [
                    'position' => ['Commercial', 'Logisticien', 'Assistant administratif', 'Magasinier'][($i - 1) % 4],
                    'monthly_salary' => 700000 + ($i * 25000), 'irsa_mode' => 'pourcentage', 'irsa_value' => 5,
                    'active' => true, 'left_at' => null, 'departure_reason' => null, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $employeeId = DB::table('employees')->where('name', $employeeName)->value('id');
                $gross = 700000 + ($i * 25000);
                DB::table('salaries')->updateOrInsert(['employee_id' => $employeeId, 'month' => $now->copy()->startOfMonth()->toDateString()], [
                    'gross_salary' => $gross, 'irsa_mode' => 'pourcentage', 'irsa_rate' => 5, 'irsa_amount' => $gross * .05,
                    'net_salary' => $gross * .95, 'paid_at' => $date, 'status' => 'paye', 'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('tax_records')->updateOrInsert(['type' => $i % 2 ? 'IRSA' : 'impot_synthetique', 'period' => "DEMO-$suffix"], [
                    'fiscal_year' => 2026, 'calculation_base' => $i % 2 ? 'salaires_bruts' : 'ca_encaisse', 'base_amount' => $gross,
                    'rate' => 5, 'calculated_amount' => $gross * .05, 'declared_amount' => null, 'due_at' => $now->copy()->addMonth()->toDateString(),
                    'declared_at' => null, 'paid_at' => null, 'status' => 'estimation', 'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('audit_logs')->updateOrInsert(
                    ['event' => 'demo.donnee_creee', 'auditable_type' => 'orders', 'auditable_id' => $orderId],
                    ['user_id' => $manager->id, 'old_values' => null, 'new_values' => json_encode(['number' => $orderNumber]), 'ip_address' => '127.0.0.1', 'created_at' => $now->copy()->addSeconds($i)]
                );
            }
        });
    }
}
