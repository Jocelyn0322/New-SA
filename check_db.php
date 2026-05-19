<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'project', 3307);
if($conn->connect_error) { die('error: '.$conn->connect_error); }
$r = $conn->query('SELECT COUNT(*) as cnt FROM products');
echo 'products: '.$r->fetch_assoc()['cnt']."\n";
$r2 = $conn->query('SELECT COUNT(*) as cnt FROM product_colors');
echo 'product_colors: '.$r2->fetch_assoc()['cnt']."\n";
$r3 = $conn->query('SELECT DISTINCT category FROM products');
$cats = [];
while($row = $r3->fetch_assoc()) $cats[] = $row['category'];
echo 'categories: '.implode(', ', $cats)."\n";
