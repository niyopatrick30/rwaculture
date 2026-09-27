<?php
$page_title = 'Generated Reports Summary';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

/* ===============================
   DATE SETUP
================================ */
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

/* ===============================
   DAILY REPORT
================================ */
$daily = ['orders'=>0,'sales'=>0,'commission'=>0];
$stmt = $conn->prepare("
    SELECT COUNT(*) orders, 
           SUM(total_amount) sales, 
           SUM(commission) commission
    FROM orders 
    WHERE DATE(created_at)=? AND status!='cancelled'
");
if ($stmt) {
    $stmt->bind_param("s",$today);
    $stmt->execute();
    $daily = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ===============================
   WEEKLY REPORT
================================ */
$weekly = ['orders'=>0,'sales'=>0,'commission'=>0];
$stmt = $conn->prepare("
    SELECT COUNT(*) orders, 
           SUM(total_amount) sales, 
           SUM(commission) commission
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ? 
    AND status!='cancelled'
");
if ($stmt) {
    $stmt->bind_param("ss",$week_start,$today);
    $stmt->execute();
    $weekly = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ===============================
   MONTHLY REPORT
================================ */
$monthly = ['orders'=>0,'sales'=>0,'commission'=>0];
$stmt = $conn->prepare("
    SELECT COUNT(*) orders, 
           SUM(total_amount) sales, 
           SUM(commission) commission
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ? 
    AND status!='cancelled'
");
if ($stmt) {
    $stmt->bind_param("ss",$month_start,$today);
    $stmt->execute();
    $monthly = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ===============================
   MONTH-TO-MONTH GROWTH
================================ */
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end   = date('Y-m-t', strtotime('-1 month'));

$growth = ['current'=>0,'previous'=>0,'percent'=>0];

$stmt = $conn->prepare("
    SELECT SUM(total_amount) total
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status!='cancelled'
");
if ($stmt) {
    $stmt->bind_param("ss",$month_start,$today);
    $stmt->execute();
    $growth['current'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT SUM(total_amount) total
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status!='cancelled'
");
if ($stmt) {
    $stmt->bind_param("ss",$last_month_start,$last_month_end);
    $stmt->execute();
    $growth['previous'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

if ($growth['previous'] > 0) {
    $growth['percent'] = (($growth['current'] - $growth['previous']) / $growth['previous']) * 100;
}

/* ===============================
   AUTO SAVE REPORT (SAFE)
================================ */
$save_sql = "
INSERT INTO report_history 
(report_date, daily_sales, weekly_sales, monthly_sales)
VALUES (?, ?, ?, ?)
";

$save_stmt = $conn->prepare($save_sql);
if ($save_stmt) {
    $save_stmt->bind_param(
        "sddd",
        $today,
        $daily['sales'],
        $weekly['sales'],
        $monthly['sales']
    );
    $save_stmt->execute();
    $save_stmt->close();
}

/* ===============================
   EMAIL PLACEHOLDER
================================ */
if (isset($_GET['email'])) {
    // mail($admin_email, 'Daily Report', 'Report generated');
}

/* ===============================
   PDF EXPORT PLACEHOLDER
================================ */
if (isset($_GET['pdf'])) {
    header("Content-Type: application/pdf");
    // PDF generation logic can be added here
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h2 class="mb-4">📊 Generated Report Summary</h2>

    <div class="row">
        <div class="col-md-4">
            <div class="dashboard-card">
                <h5>📅 Daily Report</h5>
                <p>Orders: <?= $daily['orders'] ?></p>
                <p>Sales: <?= formatCurrency($daily['sales']) ?></p>
                <p>Commission: <?= formatCurrency($daily['commission']) ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dashboard-card">
                <h5>📆 Weekly Report</h5>
                <p>Orders: <?= $weekly['orders'] ?></p>
                <p>Sales: <?= formatCurrency($weekly['sales']) ?></p>
                <p>Commission: <?= formatCurrency($weekly['commission']) ?></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dashboard-card">
                <h5>🗓 Monthly Report</h5>
                <p>Orders: <?= $monthly['orders'] ?></p>
                <p>Sales: <?= formatCurrency($monthly['sales']) ?></p>
                <p>Commission: <?= formatCurrency($monthly['commission']) ?></p>
            </div>
        </div>
    </div>

    <div class="dashboard-card mt-4">
        <h5>📈 Month-to-Month Growth</h5>
        <p>Last Month: <?= formatCurrency($growth['previous']) ?></p>
        <p>This Month: <?= formatCurrency($growth['current']) ?></p>
        <p>
            Growth: 
            <strong style="color:<?= $growth['percent']>=0?'green':'red' ?>">
                <?= number_format($growth['percent'],2) ?>%
            </strong>
        </p>
    </div>

    <div class="mt-4 d-flex gap-2">
        <a href="?pdf=1" class="btn btn-outline-dark">📄 Export PDF</a>
        <a href="?email=1" class="btn btn-outline-primary">📧 Email Report</a>
        <a href="reports.php" class="btn btn-secondary">⬅ Back</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
