<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

require __DIR__ . '/../db.php';

$data        = json_decode(file_get_contents('php://input'), true) ?? [];
$username    = $_SESSION['user'];
$productName = trim($data['product_name'] ?? '');
$brand       = trim($data['brand']        ?? '');
$category    = trim($data['category']     ?? '');
$description = trim($data['description'] ?? '');
$price       = trim($data['price']        ?? '');
$purchaseLink = trim($data['purchase_link'] ?? '');

if (!$productName) {
    echo json_encode(['success' => false, 'message' => '產品名稱為必填']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_requests (type, username, product_name, brand, category, description, price, purchase_link)
        VALUES ('submission', :username, :product_name, :brand, :category, :description, :price, :purchase_link)
    ");
    $stmt->execute([
        ':username'     => $username,
        ':product_name' => $productName,
        ':brand'        => $brand,
        ':category'     => $category,
        ':description'  => $description,
        ':price'        => $price,
        ':purchase_link' => $purchaseLink,
    ]);
    echo json_encode(['success' => true, 'message' => '產品申請已送出，待管理者審核後上架！']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '送出失敗：' . $e->getMessage()]);
}
