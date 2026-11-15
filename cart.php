<?php
declare(strict_types=1);

require __DIR__ . '/shop.php';

$errors = [];
$notice = '';
$legacyBuyer = is_array($_SESSION['invoice_buyer'] ?? null) ? $_SESSION['invoice_buyer'] : [];
$invoicePeople = $_SESSION['invoice_people'] ?? [
    'customer' => $legacyBuyer['name'] ?? '',
    'seller' => '',
    'delivery' => '',
    'phone' => $legacyBuyer['phone'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        $errors[] = 'Your session could not be verified. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'buyer') {
            $invoicePeople = [
                'customer' => is_string($_POST['customer_name'] ?? null) ? trim($_POST['customer_name']) : '',
                'seller' => is_string($_POST['seller_name'] ?? null) ? trim($_POST['seller_name']) : '',
                'delivery' => is_string($_POST['delivery_name'] ?? null) ? trim($_POST['delivery_name']) : '',
                'phone' => is_string($_POST['buyer_phone'] ?? null) ? trim($_POST['buyer_phone']) : '',
            ];
            foreach (['customer' => 'អតិថិជន', 'seller' => 'អ្នកលក់', 'delivery' => 'អ្នកដឹកជញ្ជូន'] as $field => $label) {
                if ($invoicePeople[$field] === '' || mb_strlen($invoicePeople[$field]) > 100) {
                    $errors[] = 'សូមបញ្ចូលឈ្មោះ' . $label . ' (មិនលើស ១០០ តួអក្សរ)។';
                }
            }
            if ($invoicePeople['phone'] !== ''
                && (!preg_match('/^[0-9+().\-\s]{7,24}$/', $invoicePeople['phone']) || !preg_match('/[0-9]/', $invoicePeople['phone']))) {
                $errors[] = 'សូមបញ្ចូលលេខទូរសព្ទឱ្យបានត្រឹមត្រូវ។';
            }
            if (!$errors) {
                $_SESSION['invoice_people'] = $invoicePeople;
                $_SESSION['invoice_buyer'] = [
                    'name' => $invoicePeople['customer'],
                    'phone' => $invoicePeople['phone'],
                ];
                if (($_POST['submit_action'] ?? '') === 'print') {
                    $_SESSION['print_invoice'] = true;
                } else {
                    unset($_SESSION['print_invoice']);
                }
                header('Location: cart.php');
                exit;
            }
        } elseif ($action === 'add') {
            $productId = $_POST['product_id'] ?? '';
            if (is_string($productId) && isset($products[$productId])) {
                $_SESSION['cart'][$productId] = min(99, (int) ($_SESSION['cart'][$productId] ?? 0) + 1);
                unset($_SESSION['invoice_ref'], $_SESSION['invoice_date']);
                header('Location: cart.php?added=1');
                exit;
            }
            $errors[] = 'That menu item is not available.';
        } elseif ($action === 'update') {
            $quantities = $_POST['quantity'] ?? [];
            if (!is_array($quantities)) {
                $errors[] = 'Please enter valid quantities.';
            } else {
                foreach ($_SESSION['cart'] as $productId => $currentQuantity) {
                    if (!isset($products[$productId])) {
                        unset($_SESSION['cart'][$productId]);
                        continue;
                    }
                    $quantityValue = $quantities[$productId] ?? '0';
                    $quantity = is_string($quantityValue) ? filter_var($quantityValue, FILTER_VALIDATE_INT) : false;
                    if ($quantity === false || $quantity < 0 || $quantity > 99) {
                        $errors[] = 'Enter a quantity from 0 to 99 for each item.';
                        break;
                    }
                    if ($quantity === 0) {
                        unset($_SESSION['cart'][$productId]);
                    } else {
                        $_SESSION['cart'][$productId] = $quantity;
                    }
                }
            }
            if (!$errors) {
                if (!$_SESSION['cart']) {
                    unset($_SESSION['invoice_buyer'], $_SESSION['invoice_people']);
                }
                unset($_SESSION['invoice_ref'], $_SESSION['invoice_date']);
                header('Location: cart.php');
                exit;
            }
        } elseif ($action === 'remove') {
            $productId = $_POST['product_id'] ?? '';
            if (is_string($productId)) {
                unset($_SESSION['cart'][$productId]);
            }
            if (!$_SESSION['cart']) {
                unset($_SESSION['invoice_buyer'], $_SESSION['invoice_people']);
            }
            unset($_SESSION['invoice_ref'], $_SESSION['invoice_date']);
            header('Location: cart.php');
            exit;
        } else {
            $errors[] = 'We could not process that request.';
        }
    }
}

$cartCount = 0;
$cartTotal = 0.0;
foreach ($_SESSION['cart'] as $productId => $quantity) {
    if (!isset($products[$productId])) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }
    $quantity = max(0, min(99, (int) $quantity));
    $cartCount += $quantity;
    $cartTotal += $products[$productId]['price'] * $quantity;
}

if (isset($_GET['added'])) {
    $notice = 'Added to your order.';
}
if ($cartCount > 0 && !isset($_SESSION['invoice_ref'])) {
    $_SESSION['invoice_ref'] = 'INV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $_SESSION['invoice_date'] = date('d/m/Y H:i');
}
$invoiceRef = $_SESSION['invoice_ref'] ?? '';
$receiptDate = $_SESSION['invoice_date'] ?? '';
$autoPrint = $cartCount > 0 && !empty($_SESSION['print_invoice']);
unset($_SESSION['print_invoice']);
?>
<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Oho Cafe invoice and printable order receipt.">
    <title>វិក្កយបត្រ | Oho Cafe</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="invoice-page">
<div class="invoice-actions no-print">
    <a href="index.php">← ត្រឡប់ទៅមុខម្ហូប</a>
    <?php if ($cartCount > 0): ?>
        <button type="submit" form="update-order">កែចំនួន</button>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="notice notice-error no-print" role="alert">
        <?php foreach ($errors as $error): ?><p><?= escape($error) ?></p><?php endforeach; ?>
    </div>
<?php elseif ($notice): ?>
    <div class="notice notice-success no-print" role="status"><?= escape($notice) ?></div>
<?php endif; ?>

<?php if ($cartCount > 0): ?>
    <form method="post" class="buyer-form no-print">
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="buyer">
        <div class="buyer-field">
            <label for="customer-name">ឈ្មោះអតិថិជន</label>
            <input id="customer-name" name="customer_name" type="text" maxlength="100" required value="<?= escape($invoicePeople['customer']) ?>">
        </div>
        <div class="buyer-field">
            <label for="seller-name">ឈ្មោះអ្នកលក់</label>
            <input id="seller-name" name="seller_name" type="text" maxlength="100" required value="<?= escape($invoicePeople['seller']) ?>">
        </div>
        <div class="buyer-field">
            <label for="delivery-name">ឈ្មោះអ្នកដឹកជញ្ជូន</label>
            <input id="delivery-name" name="delivery_name" type="text" maxlength="100" required value="<?= escape($invoicePeople['delivery']) ?>">
        </div>
        <div class="buyer-field">
            <label for="buyer-phone">លេខទូរសព្ទអតិថិជន</label>
            <input id="buyer-phone" name="buyer_phone" type="tel" maxlength="24" value="<?= escape($invoicePeople['phone']) ?>">
        </div>
        <div class="buyer-form-actions">
            <button type="submit" name="submit_action" value="save">រក្សាទុកព័ត៌មាន</button>
            <button type="submit" name="submit_action" value="print">រក្សាទុក និងបោះពុម្ព</button>
        </div>
    </form>
    <main class="invoice">
        <header class="invoice-header">
            <div class="invoice-logo" aria-hidden="true">🪷</div>
            <div class="invoice-company">
                <h1>ដេប៉ូលក់អង្ករ ហេង លាប</h1>
                <p>វិក្កយបត្រ</p>
            </div>
            <div class="invoice-reference">
                <strong>លេខវិក្កយបត្រ</strong>
                <span><?= escape($invoiceRef) ?></span>
                <span><?= escape($receiptDate) ?></span>
            </div>
        </header>

        <section class="invoice-address">
            <div class="invoice-customer">
                <p>អាស័យដ្ឋាន: ផ្ទះលេខ 106 ផ្លូវលេខ 77BT</p>
                <p>សង្កាត់បឹងទំពន់ ខណ្ឌមានជ័យ</p>
                <p>លក់ជូនឈ្មោះ
                    <?php if ($invoicePeople['customer'] !== ''): ?>
                        <strong><?= escape($invoicePeople['customer']) ?></strong>
                    <?php else: ?>
                        <span class="fill-line"></span>
                    <?php endif; ?>
                </p>
                <p>លេខទូរសព្ទ័
                    <?php if ($invoicePeople['phone'] !== ''): ?>
                        <strong><?= escape($invoicePeople['phone']) ?></strong>
                    <?php else: ?>
                        <span class="fill-line"></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="invoice-phone">
                <p>(076) 6 668 668 (ប្រព័ន្ធ១២)</p>
                <p>(096) 6 668 668 (ប្រព័ន្ធ១០)</p>
                <p>(097) 6 668 668 (ប្រព័ន្ធ៩៧)</p>
            </div>
        </section>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="invoice-number">ល.រ</th>
                    <th>ប្រភេទទំនិញ</th>
                    <th class="invoice-qty-column">ចំនួន</th>
                    <th class="invoice-price-column">តម្លៃរាយ</th>
                    <th class="invoice-total-column">តម្លៃសរុប</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNumber = 0; ?>
                <?php foreach ($_SESSION['cart'] as $productId => $quantity): ?>
                    <?php if (isset($products[$productId])): ?>
                        <?php $rowNumber++; ?>
                        <tr>
                            <td><?= $rowNumber ?></td>
                            <td class="invoice-product"><?= escape($products[$productId]['name']) ?></td>
                            <td>
                                <input class="invoice-quantity no-print" type="number" name="quantity[<?= escape($productId) ?>]" form="update-order" min="0" max="99" value="<?= (int) $quantity ?>" aria-label="ចំនួន <?= escape($products[$productId]['name']) ?>">
                                <span class="print-quantity"><?= (int) $quantity ?></span>
                            </td>
                            <td><?= money($products[$productId]['price']) ?></td>
                            <td><?= money($products[$productId]['price'] * (int) $quantity) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php for ($blankRow = $rowNumber + 1; $blankRow <= 13; $blankRow++): ?>
                    <tr class="blank-invoice-row">
                        <td><?= $blankRow ?></td><td></td><td></td><td></td><td></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <section class="invoice-summary">
            <div class="payment-terms">
                <h2>បញ្ជាក់</h2>
                <p>- ការទូទាត់ជាសាច់ប្រាក់</p>
                <p>- ការទូទាត់ប្រាក់រយៈពេល ០៧ថ្ងៃ បន្ទាប់ពីចេញវិក្កយបត្រ</p>
                <p>- អតិថិជនត្រូវទូទាត់តាមកាលកំណត់ បើមិនដូច្នេះទេនឹងពិន័យ ៣% ក្នុង ១ខែ លើទឹកប្រាក់នៅសល់។</p>
            </div>
            <div class="invoice-totals">
                <p><span>ប្រាក់សរុប</span><strong><?= money($cartTotal) ?></strong></p>
                <p><span>ប្រាក់កក់</span><strong><?= money(0) ?></strong></p>
                <p class="balance-due"><span>ប្រាក់នៅខ្វះ</span><strong><?= money($cartTotal) ?></strong></p>
            </div>
        </section>

        <footer class="invoice-signatures">
            <div>
                <h2>ហត្ថលេខា និងឈ្មោះអ្នកទិញ</h2>
                <p class="signature-name"><?= escape($invoicePeople['customer']) ?></p>
                <p>ថ្ងៃទី ............ ខែ .......... ឆ្នាំ 20............</p>
            </div>
            <div>
                <h2>ហត្ថលេខា និងឈ្មោះអ្នកដឹក</h2>
                <p class="signature-name"><?= escape($invoicePeople['delivery']) ?></p>
                <p>ថ្ងៃទី ............ ខែ .......... ឆ្នាំ 20............</p>
            </div>
            <div>
                <h2>ហត្ថលេខា និងឈ្មោះអ្នកលក់</h2>
                <p class="signature-name"><?= escape($invoicePeople['seller']) ?></p>
                <p>ថ្ងៃទី ............ ខែ .......... ឆ្នាំ 20............</p>
            </div>
        </footer>
    </main>
    <form method="post" id="update-order" class="visually-hidden no-print">
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="update">
    </form>
<?php else: ?>
    <main class="empty-invoice">
        <h1>មិនទាន់មានទំនិញ</h1>
        <p>សូមជ្រើសរើសមុខម្ហូបជាមុនសិន។</p>
        <a href="index.php">ត្រឡប់ទៅមុខម្ហូប</a>
    </main>
<?php endif; ?>
<?php if ($autoPrint): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
