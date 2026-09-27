<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/DatabaseMigration.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\DatabaseMigration;

$pdo = Database::getInstance()->getConnection();

echo "1. Running schema migrations...\n";
DatabaseMigration::ensureMigrated($pdo);

echo "2. Deactivating old test products...\n";
$pdo->exec("UPDATE `products` SET `is_active` = 0");

$realVegetables = [
    [
        'name' => 'Tomato',
        'telugu_name' => 'టమోటా',
        'category' => 'standard',
        'unit_label' => '0.5 kg',
        'unit_weight_kg' => 0.500,
        'price' => 30.00,
        'stock' => 100,
        'image' => 'uploads/products/tomato.jpeg'
    ],
    [
        'name' => 'Green Chilli (Mirchi)',
        'telugu_name' => 'పచ్చి మిర్చి',
        'category' => 'standard',
        'unit_label' => '0.5 kg',
        'unit_weight_kg' => 0.500,
        'price' => 30.00,
        'stock' => 100,
        'image' => 'uploads/products/mirchi.jpeg'
    ],
    [
        'name' => 'Lady Finger (Bendakaya)',
        'telugu_name' => 'బెండకాయ',
        'category' => 'standard',
        'unit_label' => '0.5 kg',
        'unit_weight_kg' => 0.500,
        'price' => 30.00,
        'stock' => 100,
        'image' => 'uploads/products/lady_finger.jpeg'
    ],
    [
        'name' => 'Brinjal (Vankaya)',
        'telugu_name' => 'వంకాయ',
        'category' => 'standard',
        'unit_label' => '0.5 kg',
        'unit_weight_kg' => 0.500,
        'price' => 30.00,
        'stock' => 100,
        'image' => 'uploads/products/brinjal.jpeg'
    ],
    [
        'name' => 'Ridge Gourd (Beerakaya)',
        'telugu_name' => 'బీరకాయ',
        'category' => 'standard',
        'unit_label' => '0.5 kg',
        'unit_weight_kg' => 0.500,
        'price' => 35.00,
        'stock' => 100,
        'image' => 'uploads/products/ridge_gourd.jpeg'
    ],
    [
        'name' => 'Bottle Gourd (Sorakaya)',
        'telugu_name' => 'సొరకాయ',
        'category' => 'standard',
        'unit_label' => '1 Piece (each)',
        'unit_weight_kg' => 0.500,
        'price' => 20.00,
        'stock' => 100,
        'image' => 'uploads/products/bottle_gourd.jpeg'
    ],
    [
        'name' => 'Bitter Gourd (Kakarakaya)',
        'telugu_name' => 'కాకరకాయ',
        'category' => 'standard',
        'unit_label' => '0.5 kg',
        'unit_weight_kg' => 0.500,
        'price' => 30.00,
        'stock' => 100,
        'image' => 'uploads/products/bitter_gourd.jpeg'
    ],
    [
        'name' => 'Spinach (Palakura)',
        'telugu_name' => 'పాలకూర',
        'category' => 'standard',
        'unit_label' => '200g Bunch',
        'unit_weight_kg' => 0.200,
        'price' => 20.00,
        'stock' => 100,
        'image' => 'uploads/products/palak.jpeg'
    ],
    [
        'name' => 'Green Sorrel (Chukkakura)',
        'telugu_name' => 'చుక్కకూర',
        'category' => 'standard',
        'unit_label' => '200g Bunch',
        'unit_weight_kg' => 0.200,
        'price' => 20.00,
        'stock' => 100,
        'image' => 'uploads/products/chukkakura.jpeg'
    ],
    [
        'name' => 'Roselle Leaves (Gongura)',
        'telugu_name' => 'గోంగూర',
        'category' => 'standard',
        'unit_label' => '300g Bunch',
        'unit_weight_kg' => 0.300,
        'price' => 20.00,
        'stock' => 100,
        'image' => 'uploads/products/gongura.jpeg'
    ],
    [
        'name' => 'Fenugreek Leaves (Methi)',
        'telugu_name' => 'మెంతి కూర',
        'category' => 'standard',
        'unit_label' => '200g Bunch',
        'unit_weight_kg' => 0.200,
        'price' => 20.00,
        'stock' => 100,
        'image' => 'uploads/products/methi.jpeg'
    ],
    [
        'name' => 'Coriander Leaves (Kothimeer)',
        'telugu_name' => 'కొత్తిమీర',
        'category' => 'standard',
        'unit_label' => '150g Bunch',
        'unit_weight_kg' => 0.150,
        'price' => 20.00,
        'stock' => 100,
        'image' => 'uploads/products/kothimeer.jpeg'
    ]
];

echo "3. Inserting / updating 12 real vegetables...\n";

$checkStmt = $pdo->prepare("SELECT id FROM products WHERE name = :name OR telugu_name = :telugu_name");
$insertStmt = $pdo->prepare("
    INSERT INTO products (name, telugu_name, category, unit_label, unit_weight_kg, price_per_half_kg, available_half_kg_stock, image_path, is_active)
    VALUES (:name, :telugu_name, :category, :unit_label, :unit_weight_kg, :price, :stock, :image, 1)
");
$updateStmt = $pdo->prepare("
    UPDATE products SET 
        name = :name,
        telugu_name = :telugu_name,
        category = :category,
        unit_label = :unit_label,
        unit_weight_kg = :unit_weight_kg,
        price_per_half_kg = :price,
        available_half_kg_stock = :stock,
        image_path = :image,
        is_active = 1
    WHERE id = :id
");

foreach ($realVegetables as $veg) {
    $checkStmt->execute([':name' => $veg['name'], ':telugu_name' => $veg['telugu_name']]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $updateStmt->execute([
            ':id' => $existing['id'],
            ':name' => $veg['name'],
            ':telugu_name' => $veg['telugu_name'],
            ':category' => $veg['category'],
            ':unit_label' => $veg['unit_label'],
            ':unit_weight_kg' => $veg['unit_weight_kg'],
            ':price' => $veg['price'],
            ':stock' => $veg['stock'],
            ':image' => $veg['image']
        ]);
        echo "Updated: {$veg['name']} ({$veg['telugu_name']}) - {$veg['unit_weight_kg']} kg\n";
    } else {
        $insertStmt->execute([
            ':name' => $veg['name'],
            ':telugu_name' => $veg['telugu_name'],
            ':category' => $veg['category'],
            ':unit_label' => $veg['unit_label'],
            ':unit_weight_kg' => $veg['unit_weight_kg'],
            ':price' => $veg['price'],
            ':stock' => $veg['stock'],
            ':image' => $veg['image']
        ]);
        echo "Inserted: {$veg['name']} ({$veg['telugu_name']}) - {$veg['unit_weight_kg']} kg\n";
    }
}

echo "4. Refreshing run_inventory for active delivery schedules...\n";
$schedules = $pdo->query("SELECT id FROM delivery_schedules")->fetchAll(PDO::FETCH_COLUMN);

foreach ($schedules as $sid) {
    $sid = (int)$sid;
    $pdo->exec("DELETE FROM run_inventory WHERE schedule_id = {$sid}");
    $pdo->exec("
        INSERT INTO run_inventory (schedule_id, product_id, harvest_kg, available_half_kg_stock, price_per_half_kg, unit_label, unit_weight_kg, is_active)
        SELECT {$sid}, p.id, ROUND(p.available_half_kg_stock * p.unit_weight_kg, 2), p.available_half_kg_stock, p.price_per_half_kg, p.unit_label, p.unit_weight_kg, p.is_active
        FROM products p
        WHERE p.is_active = 1
    ");
}

echo "SUCCESS! Real vegetable catalog seeded completely with accurate unit harvest weights.\n";
