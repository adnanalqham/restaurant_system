<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة الأقسام', 'departments');
$printers = json_decode(file_get_contents(APP_URL . '/api/printers.php?all=1'), true);
$printersList = isset($printers['data']) ? $printers['data'] : [];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  .modal { max-width: 500px; }
</style>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-building"></i> الأقسام (Departments)</h3>
    <button class="btn btn-primary btn-sm" onclick="openModal()"><i class="fas fa-plus"></i> إضافة قسم</button>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>الاسم العربي</th>
            <th>الاسم الإنجليزي</th>
            <th>الطابعة المسندة</th>
            <th>عنوان IP</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody id="dept-tbody">
          <tr><td colspan="7" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal-backdrop hidden" id="modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">إضافة قسم</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="dept-form">
        <input type="hidden" id="dept-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الاسم العربي *</label>
            <input type="text" class="form-control" id="dept-name-ar" placeholder="مثال: مطبخ" required>
          </div>
          <div class="form-group">
            <label class="form-label">الاسم الإنجليزي *</label>
            <input type="text" class="form-control" id="dept-name-en" placeholder="Kitchen" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">الطابعة المسندة</label>
          <select class="form-control" id="dept-printer">
            <option value="">-- بدون طابعة --</option>
            <?php foreach ($printersList as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['type']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small style="opacity:.6;font-size:.78rem">اختر الطابعة التي ستستلم طلبات هذا القسم</small>
        </div>
        <div class="form-group" id="status-group" style="display:none">
          <label class="form-label">الحالة</label>
          <select class="form-control" id="dept-status">
            <option value="1">نشط</option>
            <option value="0">معطّل</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveDepartment()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>

<script>
let departments = [];

async function loadDepartments() {
  const res = await apiCall('/api/departments.php?all=1');
  if (!res.success) return;
  departments = res.data;
  renderTable();
}

function renderTable() {
  const tbody = document.getElementById('dept-tbody');
  if (!departments.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><span class="icon"><i class="fas fa-building"></i></span><p>لا توجد أقسام</p></div></td></tr>';
    return;
  }
  tbody.innerHTML = departments.map((d, i) => `
    <tr>
      <td>${i + 1}</td>
      <td><strong>${d.name_ar}</strong></td>
      <td>${d.name_en}</td>
      <td>${d.printer_name ? `<span class="badge badge-info">${d.printer_name}</span>` : '<span style="opacity:.4">—</span>'}</td>
      <td><code>${d.printer_ip || '—'}</code></td>
      <td><span class="badge ${d.is_active == '1' ? 'badge-confirmed' : 'badge-cancelled'}">${d.is_active == '1' ? 'نشط' : 'معطّل'}</span></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editDepartment(${d.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteDepartment(${d.id}, '${d.name_ar}')"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');
}

function openModal(isEdit = false) {
  document.getElementById('modal-title').textContent = isEdit ? 'تعديل قسم' : 'إضافة قسم';
  document.getElementById('status-group').style.display = isEdit ? 'block' : 'none';
  document.getElementById('modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('modal').classList.add('hidden');
  document.getElementById('dept-id').value = '';
  document.getElementById('dept-form').reset();
}

function editDepartment(id) {
  const d = departments.find(x => x.id == id);
  if (!d) return;
  document.getElementById('dept-id').value = d.id;
  document.getElementById('dept-name-ar').value = d.name_ar;
  document.getElementById('dept-name-en').value = d.name_en;
  document.getElementById('dept-printer').value = d.printer_id || '';
  document.getElementById('dept-status').value = d.is_active;
  openModal(true);
}

async function saveDepartment() {
  const id = document.getElementById('dept-id').value;
  const data = {
    id: id || null,
    name_ar: document.getElementById('dept-name-ar').value,
    name_en: document.getElementById('dept-name-en').value,
    printer_id: document.getElementById('dept-printer').value || null,
    is_active: id ? document.getElementById('dept-status').value : 1
  };
  if (id) data.id = parseInt(id);

  const res = await apiCall('/api/departments.php', 'POST', data);
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) { closeModal(); loadDepartments(); }
}

async function deleteDepartment(id, name) {
  if (!confirmAction(`هل أنت متأكد من حذف القسم '${name}'؟`)) return;
  const res = await apiCall('/api/departments.php?action=delete', 'POST', {id: id});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadDepartments();
}

loadDepartments();
</script>
<?php adminFooter(); ?>
