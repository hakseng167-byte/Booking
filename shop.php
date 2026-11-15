<?php
declare(strict_types=1);

session_start();

$products = [
    'espresso' => [
        'name' => 'Espresso',
        'subtitle' => 'Rich · bold · classic',
        'description' => 'A smooth, full-bodied shot pulled fresh from our house blend.',
        'price' => 2.50,
        'tone' => 'cream',
        'category' => 'Coffee',
        'icon' => '☕',
    ],
    'latte' => [
        'name' => 'Oho Latte',
        'subtitle' => 'Silky · comforting',
        'description' => 'Our signature espresso with silky steamed milk and a little latte art.',
        'price' => 3.75,
        'tone' => 'sage',
        'category' => 'Coffee',
        'icon' => '☕',
    ],
    'americano' => [
        'name' => 'Americano',
        'subtitle' => 'Smooth · simple',
        'description' => 'Espresso topped with hot water for a clean, mellow cup.',
        'price' => 3.00,
        'tone' => 'cream',
        'category' => 'Coffee',
        'icon' => '☕',
    ],
    'cappuccino' => [
        'name' => 'Cappuccino',
        'subtitle' => 'Rich · foamy',
        'description' => 'Espresso with steamed milk and a soft layer of foam.',
        'price' => 3.75,
        'tone' => 'clay',
        'category' => 'Hot drinks',
        'icon' => '☕',
    ],
    'iced-coffee' => [
        'name' => 'Iced Coffee',
        'subtitle' => 'Chilled · refreshing',
        'description' => 'Slow-brewed coffee, poured over ice for an easy pick-me-up.',
        'price' => 3.50,
        'tone' => 'rose',
        'category' => 'Cold drinks',
        'icon' => '🧋',
    ],
    'matcha' => [
        'name' => 'Iced Matcha',
        'subtitle' => 'Bright · gently sweet',
        'description' => 'Vibrant matcha whisked smooth and served over cold milk and ice.',
        'price' => 4.25,
        'tone' => 'matcha',
        'category' => 'Cold drinks',
        'icon' => '🍵',
    ],
    'lemon-tea' => [
        'name' => 'Iced Lemon Tea',
        'subtitle' => 'Citrusy · refreshing',
        'description' => 'Freshly brewed tea with lemon, served chilled over ice.',
        'price' => 3.25,
        'tone' => 'cream',
        'category' => 'Cold drinks',
        'icon' => '🍋',
    ],
    'croissant' => [
        'name' => 'Butter Croissant',
        'subtitle' => 'Flaky · baked fresh',
        'description' => 'Golden, buttery pastry with delicate, crisp layers.',
        'price' => 2.75,
        'tone' => 'clay',
        'category' => 'Bakery',
        'icon' => '🥐',
    ],
    'cake' => [
        'name' => 'Daily Cake',
        'subtitle' => 'Soft · just-baked',
        'description' => 'A generous slice of our daily cake, made for your coffee break.',
        'price' => 4.00,
        'tone' => 'pink',
        'category' => 'Cakes',
        'icon' => '🍰',
    ],
    'muffin' => [
        'name' => 'Blueberry Muffin',
        'subtitle' => 'Soft · baked fresh',
        'description' => 'A tender muffin filled with juicy blueberries.',
        'price' => 3.25,
        'tone' => 'pink',
        'category' => 'Bakery',
        'icon' => '🧁',
    ],
    'rice-bowl' => [
        'name' => 'Chicken Rice Bowl',
        'subtitle' => 'Savory · filling',
        'description' => 'A warm bowl of seasoned rice served with tender chicken.',
        'price' => 7.50,
        'tone' => 'cream',
        'category' => 'Rice',
        'icon' => '🍚',
    ],
    'burger' => [
        'name' => 'Oho Beef Burger',
        'subtitle' => 'Juicy · freshly grilled',
        'description' => 'A grilled beef patty with lettuce and sauce in a soft bun.',
        'price' => 6.50,
        'tone' => 'clay',
        'category' => 'Burgers',
        'icon' => '🍔',
    ],
    'orange-juice' => [
        'name' => 'Fresh Orange Juice',
        'subtitle' => 'Fresh · naturally sweet',
        'description' => 'Bright orange juice, freshly squeezed and served cold.',
        'price' => 3.75,
        'tone' => 'cream',
        'category' => 'Juice',
        'icon' => '🍊',
    ],
];

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $amount): string
{
    return '$' . number_format($amount, 2);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$_SESSION['cart'] = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
$validCart = [];
foreach ($_SESSION['cart'] as $productId => $quantity) {
    if (!isset($products[$productId])) {
        continue;
    }
    $validQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
    if ($validQuantity !== false && $validQuantity > 0) {
        $validCart[$productId] = min(99, $validQuantity);
    }
}
$_SESSION['cart'] = $validCart;
