<?php
require_once __DIR__ . '/_layout.php';
adminHeader('بيع تذاكر', 'ticket_sales');
?>
<style>
.sales-layout {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 1100px) {
  .sales-layout { grid-template-columns: 1fr; }
}

/* Ticket type selector cards */
.type-selector-card {
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: all .2s;
  margin-bottom: 10px;
}
.type-selector-card:hover {
  border-color: var(--primary);
  background: var(--bg);
  transform: translateX(-2px);
}
.type-selector-card.selected {
  border-color: var(--primary);
  background: linear-gradient(135deg, rgba(var(--primary-rgb),.08), rgba(var(--primary-rgb),.02));
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb),.15);
}
.tsc-name {
  font-size: 1rem;
  font-weight: 700;
  color: var(--secondary);
  margin-bottom: 4px;
}
.tsc-price {
  font-size: 1.3rem;
  font-weight: 900;
  color: var(--primary);
}
.tsc-meta {
  display: flex;
  gap: 12px;
  margin-top: 6px;
  font-size: .8rem;
}
.tsc-badge {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 2px 10px;
  color: var(--text-muted);
}
.tsc-badge.green { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.tsc-badge.red { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }

/* Receipt panel */
.receipt-panel {
  background: var(--bg-card);
  border-radius: 14px;
  border: 1px solid var(--border);
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0,0,0,.05);
}
.receipt-header {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark, #1a5276));
  color: #fff;
  padding: 16px 20px;
}
.receipt-header h4 { margin: 0; font-size: 1.1rem; }
.receipt-body { padding: 20px; }

/* Sell form */
.sell-form-card {
  background: var(--bg-card);
  border-radius: 14px;
  border: 1px solid var(--border);
  padding: 20px;
  margin-bottom: 16px;
}

/* Sale row */
.sale-row {
  display: grid;
  grid-template-columns: 60px 1fr auto auto auto;
  gap: 8px;
  align-items: center;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
  font-size: .875rem;
}
.sale-row:last-child { border-bottom: none; }
.sale-row:hover { background: var(--bg); }
</style>

<div class="sales-layout">

<!-- ── Left: Ticket Type Picker + Sell Form ───────────────────────────── -->
<div>
  <div class="card mb-16">
    <div class="card-header">
      <h3><i class="fas fa-ticket-alt"></i> اختر نوع التذكرة</h3>
      <button class="btn btn-outline btn-primary btn-sm" onclick="loadTypes()"><i class="fas fa-sync"></i></button>
    </div>
    <div class="card-body" id="types-list" style="padding:12px; max-height:380px; overflow-y:auto;">
      <div style="text-align:center; padding:20px; color:var(--text-muted)">
        <div class="spinner border-primary" style="margin:0 auto 8px"></div> جاري التحميل...
      </div>
    </div>
  </div>

  <!-- Sell Panel -->
  <div class="sell-form-card" id="sell-panel" style="display:none">
    <div style="margin-bottom:14px">
      <div style="font-size:1.15rem; font-weight:800; color:var(--secondary)" id="sp-name">-</div>
      <div style="color:var(--primary); font-size:1.4rem; font-weight:900" id="sp-price">0.00 ريال</div>
      <div style="margin-top:4px; font-size:.82rem; color:var(--text-muted)" id="sp-avail">-</div>
    </div>

    <div class="form-group">
      <label class="form-label">عدد التذاكر *</label>
      <div style="display:flex; align-items:center; gap:10px">
        <button class="btn btn-secondary" onclick="changeQty(-1)" style="width:40px; height:40px; padding:0; font-size:1.2rem">-</button>
        <input type="number" class="form-control" id="sp-qty" value="1" min="1" style="text-align:center; font-size:1.2rem; font-weight:700" oninput="updateTotal()">
        <button class="btn btn-secondary" onclick="changeQty(1)" style="width:40px; height:40px; padding:0; font-size:1.2rem">+</button>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">رقم الهاتف (اختياري)</label>
      <div style="display:flex; align-items:stretch; gap:0">
        <span style="display:flex; align-items:center; background:var(--bg); border:1px solid var(--border); border-left:none; border-radius:0 8px 8px 0; padding:0 12px; color:var(--text-muted); font-size:.95rem; white-space:nowrap; direction:ltr">
          <img src="https://flagcdn.com/16x12/ye.png" alt="YE" style="margin-left:6px; vertical-align:middle"> +967
        </span>
        <input type="tel" class="form-control" id="sp-phone" placeholder="7XXXXXXXX" maxlength="12"
          style="border-radius:8px 0 0 8px; direction:ltr; letter-spacing:1px" inputmode="numeric">
      </div>
      <small style="color:var(--text-muted); font-size:.78rem">مثال: 771234567</small>
    </div>

    <div class="form-group">
      <label class="form-label">ملاحظات (اختياري)</label>
      <input type="text" class="form-control" id="sp-notes" placeholder="أي ملاحظة إضافية...">
    </div>

    <div style="background: linear-gradient(135deg, var(--primary), var(--primary-dark,#1a5276)); color:#fff; border-radius:10px; padding:14px; margin-bottom:14px; text-align:center">
      <div style="font-size:.85rem; opacity:.85">الإجمالي</div>
      <div style="font-size:2rem; font-weight:900" id="sp-total">0.00 ريال</div>
    </div>

    <button class="btn btn-success btn-block" onclick="executeSell()" style="font-size:1.05rem; padding:12px">
      <i class="fas fa-check-circle"></i> تأكيد البيع
    </button>
  </div>
</div>

<!-- ── Right: Sales History + Stats ──────────────────────────────────── -->
<div>

  <!-- Stats cards (shown when type selected) -->
  <div id="stats-cards" style="display:none; margin-bottom:16px">
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px">
      <div class="stat-card" style="background:linear-gradient(135deg,var(--primary),var(--primary-dark,#1a5276));color:#fff;border-radius:12px;padding:16px;text-align:center;box-shadow:var(--shadow)">
        <div style="font-size:1.8rem; font-weight:900" id="sc-total">0</div>
        <div style="font-size:.78rem; opacity:.85; margin-top:4px">إجمالي التذاكر</div>
      </div>
      <div class="stat-card" style="background:linear-gradient(135deg,var(--danger),#922b21);color:#fff;border-radius:12px;padding:16px;text-align:center;box-shadow:var(--shadow)">
        <div style="font-size:1.8rem; font-weight:900" id="sc-sold">0</div>
        <div style="font-size:.78rem; opacity:.85; margin-top:4px">المباعة</div>
      </div>
      <div class="stat-card" style="background:linear-gradient(135deg,var(--success),#1e8449);color:#fff;border-radius:12px;padding:16px;text-align:center;box-shadow:var(--shadow)">
        <div style="font-size:1.8rem; font-weight:900" id="sc-remain">0</div>
        <div style="font-size:.78rem; opacity:.85; margin-top:4px">المتبقية</div>
      </div>
    </div>
    <div style="background:var(--bg-card); border-radius:10px; padding:12px 16px; margin-top:12px; border:1px solid var(--border); display:flex; justify-content:space-between; align-items:center">
      <span style="color:var(--text-muted); font-size:.9rem">إجمالي الإيرادات من هذا النوع:</span>
      <span style="font-size:1.2rem; font-weight:900; color:var(--primary)" id="sc-revenue">0.00 ريال</span>
    </div>
  </div>

  <!-- Sales History table -->
  <div class="receipt-panel">
    <div class="receipt-header" style="display:flex; justify-content:space-between; align-items:center">
      <h4><i class="fas fa-history"></i> سجل المبيعات</h4>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
        <input type="date" id="filter-from" class="form-control" style="width:auto; background:rgba(255,255,255,.2); border-color:rgba(255,255,255,.3); color:#fff; font-size:.85rem"
          value="<?php echo date('Y-m-01'); ?>">
        <input type="date" id="filter-to" class="form-control" style="width:auto; background:rgba(255,255,255,.2); border-color:rgba(255,255,255,.3); color:#fff; font-size:.85rem"
          value="<?php echo date('Y-m-d'); ?>">
        <button class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4)" onclick="loadSales()">
          <i class="fas fa-filter"></i> تصفية
        </button>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table style="margin:0">
        <thead>
          <tr>
            <th style="width:60px">#</th>
            <th>نوع التذكرة</th>
            <th>رقم التذكرة</th>
            <th>السعر</th>
            <th>الكاشير</th>
            <th>الهاتف</th>
            <th>التاريخ</th>
            <th>ملاحظات</th>
          </tr>
        </thead>
        <tbody id="sales-tbody">
          <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted)">
            <div class="spinner border-primary" style="margin:0 auto 8px"></div> جاري التحميل...
          </td></tr>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 16px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center">
      <small id="sales-count" style="color:var(--text-muted)">-</small>
      <strong id="sales-total-rev" style="color:var(--primary)">-</strong>
    </div>
  </div>

</div>
</div>

<!-- ── Success Modal (after sell) ───────────────────────────────────── -->
<div class="modal-backdrop hidden" id="success-modal">
  <div class="modal" style="max-width:480px; text-align:center">
    <div class="modal-header" style="background:linear-gradient(135deg,var(--success),#1e8449); color:#fff">
      <h3 style="margin:0"><i class="fas fa-check-circle"></i> تمت عملية البيع</h3>
      <button class="modal-close" onclick="closeSuccessModal()" style="color:#fff">✕</button>
    </div>
    <div class="modal-body" id="success-modal-body" style="padding:24px"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeSuccessModal()">إغلاق</button>
    </div>
  </div>
</div>

<script>
let allTypes = [];
let selectedType = null;

function formatNum(n) { return new Intl.NumberFormat('en-US').format(parseInt(n) || 0); }
function formatMoney(n) {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(n) || 0);
}
function formatDate(s) {
  if (!s) return '-';
  const d = new Date(s);
  return d.toLocaleString('ar-SA', { year:'numeric', month:'numeric', day:'numeric', hour:'2-digit', minute:'2-digit' });
}

async function loadTypes() {
  const res = await apiCall('/api/tickets.php?action=get_types');
  if (!res.success) return;
  allTypes = res.data || [];
  renderTypes();
  loadSales();
}

function renderTypes() {
  const el = document.getElementById('types-list');
  if (!allTypes.length) {
    el.innerHTML = `<div style="text-align:center; padding:20px; color:var(--text-muted)">
      <i class="fas fa-info-circle" style="font-size:2rem; display:block; margin-bottom:8px; opacity:.4"></i>
      لا توجد أنواع تذاكر مضافة.<br>
      <a href="ticket_types.php" style="color:var(--primary)">أضف نوع تذكرة أولاً ←</a>
    </div>`;
    return;
  }

  el.innerHTML = allTypes
    .filter(t => t.is_active == 1)
    .map(t => {
      const remain = parseInt(t.remaining_count);
      const sold = parseInt(t.sold_count);
      return `
        <div class="type-selector-card ${selectedType && selectedType.id == t.id ? 'selected' : ''}"
             onclick="selectType(${t.id})">
          <div class="tsc-name"><i class="fas fa-ticket-alt" style="margin-left:6px;color:var(--primary)"></i>${t.name}</div>
          <div class="tsc-price">${formatMoney(t.price)} ريال</div>
          <div class="tsc-meta">
            <span class="tsc-badge">الإجمالي: ${formatNum(t.total_count)}</span>
            <span class="tsc-badge red">مباع: ${formatNum(sold)}</span>
            <span class="tsc-badge green">متبقي: ${formatNum(remain)}</span>
          </div>
        </div>`;
    }).join('');
}

function selectType(id) {
  selectedType = allTypes.find(t => t.id == id);
  renderTypes();

  if (!selectedType) return;

  const panel = document.getElementById('sell-panel');
  panel.style.display = 'block';
  document.getElementById('sp-name').textContent = selectedType.name;
  document.getElementById('sp-price').textContent = formatMoney(selectedType.price) + ' ريال للتذكرة';
  document.getElementById('sp-avail').textContent = `المتبقي: ${formatNum(selectedType.remaining_count)} تذكرة (${formatNum(selectedType.serial_from)} - ${formatNum(selectedType.serial_to)})`;
  document.getElementById('sp-qty').value = 1;
  updateTotal();

  // Load stats
  loadStats(id);
  loadSales();
}

async function loadStats(typeId) {
  const res = await apiCall('/api/tickets.php?action=stats&ticket_type_id=' + typeId);
  if (!res.success || !res.data) return;
  const d = res.data;

  document.getElementById('sc-total').textContent = formatNum(d.total_count);
  document.getElementById('sc-sold').textContent = formatNum(d.sold_count);
  document.getElementById('sc-remain').textContent = formatNum(d.remaining_count);
  document.getElementById('sc-revenue').textContent = formatMoney(d.total_revenue) + ' ريال';
  document.getElementById('stats-cards').style.display = 'block';
}

function changeQty(delta) {
  const input = document.getElementById('sp-qty');
  const newVal = Math.max(1, (parseInt(input.value) || 1) + delta);
  const max = selectedType ? parseInt(selectedType.remaining_count) : 9999;
  input.value = Math.min(newVal, max);
  updateTotal();
}

function updateTotal() {
  if (!selectedType) return;
  const qty = parseInt(document.getElementById('sp-qty').value) || 1;
  const total = qty * parseFloat(selectedType.price);
  document.getElementById('sp-total').textContent = formatMoney(total) + ' ريال';
}

async function executeSell() {
  if (!selectedType) { showToast('اختر نوع التذكرة أولاً', 'error'); return; }
  const qty = parseInt(document.getElementById('sp-qty').value) || 0;
  if (qty < 1) { showToast('الكمية يجب أن تكون على الأقل 1', 'error'); return; }
  if (qty > parseInt(selectedType.remaining_count)) {
    showToast('الكمية المطلوبة أكبر من المتاح', 'error'); return;
  }
  const notes = document.getElementById('sp-notes').value.trim();
  const phoneRaw = document.getElementById('sp-phone').value.trim().replace(/[^0-9]/g, '');
  const phone = phoneRaw ? ('+967' + phoneRaw) : '';

  const res = await apiCall('/api/tickets.php?action=sell', 'POST', {
    ticket_type_id: selectedType.id,
    quantity: qty,
    notes,
    phone
  });

  if (res.success) {
    const d = res.data;
    const serialsText = d.sold_serials.map(s => `<span style="display:inline-block;background:var(--primary);color:#fff;padding:4px 10px;border-radius:20px;margin:3px;font-weight:700;font-size:1.1rem">${s}</span>`).join('');
    document.getElementById('success-modal-body').innerHTML = `
      <div style="margin-bottom:16px">
        <div style="font-size:1.2rem; font-weight:700; margin-bottom:8px">${d.ticket_name}</div>
        <div style="font-size:2rem; font-weight:900; color:var(--success); margin-bottom:12px">${formatMoney(d.total_amount)} ريال</div>
        <div style="font-size:.9rem; color:var(--text-muted); margin-bottom:12px">أرقام التذاكر المباعة:</div>
        <div style="line-height:2.2">${serialsText}</div>
      </div>
    `;
    document.getElementById('success-modal').classList.remove('hidden');

    // Refresh
    document.getElementById('sp-qty').value = 1;
    document.getElementById('sp-notes').value = '';
    document.getElementById('sp-phone').value = '';
    await loadTypes();
    if (selectedType) {
      selectedType = allTypes.find(t => t.id == selectedType.id);
      if (selectedType) {
        loadStats(selectedType.id);
        document.getElementById('sp-avail').textContent = `المتبقي: ${formatNum(selectedType.remaining_count)} تذكرة (${formatNum(selectedType.serial_from)} - ${formatNum(selectedType.serial_to)})`;
        updateTotal();
      }
    }
    loadSales();
  } else {
    showToast(res.message || 'حدث خطأ أثناء البيع', 'error');
  }
}

function closeSuccessModal() {
  document.getElementById('success-modal').classList.add('hidden');
}
document.getElementById('success-modal').addEventListener('click', e => {
  if (e.target.id === 'success-modal') closeSuccessModal();
});

async function loadSales() {
  const tbody = document.getElementById('sales-tbody');
  const from = document.getElementById('filter-from').value;
  const to = document.getElementById('filter-to').value;

  let params = '?action=get_sales';
  if (selectedType) params += '&ticket_type_id=' + selectedType.id;
  if (from) params += '&from_date=' + from;
  if (to) params += '&to_date=' + to;

  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px"><div class="spinner border-primary" style="margin:0 auto 8px"></div></td></tr>';

  const res = await apiCall('/api/tickets.php' + params);
  if (!res.success) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> فشل تحميل البيانات</td></tr>';
    return;
  }

  const sales = res.data || [];
  document.getElementById('sales-count').textContent = `${sales.length} سجل`;

  const totalRev = sales.reduce((sum, s) => sum + parseFloat(s.sale_price || 0), 0);
  document.getElementById('sales-total-rev').textContent = 'الإجمالي: ' + formatMoney(totalRev) + ' ريال';

  if (!sales.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted)"><i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:8px; opacity:.4"></i>لا توجد مبيعات في هذه الفترة</td></tr>';
    return;
  }

  tbody.innerHTML = sales.map((s, i) => `
    <tr>
      <td style="color:var(--text-muted); font-size:.8rem">${i + 1}</td>
      <td><strong>${s.ticket_name}</strong></td>
      <td><span style="background:var(--primary); color:#fff; padding:3px 12px; border-radius:20px; font-weight:700; font-size:1rem">${s.serial_number}</span></td>
      <td style="color:var(--primary); font-weight:700">${formatMoney(s.sale_price)} ريال</td>
      <td><span class="badge" style="background:#eaf2ff; color:#1a5276; border:1px solid #bae6fd">${s.cashier_name || '-'}</span></td>
      <td style="direction:ltr; font-size:.85rem; font-weight:600">${s.phone ? `<a href="tel:${s.phone}" style="color:var(--primary);text-decoration:none"><i class="fas fa-phone" style="font-size:.75rem;margin-left:3px"></i>${s.phone}</a>` : '<span style="color:var(--text-muted)">-</span>'}</td>
      <td style="font-size:.82rem; color:var(--text-muted)">${formatDate(s.sold_at)}</td>
      <td style="font-size:.82rem">${s.notes || '<span style="color:var(--text-muted)">-</span>'}</td>
    </tr>
  `).join('');
}

document.addEventListener('DOMContentLoaded', loadTypes);
</script>

<?php adminFooter(); ?>
