<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = pos_require_setup_complete();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pos_csrf_verify();
        if (($_POST['action'] ?? '') !== 'save') {
            throw new RuntimeException('Unknown action.');
        }

        $cartJson = $_POST['cart_json'] ?? '';
        $lines = json_decode($cartJson, true);
        if (!is_array($lines) || !$lines) {
            throw new RuntimeException('Cart is empty.');
        }

        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $paymentMethod = (string) ($_POST['payment_method'] ?? 'cash');
        if (!array_key_exists($paymentMethod, pos_payment_methods())) {
            $paymentMethod = 'cash';
        }

        $invoiceDiscount = max(0, (float) ($_POST['invoice_discount'] ?? 0));
        $paidAmount = max(0, (float) ($_POST['paid_amount'] ?? 0));
        if ($paymentMethod === 'credit') {
            $paidAmount = 0;
        }

        $header = [
            'customer_id' => $customerId > 0 ? $customerId : null,
            'discount' => $invoiceDiscount,
            'payment_method' => $paymentMethod,
            'paid_amount' => $paidAmount,
            'warranty_period_days' => max(0, (int) ($_POST['warranty_days'] ?? 0)),
            'warranty_note' => trim($_POST['warranty_note'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        if ($paymentMethod !== 'credit' && $paymentMethod !== 'partial' && $paidAmount <= 0) {
            $subtotal = 0.0;
            foreach ($lines as $line) {
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $lineDiscount = max(0, (float) ($line['discount'] ?? 0));
                $subtotal += max(0, ($unitPrice * $qty) - $lineDiscount);
            }
            $header['paid_amount'] = max(0, $subtotal - $invoiceDiscount);
        }

        $invoiceId = pos_create_invoice($header, $lines, (int) $user['id']);
        pos_flash('success', 'Invoice saved.');
        header('Location: ' . pos_panel_url('invoice-view.php?id=' . $invoiceId));
        exit;
    } catch (Throwable $e) {
        pos_flash('error', $e->getMessage());
        header('Location: ' . pos_panel_url('sale-new.php'));
        exit;
    }
}

$paymentMethods = pos_payment_methods();
$searchApi = pos_panel_url('api/search.php');

pos_render_header('New Sale', 'sale');
?>
<div class="admin-grid-2 pos-sale-layout">
    <section class="admin-panel admin-panel--sales">
        <h2>Products</h2>
        <div class="admin-field">
            <label for="pos-product-search">Search products</label>
            <input type="search" id="pos-product-search" placeholder="Name, brand, category…" autocomplete="off">
        </div>
        <div id="pos-product-results" class="pos-search-results"></div>
        <h3>Cart</h3>
        <div class="admin-table-wrap">
            <table class="admin-table" id="pos-cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Disc.</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="pos-cart-body">
                    <tr id="pos-cart-empty"><td colspan="6" class="admin-empty">No items in cart.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-panel">
        <h2>Checkout</h2>
        <form method="post" id="pos-sale-form" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pos_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="cart_json" id="cart_json" value="[]">
            <input type="hidden" name="customer_id" id="customer_id" value="">

            <div class="admin-field">
                <label for="pos-customer-search">Customer <small>(optional — walk-in)</small></label>
                <input type="search" id="pos-customer-search" placeholder="Search by name or phone…" autocomplete="off">
            </div>
            <div id="pos-customer-results" class="pos-search-results"></div>
            <p id="pos-customer-selected" class="admin-hint" hidden></p>
            <button type="button" class="btn btn-ghost btn-sm" id="pos-customer-clear" hidden>Clear customer</button>

            <div class="admin-field">
                <label for="payment_method">Payment method</label>
                <select id="payment_method" name="payment_method">
                    <?php foreach ($paymentMethods as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field" id="pos-paid-wrap" hidden>
                <label for="paid_amount">Amount paid now</label>
                <input type="number" id="paid_amount" name="paid_amount" min="0" step="0.01" value="0">
            </div>

            <div class="admin-field-row">
                <div class="admin-field">
                    <label for="invoice_discount">Invoice discount (Rs.)</label>
                    <input type="number" id="invoice_discount" name="invoice_discount" min="0" step="0.01" value="0">
                </div>
                <div class="admin-field">
                    <label for="warranty_days">Warranty (days)</label>
                    <input type="number" id="warranty_days" name="warranty_days" min="0" step="1" value="0">
                </div>
            </div>

            <div class="admin-field">
                <label for="warranty_note">Warranty note</label>
                <input type="text" id="warranty_note" name="warranty_note">
            </div>

            <div class="admin-field">
                <label for="notes">Invoice notes</label>
                <textarea id="notes" name="notes" rows="2"></textarea>
            </div>

            <div class="pos-sale-totals admin-panel">
                <div><span>Subtotal</span><strong id="pos-subtotal">Rs. 0.00</strong></div>
                <div><span>Discount</span><strong id="pos-discount-display">Rs. 0.00</strong></div>
                <div class="pos-sale-totals__grand"><span>Total</span><strong id="pos-grand-total">Rs. 0.00</strong></div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="pos-save-sale">Save invoice</button>
        </form>
    </section>
</div>
<script>
window.POS_SALE = <?php echo json_encode(['searchApi' => $searchApi], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo pos_panel_url('assets/sale.js'); ?>"></script>
<?php
pos_render_footer();
