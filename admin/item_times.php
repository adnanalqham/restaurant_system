<?php
require_once __DIR__ . '/_layout.php';
adminHeader('أوقات تحضير الأصناف ⏱️', 'item_times');

$db = getDB();
$filter = $_GET['filter'] ?? '7days';

// Determine date range condition
$dateCondition = "";
switch ($filter) {
    case 'today':
        $dateCondition = "AND DATE(oi.prep_end_time) = CURDATE()";
        break;
    case '7days':
        $dateCondition = "AND oi.prep_end_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $dateCondition = "AND oi.prep_end_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'all':
    default:
        $dateCondition = "";
        break;
}

// Main query: calculate prep time in seconds, group by item
$stmt = $db->query("
    SELECT 
        i.name_ar, 
        i.name_en, 
        COUNT(oi.id) as times_prepared,
        AVG(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as avg_time_sec,
        MIN(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as min_time_sec,
        MAX(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as max_time_sec
    FROM order_items oi
    JOIN items i ON oi.item_id = i.id
    WHERE oi.prep_start_time IS NOT NULL 
      AND oi.prep_end_time IS NOT NULL
      $dateCondition
    GROUP BY oi.item_id
    ORDER BY avg_time_sec DESC
");
$itemStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatSecToMin($seconds) {
    if (!$seconds || $seconds < 0) return "-";
    $m = floor($seconds / 60);
    $s = round($seconds % 60);
    if ($m > 0) {
        return sprintf("%dد و %02dث", $m, $s);
    }
    return sprintf("%d ثانية", $s);
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px; gap: 15px;">
    <div>
        <h2 style="margin: 0; font-size: 1.6rem; color: var(--text-main);">
            <i class="fas fa-stopwatch" style="color: var(--primary); margin-left: 8px;"></i>أوقات تحضير الأصناف
        </h2>
        <p style="margin: 6px 0 0 0; color: var(--text-muted); font-size: 0.95rem;">
            تتبع الوقت المستغرق من لحظة استلام الشيف للطلب (تحضير) حتى انتهائه (جاهز).
        </p>
    </div>
    <div>
        <form method="GET" style="margin: 0;">
            <select name="filter" class="form-control" onchange="this.form.submit()" style="padding: 8px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text-main); font-family: inherit; cursor: pointer; min-width: 150px;">
                <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>اليوم</option>
                <option value="7days" <?= $filter == '7days' ? 'selected' : '' ?>>آخر 7 أيام</option>
                <option value="30days" <?= $filter == '30days' ? 'selected' : '' ?>>آخر 30 يوم</option>
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>كل الأوقات</option>
            </select>
        </form>
    </div>
</div>

<!-- Chart.js Library CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if (!empty($itemStats)): ?>
<!-- Analytical Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 24px;">
    <?php
    $maxAvgItem = null;
    $minAvgItem = null;
    $totalPreps = 0;
    foreach ($itemStats as $stat) {
        $totalPreps += (int)$stat['times_prepared'];
        if ($maxAvgItem === null || $stat['avg_time_sec'] > $maxAvgItem['avg_time_sec']) {
            $maxAvgItem = $stat;
        }
        if ($minAvgItem === null || $stat['avg_time_sec'] < $minAvgItem['avg_time_sec']) {
            $minAvgItem = $stat;
        }
    }
    ?>
    <div style="background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); padding: 15px 20px; border-radius: 12px; border: 1px solid #bae6fd; box-shadow: 0 2px 4px rgba(0,0,0,0.02)">
        <h6 style="color: #0369a1; margin: 0; font-size: 0.9rem; font-weight: 700;">إجمالي الوجبات المحضرة</h6>
        <div style="font-size: 1.8rem; font-weight: 800; color: #0369a1; margin-top: 5px;"><?= $totalPreps ?> <span style="font-size: 0.95rem; font-weight: 600;">تحضير</span></div>
    </div>
    <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); padding: 15px 20px; border-radius: 12px; border: 1px solid #fecaca; box-shadow: 0 2px 4px rgba(0,0,0,0.02)">
        <h6 style="color: #991b1b; margin: 0; font-size: 0.9rem; font-weight: 700;">الأكثر استهلاكاً للوقت (متوسط)</h6>
        <div style="font-size: 1.3rem; font-weight: 800; color: #991b1b; margin-top: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($maxAvgItem['name_ar']) ?></div>
        <small style="color: #b91c1c; font-weight: 700; font-size: 0.9rem;"><i class="fas fa-clock"></i> <?= formatSecToMin($maxAvgItem['avg_time_sec']) ?></small>
    </div>
    <div style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); padding: 15px 20px; border-radius: 12px; border: 1px solid #bbf7d0; box-shadow: 0 2px 4px rgba(0,0,0,0.02)">
        <h6 style="color: #166534; margin: 0; font-size: 0.9rem; font-weight: 700;">الأسرع في التحضير (متوسط)</h6>
        <div style="font-size: 1.3rem; font-weight: 800; color: #166534; margin-top: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($minAvgItem['name_ar']) ?></div>
        <small style="color: #15803d; font-weight: 700; font-size: 0.9rem;"><i class="fas fa-bolt"></i> <?= formatSecToMin($minAvgItem['avg_time_sec']) ?></small>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Chart 1: Average Prep Time -->
    <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white py-3 border-0" style="border-bottom: 1px solid var(--border) !important;">
            <h5 class="mb-0" style="font-weight: 700; color: var(--text-main); font-size: 1rem;"><i class="fas fa-chart-bar text-primary" style="margin-left: 5px;"></i> متوسط وقت التحضير (بالدقائق)</h5>
        </div>
        <div class="card-body" style="position: relative; height: 300px; padding: 15px;">
            <canvas id="avgTimeChart"></canvas>
        </div>
    </div>
    <!-- Chart 2: Times Prepared -->
    <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white py-3 border-0" style="border-bottom: 1px solid var(--border) !important;">
            <h5 class="mb-0" style="font-weight: 700; color: var(--text-main); font-size: 1rem;"><i class="fas fa-chart-pie text-success" style="margin-left: 5px;"></i> نسبة ومرات التحضير لكل صنف</h5>
        </div>
        <div class="card-body" style="position: relative; height: 300px; padding: 15px;">
            <canvas id="prepCountChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">الصنف</th>
                        <th class="text-center py-3">مرات التحضير</th>
                        <th class="text-center py-3">متوسط الوقت <i class="fas fa-clock text-primary"></i></th>
                        <th class="text-center py-3">أسرع وقت <i class="fas fa-bolt text-success"></i></th>
                        <th class="text-center py-3">أبطأ وقت <i class="fas fa-hourglass-end text-danger"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itemStats)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3 text-light"></i>
                                <h5>لا توجد بيانات للفترة المحددة</h5>
                                <p>يجب على الشيف استخدام أزرار "تحضير" و "جاهز" ليتم حساب الوقت.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itemStats as $stat): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($stat['name_ar']) ?></td>
                                <td class="text-center"><span class="badge bg-secondary rounded-pill px-3"><?= $stat['times_prepared'] ?> مرات</span></td>
                                <td class="text-center fw-bold text-primary" style="font-size: 1.1rem;"><?= formatSecToMin($stat['avg_time_sec']) ?></td>
                                <td class="text-center text-success fw-bold"><?= formatSecToMin($stat['min_time_sec']) ?></td>
                                <td class="text-center text-danger fw-bold"><?= formatSecToMin($stat['max_time_sec']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const rawData = <?= json_encode($itemStats) ?>;
    if (!rawData || rawData.length === 0) return;

    const labels = rawData.map(d => d.name_ar);
    const avgMinutes = rawData.map(d => (parseFloat(d.avg_time_sec) / 60).toFixed(2));
    const prepCounts = rawData.map(d => parseInt(d.times_prepared));

    // Chart 1: Average Prep Time (Bar Chart)
    const ctx1 = document.getElementById('avgTimeChart').getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'متوسط وقت التحضير بالدقائق',
          data: avgMinutes,
          backgroundColor: 'rgba(54, 162, 235, 0.75)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1,
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: { display: true, text: 'الوقت بالدقائق', font: { family: 'inherit', size: 11 } }
          },
          x: {
            ticks: { font: { family: 'inherit', size: 10 } }
          }
        }
      }
    });

    // Chart 2: Times Prepared (Pie/Doughnut Chart)
    const ctx2 = document.getElementById('prepCountChart').getContext('2d');
    const colors = [
      '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', 
      '#FF9F40', '#32C787', '#E74C3C', '#9B59B6', '#1ABC9C'
    ];
    
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: labels.slice(0, 10),
        datasets: [{
          data: prepCounts.slice(0, 10),
          backgroundColor: colors,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { font: { family: 'inherit', size: 10 } }
          }
        }
      }
    });
  });
</script>

<?php adminFooter(); ?>
