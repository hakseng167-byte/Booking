<?php
declare(strict_types=1);

require __DIR__ . '/shop.php';

$categories = ['All', 'Coffee', 'Cold drinks', 'Bakery', 'Hot drinks', 'Rice', 'Burgers', 'Cakes', 'Juice'];
$category = $_GET['category'] ?? 'All';
$category = is_string($category) && in_array($category, $categories, true) ? $category : 'All';
$search = $_GET['q'] ?? '';
$search = is_string($search) ? trim($search) : '';
$returnUrl = 'index.php?category=' . rawurlencode($category) . '&q=' . rawurlencode($search);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        $errors[] = 'Your session could not be verified. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $productId = $_POST['product_id'] ?? '';

        if (!is_string($productId) || !isset($products[$productId])) {
            $errors[] = 'That menu item is not available.';
        } elseif ($action === 'add') {
            $_SESSION['cart'][$productId] = min(99, (int) ($_SESSION['cart'][$productId] ?? 0) + 1);
            unset($_SESSION['invoice_ref'], $_SESSION['invoice_date']);
            header('Location: ' . $returnUrl . '#products');
            exit;
        } elseif ($action === 'adjust') {
            $change = filter_var($_POST['change'] ?? null, FILTER_VALIDATE_INT);
            if ($change !== 1 && $change !== -1) {
                $errors[] = 'That quantity change is not valid.';
            } else {
                $quantity = (int) ($_SESSION['cart'][$productId] ?? 0) + $change;
                if ($quantity < 1) {
                    unset($_SESSION['cart'][$productId]);
                } else {
                    $_SESSION['cart'][$productId] = min(99, $quantity);
                }
                if (!$_SESSION['cart']) {
                    unset($_SESSION['invoice_buyer'], $_SESSION['invoice_people']);
                }
                unset($_SESSION['invoice_ref'], $_SESSION['invoice_date']);
                header('Location: ' . $returnUrl . '#order');
                exit;
            }
        } else {
            $errors[] = 'We could not process that request.';
        }
    }
}

$visibleProducts = [];
foreach ($products as $productId => $product) {
    $matchesCategory = $category === 'All' || $product['category'] === $category;
    $matchesSearch = $search === ''
        || stripos($product['name'], $search) !== false
        || stripos($product['description'], $search) !== false
        || stripos($product['category'], $search) !== false;
    if ($matchesCategory && $matchesSearch) {
        $visibleProducts[$productId] = $product;
    }
}

$cartCount = 0;
$cartTotal = 0.0;
foreach ($_SESSION['cart'] as $productId => $quantity) {
    $cartCount += $quantity;
    $cartTotal += $products[$productId]['price'] * $quantity;
}
$cartNotice = isset($_GET['added']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Coffee, fresh bakes and good company at Oho Cafe.">
    <title>Oho Cafe — Menu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="pos-page">
<div class="pos-layout">
    <aside class="sidebar">
        <a class="brand" href="index.php" aria-label="Oho Cafe home">oho<span>.</span></a>
        <p class="sidebar-caption">CAFE MENU</p>
        <nav class="side-nav" aria-label="Cafe navigation">
            <a class="side-link active" href="#products"><span aria-hidden="true">▦</span> Menu</a>
            <a class="side-link" href="#order"><span aria-hidden="true">▤</span> Current order <span class="side-count"><?= $cartCount ?></span></a>
            <a class="side-link" href="cart.php"><span aria-hidden="true">▧</span> Receipt</a>
        </nav>
        <div class="sidebar-footer">Good coffee, good day.</div>
    </aside>

    <main class="menu-main" id="products">
        <div class="menu-toolbar">
            <div>
                <p class="eyebrow">Welcome to Oho</p>
                <h1>Choose something good.</h1>
            </div>
            <form method="get" class="search-form" role="search">
                <input type="hidden" name="category" value="<?= escape($category) ?>">
                <label class="visually-hidden" for="menu-search">Search menu</label>
                <input id="menu-search" name="q" type="search" placeholder="Search menu..." value="<?= escape($search) ?>">
                <button type="submit" aria-label="Search menu">Search</button>
            </form>
        </div>

        <?php if ($errors): ?>
            <div class="notice notice-error" role="alert">
                <?php foreach ($errors as $error): ?><p><?= escape($error) ?></p><?php endforeach; ?>
            </div>
        <?php elseif ($cartNotice): ?>
            <div class="notice notice-success" role="status">Added to your order.</div>
        <?php endif; ?>

        <nav class="category-list" aria-label="Menu categories">
            <?php foreach ($categories as $menuCategory): ?>
                <?php
                $count = $menuCategory === 'All'
                    ? count($products)
                    : count(array_filter($products, static function (array $product) use ($menuCategory): bool {
                        return $product['category'] === $menuCategory;
                    }));
                $categoryUrl = 'index.php?category=' . rawurlencode($menuCategory) . '&q=' . rawurlencode($search) . '#products';
                ?>
                <a class="category-link<?= $category === $menuCategory ? ' selected' : '' ?>" href="<?= escape($categoryUrl) ?>">
                    <span><?= escape($menuCategory) ?></span>
                    <small><?= $count ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($visibleProducts): ?>
            <div class="product-grid">
                <?php foreach ($visibleProducts as $productId => $product): ?>
                    <article class="product-card">
                        <div class="product-image tone-<?= escape($product['tone']) ?>">
                            <span aria-hidden="true"><?= escape($product['icon']) ?></span>
                        </div>
                        <div class="product-details">
                            <p class="product-category"><?= escape($product['category']) ?></p>
                            <h2><?= escape($product['name']) ?></h2>
                            <p class="product-description"><?= escape($product['description']) ?></p>
                            <div class="product-footer">
                                <strong><?= money($product['price']) ?></strong>
                                <form method="post" action="<?= escape($returnUrl) ?>" class="add-form">
                                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= escape($productId) ?>">
                                    <button class="button button-green" type="submit">Add to order <span aria-hidden="true">+</span></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-menu">No menu items match your search.</div>
        <?php endif; ?>
    </main>

    <aside class="order-panel" id="order">
        <div class="order-heading">
            <div>
                <p class="eyebrow">Oho Cafe</p>
                <h2>Current order</h2>
            </div>
            <span class="order-count"><?= $cartCount ?></span>
        </div>

        <?php if ($_SESSION['cart']): ?>
            <div class="order-items">
                <?php foreach ($_SESSION['cart'] as $productId => $quantity): ?>
                    <?php $product = $products[$productId]; ?>
                    <div class="order-item">
                        <span class="order-item-icon" aria-hidden="true"><?= escape($product['icon']) ?></span>
                        <div class="order-item-info">
                            <strong><?= escape($product['name']) ?></strong>
                            <span><?= money($product['price']) ?> each</span>
                            <div class="quantity-controls">
                                <form method="post" action="<?= escape($returnUrl) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="adjust">
                                    <input type="hidden" name="product_id" value="<?= escape($productId) ?>">
                                    <input type="hidden" name="change" value="-1">
                                    <button type="submit" aria-label="Remove one <?= escape($product['name']) ?>">−</button>
                                </form>
                                <span><?= (int) $quantity ?></span>
                                <form method="post" action="<?= escape($returnUrl) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="adjust">
                                    <input type="hidden" name="product_id" value="<?= escape($productId) ?>">
                                    <input type="hidden" name="change" value="1">
                                    <button type="submit" aria-label="Add one <?= escape($product['name']) ?>">+</button>
                                </form>
                            </div>
                        </div>
                        <strong class="order-item-total"><?= money($product['price'] * $quantity) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="order-total"><span>Total</span><strong><?= money($cartTotal) ?></strong></div>
            <a class="place-order-button" href="cart.php">Review &amp; print receipt <span aria-hidden="true">→</span></a>
        <?php else: ?>
            <div class="empty-order">
                <span aria-hidden="true">☕</span>
                <p>Your order is empty.</p>
                <small>Add a menu item to get started.</small>
            </div>
            <div class="order-total"><span>Total</span><strong><?= money($cartTotal) ?></strong></div>
        <?php endif; ?>
    </aside>
</div>
</body>
</html>
