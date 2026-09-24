<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إضافة التذاكر', 'ticket_types');
?>
<style>
.tickets-grid {
  display: grid;
  grid-template-columns: 400px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 992px) {
  .tickets-grid { grid-template-columns: 1fr; }
}
.ticket-card {
  background: var(--bg-card);
  border-radius: 14px;
  padding: 18px 20px;
  border: 1px solid var(--border);
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
  position: relative;
  transition: box-shadow .2s, transform .2s;
}
.ticket-card:hover {
  box-shadow: 0 6px 20px rgba(0,0,0,.08);
  transform: translateY(-2px);
}
.ticket-card .tc-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}
.ticket-card .tc-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--secondary);
}
.ticket-card .tc-price {
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--primary);
}
.ticket-card .tc-stats {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 8px;
  margin: 12px 0;
}
.tc-stat-item {
  text-align: center;
  background: var(--bg);
  border-radius: 8px;
  padding: 8px 4px;
}
.tc-stat-item .val {
  font-size: 1.3rem;
  font-weight: 900;
  display: block;
}
.tc-stat-item .lbl {
  font-size: .72rem;
  color: var(--text-muted);
  margin-top: 2px;
  display: block;
}
.progress-bar-wrap {
  background: var(--border);
  border-radius: 20px;
  height: 8px;
  overflow: hidden;
  margin: 8px 0 4px;
}
.progress-bar-fill {
  height: 100%;
  border-radius: 20px;
  transition: width .5s ease;
  background: linear-gradient(90deg, var(--success), var(--primary));
}
.badge-active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.badge-inactive { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>

<div class="tickets-grid">

<!-- ── Left: Add/Edit Form ────────────────────────────────────────────── -->
<div class="card" id="form-card">
  <div class="card-header">
    <h3><i class="fas fa-ticket-alt"></i> <span id="form-title">إضافة نوع تذكرة جديد</span></h3>
  </div>
  <div class="card-body">
    <input type="hidden" id="edit-id">

    <div class="form-group">
      <label class="form-label">اسم التذكرة *</label>
      <input type="text" class="form-control" id="f-name" placeholder="مثال: دخول المسبح، كأس العالم...">
    </div>

    <div class="form-group">
      <label class="form-label">سعر التذكرة (ريال) *</label>
      <input type="number" class="form-control" id="f-price" min="0.01" step="0.01" placeholder="0.00">
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
      <div class="form-group">
        <label class="form-label">رقم البداية *</label>
        <input type="number" class="form-control" id="f-from" min="1" placeholder="1000" oninput="calcTotal()">
      </div>
      <div class="form-group">
        <label class="form-label">رقم النهاية *</label>
        <input type="number" class="form-control" id="f-to" min="1" placeholder="2000" oninput="calcTotal()">
      </div>
    </div>

    <div id="calc-preview" style="display:none; background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:16px;">
      <div style="font-weight:600; margin-bottom:8px; color:var(--secondary)"><i class="fas fa-calculator"></i> الملخص التلقائي</div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:.9rem;">
        <div>إجمالي عدد التذاكر: <strong id="p-total" style="color:var(--primary)">0</strong></div>
        <div>رقم البداية: <strong id="p-from">-</strong></div>
        <div>رقم النهاية: <strong id="p-to">-</strong></div>
      </div>
    </div>

    <div class="form-group" id="status-group" style="display:none">
      <label class="form-label">الحالة</label>
      <select class="form-control" id="f-status">
        <option value="1">نشط</option>
        <option value="0">معطّل</option>
      </select>
    </div>

    <div style="display:flex; gap:10px; margin-top:8px;">
      <button class="btn btn-primary btn-block" onclick="saveTicketType()" id="save-btn">
        <i class="fas fa-save"></i> حفظ
      </button>
      <button class="btn btn-secondary" onclick="resetForm()" id="cancel-btn" style="display:none">
        إلغاء
      </button>
    </div>
  </div>
</div>

<!-- ── Right: Ticket Types List ──────────────────────────────────────── -->
<div>
  <div class="card-header" style="background:var(--bg-card); border-radius:12px 12px 0 0; border:1px solid var(--border); border-bottom:none; padding:14px 20px; display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0"><i class="fas fa-list"></i> أنواع التذاكر المعرَّفة</h3>
    <button class="btn btn-outline btn-primary btn-sm" onclick="loadTypes()">
      <i class="fas fa-sync"></i> تحديث
    </button>
  </div>
  <div id="types-container" style="border:1px solid var(--border); border-top:none; border-radius:0 0 12px 12px; padding:16px; background:var(--bg-card); min-height:200px;">
    <div style="text-align:center; padding:30px; color:var(--text-muted)">
      <div class="spinner border-primary" style="margin:0 auto 10px"></div>
      جاري تحميل البيانات...
    </div>
  </div>
</div>

</div>

<script>
function calcTotal() {
  const from = parseInt(document.getElementById('f-from').value);
  const to = parseInt(document.getElementById('f-to').value);
  const preview = document.getElementById('calc-preview');
  if (from > 0 && to > 0 && to >= from) {
    preview.style.display = 'block';
    document.getElementById('p-total').textContent = (to - from + 1).toLocaleString();
    document.getElementById('p-from').textContent = from.toLocaleString();
    document.getElementById('p-to').textContent = to.toLocaleString();
  } else {
    preview.style.display = 'none';
  }
}

function resetForm() {
  document.getElementById('edit-id').value = '';
  document.getElementById('f-name').value = '';
  document.getElementById('f-price').value = '';
  document.getElementById('f-from').value = '';
  document.getElementById('f-to').value = '';
  document.getElementById('calc-preview').style.display = 'none';
  document.getElementById('form-title').textContent = 'إضافة نوع تذكرة جديد';
  document.getElementById('save-btn').innerHTML = '<i class="fas fa-save"></i> حفظ';
  document.getElementById('cancel-btn').style.display = 'none';
  document.getElementById('status-group').style.display = 'none';
}

function editType(t) {
  document.getElementById('edit-id').value = t.id;
  document.getElementById('f-name').value = t.name;
  document.getElementById('f-price').value = t.price;
  document.getElementById('f-from').value = t.serial_from;
  document.getElementById('f-to').value = t.serial_to;
  document.getElementById('f-status').value = t.is_active;
  document.getElementById('status-group').style.display = 'block';
  document.getElementById('form-title').textContent = 'تعديل: ' + t.name;
  document.getElementById('save-btn').innerHTML = '<i class="fas fa-save"></i> حفظ التعديلات';
  document.getElementById('cancel-btn').style.display = '';
  calcTotal();
  document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
}

async function saveTicketType() {
  const id = document.getElementById('edit-id').value;
  const name = document.getElementById('f-name').value.trim();
  const price = parseFloat(document.getElementById('f-price').value);
  const from = parseInt(document.getElementById('f-from').value);
  const to = parseInt(document.getElementById('f-to').value);
  const is_active = document.getElementById('f-status').value;

  if (!name) { showToast('يرجى إدخال اسم التذكرة', 'error'); return; }
  if (!price || price <= 0) { showToast('يرجى إدخال سعر صحيح', 'error'); return; }
  if (!from || !to) { showToast('يرجى إدخال أرقام البداية والنهاية', 'error'); return; }
  if (from > to) { showToast('رقم البداية يجب أن يكون أصغر من رقم النهاية', 'error'); return; }

  const action = id ? 'edit_type' : 'add_type';
  const payload = { name, price, serial_from: from, serial_to: to };
  if (id) { payload.id = parseInt(id); payload.is_active = parseInt(is_active); }

  const res = await apiCall('/api/tickets.php?action=' + action, 'POST', payload);
  if (res.success) {
    showToast(res.message, 'success');
    resetForm();
    loadTypes();
  } else {
    showToast(res.message || 'حدث خطأ', 'error');
  }
}

async function deleteType(id, name) {
  if (!confirm(`هل تريد حذف نوع التذكرة "${name}"؟`)) return;
  const res = await apiCall('/api/tickets.php?action=delete_type', 'POST', { id });
  if (res.success) {
    showToast(res.message, 'success');
    loadTypes();
  } else {
    showToast(res.message, 'error');
  }
}

function formatNum(n) {
  return new Intl.NumberFormat('en-US').format(n);
}
function formatMoney(n) {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(n) || 0);
}

async function loadTypes() {
  const container = document.getElementById('types-container');
  container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted)"><div class="spinner border-primary" style="margin:0 auto 10px"></div> جاري التحميل...</div>';

  const res = await apiCall('/api/tickets.php?action=get_types');
  if (!res.success) {
    container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> فشل تحميل البيانات</div>';
    return;
  }

  if (!res.data || !res.data.length) {
    container.innerHTML = `
      <div style="text-align:center; padding:40px; color:var(--text-muted)">
        <i class="fas fa-ticket-alt" style="font-size:3rem; opacity:.3; display:block; margin-bottom:12px"></i>
        لا توجد أنواع تذاكر مضافة بعد.<br>
        <small>استخدم النموذج لإضافة نوع تذكرة جديد</small>
      </div>`;
    return;
  }

  container.innerHTML = res.data.map(t => {
    const total = parseInt(t.total_count);
    const sold = parseInt(t.sold_count);
    const remaining = parseInt(t.remaining_count);
    const pct = total > 0 ? Math.round((sold / total) * 100) : 0;
    const pctColor = pct >= 90 ? 'var(--danger)' : pct >= 60 ? 'var(--warning)' : 'var(--success)';
    const activeBadge = t.is_active == 1
      ? '<span class="badge badge-active">نشط</span>'
      : '<span class="badge badge-inactive">معطّل</span>';
    return `
      <div class="ticket-card mb-12">
        <div class="tc-header">
          <div>
            <div class="tc-name"><i class="fas fa-ticket-alt" style="color:var(--primary);margin-left:6px"></i>${t.name}</div>
            <div style="margin-top:4px">${activeBadge} <small style="color:var(--text-muted)">من ${formatNum(t.serial_from)} إلى ${formatNum(t.serial_to)}</small></div>
          </div>
          <div class="tc-price">${formatMoney(t.price)} ر</div>
        </div>
        <div class="tc-stats">
          <div class="tc-stat-item">
            <span class="val" style="color:var(--secondary)">${formatNum(total)}</span>
            <span class="lbl">الإجمالي</span>
          </div>
          <div class="tc-stat-item">
            <span class="val" style="color:var(--danger)">${formatNum(sold)}</span>
            <span class="lbl">المباع</span>
          </div>
          <div class="tc-stat-item">
            <span class="val" style="color:var(--success)">${formatNum(remaining)}</span>
            <span class="lbl">المتبقي</span>
          </div>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:${pct}%; background:linear-gradient(90deg, ${pctColor}, ${pctColor}cc)"></div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px">
          <small style="color:var(--text-muted)">${pct}% مباع</small>
          <div style="display:flex; gap:8px">
            <button class="btn btn-outline btn-primary btn-sm" onclick='editType(${JSON.stringify(t)})'>
              <i class="fas fa-edit"></i> تعديل
            </button>
            <button class="btn btn-outline btn-danger btn-sm" onclick="deleteType(${t.id}, '${t.name.replace(/'/g,"\\'")}')">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

document.addEventListener('DOMContentLoaded', loadTypes);
</script>

<?php adminFooter(); ?>
