<?php

require_once __DIR__ . '/includes/init.php';

pos_require_setup_complete();
$id = (int) ($_GET['id'] ?? 0);
$job = $id > 0 ? pos_get_repair_job($id) : null;

if (!$job) {
    http_response_code(404);
    echo 'Repair job not found.';
    exit;
}

$totalPaid = pos_repair_total_paid($job);
$balanceDue = pos_repair_balance_due($job);
$final = (float) $job['final_cost'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($job['job_no']); ?> | Repair Receipt</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 24px; color: #111; font-size: 14px; max-width: 400px; }
        h1 { margin: 0 0 4px; font-size: 1.35rem; }
        .tagline { margin: 0 0 20px; color: #555; font-size: 0.9rem; }
        .meta p { margin: 6px 0; }
        .totals { margin: 20px 0; padding: 14px 0; border-top: 2px solid #111; border-bottom: 1px solid #ccc; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { font-weight: 700; font-size: 1.05rem; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #999; }
        .thanks { margin-top: 24px; text-align: center; font-size: 0.95rem; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <h1><?php echo htmlspecialchars(SITE_NAME); ?></h1>
    <p class="tagline">Repair job receipt</p>

    <div class="meta">
        <p><strong>Job:</strong> <?php echo htmlspecialchars($job['job_no']); ?></p>
        <p><strong>Date:</strong> <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($job['created_at']))); ?></p>
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($job['customer_name']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($job['customer_phone'] ?: '—'); ?></p>
        <p><strong>Device:</strong> <?php echo htmlspecialchars(trim($job['device_brand'] . ' ' . $job['device_model']) ?: '—'); ?></p>
        <?php if ($job['imei_serial'] !== ''): ?>
        <p><strong>IMEI/Serial:</strong> <?php echo htmlspecialchars($job['imei_serial']); ?></p>
        <?php endif; ?>
        <p><strong>Issue:</strong> <?php echo htmlspecialchars($job['issue_description']); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($job['status']); ?></p>
        <?php if ((int) $job['repair_warranty_days'] > 0 && $job['warranty_end_date']): ?>
        <p><strong>Warranty until:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($job['warranty_end_date']))); ?></p>
        <?php endif; ?>
    </div>

    <div class="totals">
        <div><span>Estimated</span><span><?php echo pos_format_money((float) $job['estimated_cost']); ?></span></div>
        <div><span>Final cost</span><span><?php echo $final > 0 ? pos_format_money($final) : '—'; ?></span></div>
        <div><span>Advance / paid</span><span><?php echo pos_format_money($totalPaid); ?></span></div>
        <div class="grand"><span>Balance</span><span><?php echo $final > 0 ? pos_format_money($balanceDue) : '—'; ?></span></div>
    </div>

    <?php if ($job['notes'] !== ''): ?>
    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($job['notes'])); ?></p>
    <?php endif; ?>

    <p class="thanks">Thank you for choosing <?php echo htmlspecialchars(SITE_NAME); ?>.</p>
</body>
</html>
