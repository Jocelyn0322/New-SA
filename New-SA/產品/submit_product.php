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

if (!$productName || !$brand) {
    echo json_encode(['success' => false, 'message' => '產品名稱與品牌為必填']);
    exit;
}

try {
    // 查重：同品牌 + 同名稱已存在 data 表
    $dup = $pdo->prepare("SELECT id FROM data WHERE name = ? AND brand = ? LIMIT 1");
    $dup->execute([$productName, $brand]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => '此產品已存在於資料庫中，無需重複新增']);
        exit;
    }
    // 查重：同品牌 + 同名稱已有待審核申請
    $dup2 = $pdo->prepare("SELECT id FROM product_requests WHERE product_name = ? AND brand = ? AND type = 'submission' AND status = 'pending' LIMIT 1");
    $dup2->execute([$productName, $brand]);
    if ($dup2->fetch()) {
        echo json_encode(['success' => false, 'message' => '此產品已有待審核的申請，請等待管理員審核']);
        exit;
    }

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
