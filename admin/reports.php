<?php  
$page_title = 'Reports & Analytics';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Sales Report
$sales_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_sales,
    SUM(commission) as total_commission,
    AVG(total_amount) as avg_order_value
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'");
$sales_stmt->bind_param("ss", $start_date, $end_date);
$sales_stmt->execute();
$sales_report = $sales_stmt->get_result()->fetch_assoc();
$sales_stmt->close();

// Daily Sales Chart Data
$daily_sales = [];
$daily_stmt = $conn->prepare("SELECT DATE(created_at) as sale_date, SUM(total_amount) as daily_total, COUNT(*) as order_count
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC");
$daily_stmt->bind_param("ss", $start_date, $end_date);
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();
while ($row = $daily_result->fetch_assoc()) {
    $daily_sales[] = $row;
}
$daily_stmt->close();

// Top Buyers Ranking using orders table
$top_buyers = [];
$buyers_stmt = $conn->prepare("
    SELECT 
        o.buyer_id,
        CONCAT(u.first_name, ' ', u.last_name) AS full_name,
        u.email,
        u.phone,
        o.buyer_address AS address,
        COUNT(o.id) AS total_orders,
        SUM(o.total_amount) AS total_spent,
        SUM(CASE WHEN DATE(o.created_at) = CURDATE() THEN o.total_amount ELSE 0 END) AS daily_spent,
        SUM(CASE WHEN YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1) THEN o.total_amount ELSE 0 END) AS weekly_spent,
        SUM(CASE WHEN MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE()) THEN o.total_amount ELSE 0 END) AS monthly_spent
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
      AND o.status != 'cancelled'
    GROUP BY o.buyer_id
    ORDER BY total_orders DESC
");
if (!$buyers_stmt) {
    die("SQL Error (Top Buyers): " . $conn->error);
}
$buyers_stmt->bind_param("ss", $start_date, $end_date);
$buyers_stmt->execute();
$buyers_result = $buyers_stmt->get_result();
while ($row = $buyers_result->fetch_assoc()) {
    $top_buyers[] = $row;
}
$buyers_stmt->close();

// Top Products
$top_products = [];
$top_stmt = $conn->prepare("SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10");
$top_stmt->bind_param("ss", $start_date, $end_date);
$top_stmt->execute();
$top_result = $top_stmt->get_result();
while ($row = $top_result->fetch_assoc()) {
    $top_products[] = $row;
}
$top_stmt->close();

// Top Sellers
$top_sellers = [];
$seller_stmt = $conn->prepare("SELECT u.first_name, u.last_name, COUNT(o.id) as order_count, SUM(o.total_amount) as total_sales
    FROM orders o
    JOIN users u ON o.seller_id = u.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY u.id
    ORDER BY total_sales DESC
    LIMIT 10");
$seller_stmt->bind_param("ss", $start_date, $end_date);
$seller_stmt->execute();
$seller_result = $seller_stmt->get_result();
while ($row = $seller_result->fetch_assoc()) {
    $top_sellers[] = $row;
}
$seller_stmt->close();

// Order Status Breakdown
$status_breakdown = [];
$status_stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
$status_stmt->bind_param("ss", $start_date, $end_date);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
while ($row = $status_result->fetch_assoc()) {
    $status_breakdown[] = $row;
}
$status_stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4">Reports & Analytics</h1>
    
    <!-- Date Range Filter -->
    <div class="dashboard-card mb-4">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <a href="generate.php" class="btn btn-primary w-100">Generate Report</a>
            </div>
        </form>
    </div>
    
    <!-- Sales Summary -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $sales_report['total_orders'] ?? 0; ?></h4>
                <p>Total Orders</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo formatCurrency($sales_report['total_sales'] ?? 0); ?></h4>
                <p>Total Sales</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo formatCurrency($sales_report['total_commission'] ?? 0); ?></h4>
                <p>Platform Commission</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo formatCurrency($sales_report['avg_order_value'] ?? 0); ?></h4>
                <p>Average Order Value</p>
            </div>
        </div>
    </div>
    
    <!-- Daily Sales Chart -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="dashboard-card">
                <h3>Daily Sales Trend</h3>
                <canvas id="dailySalesChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Buyers Ranking Table -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="dashboard-card">
                <h3>Top Buyers Performance & Ranking</h3>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#Rank</th>
                                <th>Buyer Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Orders</th>
                                <th>Daily Spend</th>
                                <th>Weekly Spend</th>
                                <th>Monthly Spend</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_buyers)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No buyer data available</td>
                                </tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($top_buyers as $buyer): ?>
                                    <tr>
                                        <td><strong><?php echo $rank++; ?></strong></td>
                                        <td><?php echo htmlspecialchars($buyer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($buyer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($buyer['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($buyer['address']); ?></td>
                                        <td><?php echo $buyer['total_orders']; ?></td>
                                        <td><?php echo formatCurrency($buyer['daily_spent']); ?></td>
                                        <td><?php echo formatCurrency($buyer['weekly_spent']); ?></td>
                                        <td><?php echo formatCurrency($buyer['monthly_spent']); ?></td>
                                        <td><strong><?php echo formatCurrency($buyer['total_spent']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="dashboard-card">
                <h3>Top Selling Products</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo $product['total_sold']; ?></td>
                                        <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Top Sellers -->
        <div class="col-md-6">
            <div class="dashboard-card">
                <h3>Top Sellers</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Seller</th>
                                <th>Orders</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_sellers)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_sellers as $seller): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></td>
                                        <td><?php echo $seller['order_count']; ?></td>
                                        <td><?php echo formatCurrency($seller['total_sales']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Status Breakdown -->
    <div class="row">
        <div class="col-md-12">
            <div class="dashboard-card">
                <h3>Order Status Breakdown</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_status = array_sum(array_column($status_breakdown, 'count'));
                            foreach ($status_breakdown as $status): 
                                $percentage = $total_status > 0 ? ($status['count'] / $total_status) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?></td>
                                    <td><?php echo $status['count']; ?></td>
                                    <td><?php echo number_format($percentage, 2); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Sales Chart
const dailySalesData = <?php echo json_encode($daily_sales); ?>;
const ctx = document.getElementById('dailySalesChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: dailySalesData.map(item => item.sale_date),
        datasets: [{
            label: 'Daily Sales (RWF)',
            data: dailySalesData.map(item => parseFloat(item.daily_total || 0)),
            borderColor: '#000000',
            backgroundColor: 'rgba(0, 0, 0, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'RWF ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
