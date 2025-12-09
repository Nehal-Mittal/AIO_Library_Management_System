<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';

if ($department === '') {
    echo json_encode([]);
    exit;
}

$limit = 100;
if ($q !== '') {
    $like = $q . '%';
    $stmt = mysqli_prepare($conn, "SELECT id, title, author, department FROM books WHERE status='available' AND department = ? AND title LIKE ? ORDER BY title LIMIT 10");
    mysqli_stmt_bind_param($stmt, 'ss', $department, $like);
} else {
    $stmt = mysqli_prepare($conn, "SELECT id, title, author, department FROM books WHERE status='available' AND department = ? ORDER BY title LIMIT $limit");
    mysqli_stmt_bind_param($stmt, 's', $department);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$out = [];
while ($row = mysqli_fetch_assoc($res)) {
    $out[] = ['book_id' => (int)$row['id'], 'title' => $row['title'], 'author' => $row['author'], 'department' => $row['department']];
}
mysqli_stmt_close($stmt);

echo json_encode($out);
?>



