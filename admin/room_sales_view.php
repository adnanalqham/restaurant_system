<?php
require_once __DIR__ . '/_layout.php';
adminHeader('مبيعات الغرف', 'room_sales_view');
?>

<!-- Room Sales Dashboard Summary Cards -->
<div class="stats-grid mb-16 no-print"
  style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
  
  <div class="stat-card"
    style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff; border-radius:12px; padding:15px 20px; text-align:center; box-shadow: var(--shadow);">
    <div class="stat-icon" style="font-size:2rem; margin-bottom:5px;"><i class="fas fa-coins"></i></div>
    <span class="stat-value" id="stats-today-rev" style="font-size:1.6rem; font-weight:900; display:block;">0.00 ريال</span>
    <div class="stat-label" style="font-size:.82rem; opacity:.85; margin-top:4px;">مبيعات الغرف (اليوم)</div>
  </div>

  <div class="stat-card info"
    style="background: linear-gradient(135deg, var(--info), #1a5276); color:#fff; border-radius:12px; padding:15px 20px; text-align:center; box-shadow: var(--shadow);">
    <div class="stat-icon" style="font-size:2rem; margin-bottom:5px;"><i class="fas fa-chart-line"></i></div>
    <span class="stat-value" id="stats-period-rev" style="font-size:1.6rem; font-weight:900; display:block;">0.00 ريال</span>
    <div class="stat-label" style="font-size:.82rem; opacity:.85; margin-top:4px;">إجمالي مبيعات الغرف (الفترة)</div>
  </div>

  <div class="stat-card success"
    style="background: linear-gradient(135deg, var(--success), #1e8449); color:#fff; border-radius:12px; padding:15px 20px; text-align:center; box-shadow: var(--shadow);">
    <div class="stat-icon" style="font-size:2rem; margin-bottom:5px;"><i class="fas fa-receipt"></i></div>
    <span class="stat-value" id="stats-orders-count" style="font-size:1.6rem; font-weight:900; display:block;">0</span>
    <div class="stat-label" style="font-size:.82rem; opacity:.85; margin-top:4px;">عدد طلبات الغرف</div>
  </div>

  <div class="stat-card warning"
    style="background: linear-gradient(135deg, var(--warning), #b7770d); color:#fff; border-radius:12px; padding:15px 20px; text-align:center; box-shadow: var(--shadow);">
    <div class="stat-icon" style="font-size:2rem; margin-bottom:5px;"><i class="fas fa-history"></i></div>
    <span class="stat-value" id="stats-active-count" style="font-size:1.6rem; font-weight:900; display:block;">0</span>
    <div class="stat-label" style="font-size:.82rem; opacity:.85; margin-top:4px;">طلبات الغرف النشطة (غير مدفوعة)</div>
  </div>
</div>

<div class="card mb-16 no-print">
  <div class="card-body"
    style="padding:14px 20px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; background: var(--bg-card); border-radius:15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 4px solid var(--primary);">
    <strong style="color:var(--secondary); font-size:1.05rem;"><i class="fas fa-filter"></i> تصفية مبيعات الغرف:</strong>
    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
      <input type="text" class="form-control" id="search-input" placeholder="بحث برقم الغرفة، اسم النزيل، أو رقم الطلب..." style="width:300px">
      
      <span style="color:var(--text-muted)">من</span>
      <input type="date" class="form-control" id="filter-from" style="width:auto"
        value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
      
      <span style="color:var(--text-muted)">إلى</span>
      <input type="date" class="form-control" id="filter-to" style="width:auto" value="<?php echo date('Y-m-d'); ?>">
      
      <button class="btn btn-primary btn-sm" onclick="loadRoomOrders()"><i class="fas fa-search"></i> تصفية</button>
    </div>
  </div>
</div>

<div class="card" style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px; border-top: 4px solid var(--primary);">
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table id="room-orders-table" style="margin:0">
        <thead>
          <tr>
            <th>رقم الغرفة</th>
            <th>اسم النزيل</th>
            <th>رقم الطلب</th>
            <th>تاريخ الطلب</th>
            <th>المسؤول عن الطلب</th>
            <th>تفاصيل الأصناف</th>
            <th>إجمالي المبلغ</th>
            <th>الحالة</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody id="room-orders-body">
          <tr>
            <td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted)">
              <div class="spinner border-primary" style="margin: 0 auto 10px auto;"></div>
              جاري تحميل البيانات...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal-backdrop hidden" id="order-detail-modal">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3 id="detail-order-number">تفاصيل الطلب</h3>
      <button class="modal-close" onclick="closeDetailModal()">✕</button>
    </div>
    <div class="modal-body" id="order-detail-body"></div>
    <div class="modal-footer" id="order-detail-actions">
      <button class="btn btn-secondary" onclick="closeDetailModal()">إغلاق</button>
    </div>
  </div>
</div>

<script>
  // Helper for money formatting (e.g. 10,000.00)
  function formatMoney(val) {
    let num = parseFloat(val) || 0;
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
  }

  // Helper for quantity formatting
  function formatQty(val) {
    let num = parseFloat(val) || 0;
    return Number.isInteger(num) ? num : num.toFixed(2);
  }

  // Formatting date to localized string
  function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleString('ar-YE', { 
      year: 'numeric', 
      month: 'numeric', 
      day: 'numeric', 
      hour: '2-digit', 
      minute: '2-digit',
      hour12: true 
    });
  }

  // Map database status to front-desk business flow state
  function getCustomStatusBadge(status) {
    const statusLabels = {
      'pending': { label: 'أرسل للكاشير', color: 'badge-pending' },
      'sent_to_cashier': { label: 'أرسل للكاشير', color: 'badge-pending' },
      'confirmed': { label: 'مدفوع ومؤكد', color: 'badge-confirmed' },
      'in_progress': { label: 'مدفوع ومؤكد', color: 'badge-confirmed' },
      'ready': { label: 'مدفوع ومؤكد', color: 'badge-confirmed' },
      'paid': { label: 'مدفوع ومؤكد', color: 'badge-paid' },
      'delivered': { label: 'مدفوع ومؤكد', color: 'badge-delivered' },
      'cancelled': { label: 'ملغي', color: 'badge-cancelled' },
      'refunded': { label: 'مسترجع', color: 'badge-cancelled' },
      'partially_refunded': { label: 'مسترجع جزئي', color: 'badge-warning' }
    };
    const state = statusLabels[status] || { label: status, color: '' };
    return `<span class="badge ${state.color}">${state.label}</span>`;
  }

  async function loadRoomOrders() {
    const search = document.getElementById('search-input').value.trim();
    const from = document.getElementById('filter-from').value;
    const to = document.getElementById('filter-to').value;
    
    let params = `?customer_type=room`;
    if (from) params += `&from_date=${from}`;
    if (to) params += `&to_date=${to}`;
    
    const body = document.getElementById('room-orders-body');
    body.innerHTML = `
      <tr>
        <td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted)">
          <div class="spinner border-primary" style="margin: 0 auto 10px auto;"></div>
          جاري تحميل البيانات...
        </td>
      </tr>
    `;

    try {
      const res = await apiCall('/api/orders.php' + params);
      if (res.success) {
        let orders = res.data;
        
        // Client-side search for room number, guest name, or order number
        if (search) {
          const query = search.toLowerCase();
          orders = orders.filter(o => 
            (o.customer_ref && o.customer_ref.toLowerCase().includes(query)) ||
            (o.guest_name && o.guest_name.toLowerCase().includes(query)) ||
            (o.order_number && o.order_number.toString().includes(query))
          );
        }

        // Calculate statistics based on current filtered list
        let todayRev = 0;
        let periodRev = 0;
        let activeCount = 0;

        // Fetch local today date string (YYYY-MM-DD)
        const dLocal = new Date();
        const offset = dLocal.getTimezoneOffset();
        const localDate = new Date(dLocal.getTime() - (offset * 60 * 1000));
        const todayStr = localDate.toISOString().split('T')[0];

        orders.forEach(o => {
          const isRefunded = o.status === 'refunded' || o.status === 'cancelled';
          const isPaid = o.paid_at !== null && !isRefunded;
          const netVal = parseFloat(o.total || 0) - parseFloat(o.refund_amount || 0);
          
          if (isPaid) {
            periodRev += netVal;
            if (o.created_at && o.created_at.startsWith(todayStr)) {
              todayRev += netVal;
            }
          }
          
          if (['pending', 'sent_to_cashier', 'confirmed', 'in_progress', 'ready'].includes(o.status)) {
            activeCount++;
          }
        });

        // Update statistics cards
        document.getElementById('stats-today-rev').textContent = formatMoney(todayRev) + ' ريال';
        document.getElementById('stats-period-rev').textContent = formatMoney(periodRev) + ' ريال';
        document.getElementById('stats-orders-count').textContent = orders.length;
        document.getElementById('stats-active-count').textContent = activeCount;
        
        if (orders.length === 0) {
          body.innerHTML = `
            <tr>
              <td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted)">
                <i class="fas fa-info-circle" style="font-size:2rem; margin-bottom:10px; display:block; color:var(--primary)"></i>
                لا توجد طلبات متطابقة مع خيارات البحث
              </td>
            </tr>
          `;
          return;
        }
        
        body.innerHTML = orders.map(o => {
          const netTotal = parseFloat(o.total || 0) - parseFloat(o.refund_amount || 0);
          
          return `
            <tr>
              <td><strong style="color:var(--primary); font-size:1.05rem">${o.customer_ref || '-'}</strong></td>
              <td><strong>${o.guest_name || '<span style="color:var(--text-muted)">غير مسجل</span>'}</strong></td>
              <td><strong style="color:var(--secondary)">#${o.order_number}</strong></td>
              <td>${formatDate(o.created_at)}</td>
              <td><span class="badge" style="background:#eaf2ff; color:#1a5276; border:1px solid #bae6fd; font-weight:600; font-size: 0.82rem; padding: 4px 8px">${o.direct_name || o.waiter_name || '-'}</span></td>
              <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${o.notes || ''}">
                ${o.notes ? `<i class="fas fa-sticky-note" style="color:var(--info); margin-left:4px"></i>` : ''}
                ${o.notes || ''}
              </td>
              <td style="color:var(--primary); font-weight:700">${formatMoney(netTotal)} ريال</td>
              <td>${getCustomStatusBadge(o.status)}</td>
              <td>
                <button class="btn btn-outline btn-primary btn-sm" onclick="viewOrder(${o.id})">
                  <i class="fas fa-eye"></i> تفاصيل
                </button>
              </td>
            </tr>
          `;
        }).join('');
      } else {
        body.innerHTML = `
          <tr>
            <td colspan="9" style="text-align:center; padding:30px; color:var(--danger)">
              <i class="fas fa-exclamation-triangle" style="font-size:2rem; margin-bottom:10px; display:block"></i>
              فشل تحميل البيانات: ${res.message || 'خطأ غير معروف'}
            </td>
          </tr>
        `;
      }
    } catch (e) {
      body.innerHTML = `
        <tr>
          <td colspan="9" style="text-align:center; padding:30px; color:var(--danger)">
            <i class="fas fa-exclamation-triangle" style="font-size:2rem; margin-bottom:10px; display:block"></i>
            حدث خطأ أثناء الاتصال بالخادم
          </td>
        </tr>
      `;
    }
  }

  async function viewOrder(id) {
    const res = await apiCall('/api/orders.php?action=single&id=' + id);
    if (!res.success) return;
    const o = res.data;
    document.getElementById('detail-order-number').textContent = 'تفاصيل الطلب #' + o.order_number;
    
    document.getElementById('order-detail-body').innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:12px">
        <div><strong>رقم الغرفة:</strong> <span style="font-size:1.1rem; color:var(--primary); font-weight:700">${o.customer_ref || '-'}</span></div>
        <div><strong>اسم النزيل:</strong> <span style="font-size:1.1rem; color:var(--secondary); font-weight:700">${o.guest_name || 'غير مسجل'}</span></div>
        <div><strong>رقم الطلب:</strong> #${o.order_number}</div>
        <div><strong>تاريخ الطلب:</strong> ${formatDate(o.created_at)}</div>
        <div><strong>الويتر (المنشئ):</strong> ${o.waiter_name || '-'}</div>
        <div><strong>المسؤول / المباشر:</strong> ${o.direct_name || '-'}</div>
        <div><strong>حالة الطلب:</strong> ${getCustomStatusBadge(o.status)}</div>
      </div>
      ${o.notes ? `<div class="alert alert-info" style="padding:10px 14px; margin-bottom:12px"><i class="fas fa-sticky-note"></i> <strong>ملاحظات:</strong> ${o.notes}</div>` : ''}
      <table style="margin-top:10px">
        <thead>
          <tr>
            <th>الصنف</th>
            <th>الكمية</th>
            <th>السعر</th>
            <th>الإجمالي</th>
          </tr>
        </thead>
        <tbody>
          ${o.items.map(i => `
            <tr>
              <td>${i.item_number ? '(' + i.item_number + ') ' : ''}${i.item_name_ar} ${i.status === 'rejected' ? '<span class="badge badge-danger" style="font-size:.7rem">مرفوض</span>' : ''}</td>
              <td>${formatQty(i.quantity)}</td>
              <td>${formatMoney(i.unit_price)} ريال</td>
              <td style="${i.status === 'rejected' ? 'text-decoration:line-through;color:var(--danger)' : ''}">${formatMoney(i.subtotal)} ريال</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
      <div class="receipt-row bold mt-12" style="font-size:1.05rem; display:flex; flex-direction:column; align-items:flex-end; gap:4px">
        <div>
          <span>إجمالي الطلب:</span>
          <span style="${o.refund_amount > 0 ? 'text-decoration:line-through;color:var(--text-muted)' : 'color:var(--primary); font-weight:700'}">${formatMoney(o.total)} ريال</span>
        </div>
        ${parseFloat(o.refund_amount || 0) > 0 ? `
        <div style="color:var(--danger)">
          <span>المرتجع:</span>
          <span>-${formatMoney(o.refund_amount)} ريال</span>
        </div>
        <div style="font-size:1.2rem; font-weight:900; color:var(--primary); margin-top:5px; border-top:2px dashed var(--border); padding-top:5px">
          <span>الصافي المطلوب ترحيله للغرفة:</span>
          <span>${formatMoney(parseFloat(o.total) - parseFloat(o.refund_amount))} ريال</span>
        </div>` : `
        <div style="font-size:1.2rem; font-weight:900; color:var(--primary); margin-top:5px; border-top:2px dashed var(--border); padding-top:5px">
          <span>الصافي المطلوب ترحيله للغرفة:</span>
          <span>${formatMoney(o.total)} ريال</span>
        </div>`}
      </div>
    `;
    document.getElementById('order-detail-modal').classList.remove('hidden');
  }

  function closeDetailModal() {
    document.getElementById('order-detail-modal').classList.add('hidden');
  }

  document.getElementById('order-detail-modal').addEventListener('click', e => { if (e.target.id === 'order-detail-modal') closeDetailModal(); });

  // Handle typing search with a small debounce/input listener
  document.getElementById('search-input').addEventListener('input', loadRoomOrders);

  // Initial load
  document.addEventListener('DOMContentLoaded', loadRoomOrders);
</script>

<?php adminFooter(); ?>
