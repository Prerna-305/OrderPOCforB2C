<?php
// --- DB CONNECTION ---
$mysql = new PDO("mysql:host=dmstaging.dronahq.com;dbname=developer_labs;user=developer_labs;password=developer_labs");
$pg = new PDO("pgsql:host=db.jmepocizgqxtejmzkehk.supabase.co;port=5432;dbname=postgres;user=postgres;password=Success@305");

// --- CRUD: Create ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $stmt = $mysql->prepare("INSERT INTO customers (name, email, phone, city, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$_POST['name'], $_POST['email'], $_POST['phone'], $_POST['city']]);
    header("Location: dashboard.php");
    exit();
}

// --- CRUD: Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $stmt = $mysql->prepare("UPDATE customers SET name=?, email=?, phone=?, city=? WHERE id=?");
    $stmt->execute([$_POST['name'], $_POST['email'], $_POST['phone'], $_POST['city'], $_POST['id']]);
    header("Location: dashboard.php");
    exit();
}

// --- CRUD: Delete ---
if (isset($_GET['delete'])) {
    $stmt = $mysql->prepare("DELETE FROM customers WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: dashboard.php");
    exit();
}

// --- CSV Export ---
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=customers_with_orders.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Phone', 'City', 'Order Product', 'Quantity', 'Total', 'Status', 'Order Date']);

    $customers = $mysql->query("SELECT * FROM customers")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($customers as $cust) {
        $stmt = $pg->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY order_date DESC");
        $stmt->execute([$cust['email']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($orders) {
            foreach ($orders as $order) {
                fputcsv($out, [
                    $cust['name'], $cust['email'], $cust['phone'], $cust['city'],
                    $order['product_name'], $order['quantity'], $order['total_price'],
                    $order['status'], $order['order_date']
                ]);
            }
        } else {
            fputcsv($out, [$cust['name'], $cust['email'], $cust['phone'], $cust['city'], 'No Orders']);
        }
    }
    fclose($out);
    exit();
}

// --- Pagination and Filter ---
$search = $_GET['search'] ?? '';
$cityFilter = $_GET['city'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($cityFilter) {
    $where[] = "city = ?";
    $params[] = $cityFilter;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Total count
$countStmt = $mysql->prepare("SELECT COUNT(*) FROM customers $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Fetch customers
$dataStmt = $mysql->prepare("SELECT * FROM customers $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset");
$dataStmt->execute($params);
$customers = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch orders for all customers
$customerOrders = [];
foreach ($customers as $cust) {
    $stmt = $pg->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY order_date DESC");
    $stmt->execute([$cust['email']]);
    $customerOrders[$cust['email']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>B2C Dashboard (With Orders)</title>
    <style>
        body { font-family: Arial; padding: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #f9f9f9; }
        form { margin-bottom: 20px; }
        .inline-form { display: inline-block; margin-right: 10px; }
        input[type="text"], input[type="email"] { padding: 5px; }
        .pagination a, .pagination strong { margin-right: 5px; }
        .order-table { margin-top: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>

<h2>Customer Dashboard</h2>

<!-- Add Customer -->
<h3>Add Customer</h3>
<form method="post">
    <input type="hidden" name="create" value="1">
    <input type="text" name="name" placeholder="Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="phone" placeholder="Phone" required>
    <input type="text" name="city" placeholder="City" required>
    <button type="submit">Add</button>
</form>

<!-- Filter and Export -->
<form method="get">
    <input type="text" name="search" placeholder="Search name or email" value="<?= htmlspecialchars($search) ?>">
    <input type="text" name="city" placeholder="Filter by city" value="<?= htmlspecialchars($cityFilter) ?>">
    <button type="submit">Search</button>
    <a href="dashboard.php">Reset</a>
    <a href="?export=1">Export CSV</a>
</form>

<!-- Customer Table -->
<h3>Customers & Orders</h3>
<table>
    <thead>
        <tr>
            <th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Orders</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($customers as $cust): ?>
            <tr>
                <td><?= htmlspecialchars($cust['name']) ?></td>
                <td><?= htmlspecialchars($cust['email']) ?></td>
                <td><?= htmlspecialchars($cust['phone']) ?></td>
                <td><?= htmlspecialchars($cust['city']) ?></td>
                <td>
                    <?php
                        $orders = $customerOrders[$cust['email']] ?? [];
                        if ($orders):
                    ?>
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td>₹<?= number_format($order['total_price'], 2) ?></td>
                                        <td><?= $order['status'] ?></td>
                                        <td><?= $order['order_date'] ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        No orders
                    <?php endif ?>
                </td>
                <td>
                    <!-- Update -->
                    <form method="post" class="inline-form">
                        <input type="hidden" name="update" value="1">
                        <input type="hidden" name="id" value="<?= $cust['id'] ?>">
                        <input type="text" name="name" value="<?= htmlspecialchars($cust['name']) ?>" required>
                        <input type="email" name="email" value="<?= htmlspecialchars($cust['email']) ?>" required>
                        <input type="text" name="phone" value="<?= htmlspecialchars($cust['phone']) ?>" required>
                        <input type="text" name="city" value="<?= htmlspecialchars($cust['city']) ?>" required>
                        <button type="submit">Update</button>
                    </form>
                    <!-- Delete -->
                    <a href="?delete=<?= $cust['id'] ?>" onclick="return confirm('Delete this customer?')">Delete</a>
                </td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>

<!-- Pagination -->
<div class="pagination">
    Pages:
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p == $page): ?>
            <strong><?= $p ?></strong>
        <?php else: ?>
            <a href="?search=<?= urlencode($search) ?>&city=<?= urlencode($cityFilter) ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php endif ?>
    <?php endfor ?>
</div>

</body>
</html>
