<?php
require_once __DIR__ . '/_layout.php';
cashierHeader('مراقبة الطلبات', 'monitoring');
?>
<style>
  .cashier-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
    height: calc(100vh - 120px);
  }

  .orders-list-side {
    overflow-y: auto;
    padding-left: 10px;
  }

  .detail-body.loading {
    opacity: 0.4;
  }

  .detail-side {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--border);
  }

  .detail-header {
    padding: 15px 18px;
    background: var(--secondary);
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .detail-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    transition: opacity 0.2s ease;
  }

  .detail-actions {
    padding: 20px;
    border-top: 1px solid var(--border);
  }

  /* Unified and stable order-card styles */
  .orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
  }

  .order-card {
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    border: 2px solid transparent;
    border-right: 5px solid var(--primary);
    cursor: pointer;
    min-height: 200px;
    /* Prevent layout jitter */
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  .order-card.selected {
    border: 2px solid var(--primary);
    transform: translateX(-5px);
    z-index: 10;
    background: #fffdf5;
    box-shadow: 0 4px 15px rgba(230, 126, 34, 0.15);
  }

  .order-card.new {
    animation: slideInRight 0.5s ease backwards, pulse-new 2s infinite;
  }

  .order-card.fade-out {
    transform: scale(0.9);
    opacity: 0;
    pointer-events: none;
  }

  @keyframes pulse-new {

    0%,
    100% {
      box-shadow: var(--shadow);
    }

    50% {
      box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.2);
    }
  }

  .pulse-red {
    box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
    animation: pulse-red 2s infinite;
  }

  @keyframes pulse-red {
    0% {
      box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
    }

    70% {
      box-shadow: 0 0 0 6px rgba(231, 76, 60, 0);
    }

    100% {
      box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
    }
  }

  @media (max-width: 992px) {
    .cashier-grid {
      grid-template-columns: 1fr;
      height: auto;
    }
  }
</style>

<div class="cashier-grid">
  <!-- Right (Main): Pending orders -->
  <div class="orders-list-side">
    <!-- Header Row -->
    <div class="cashier-toolbar">
      <!-- Left: Title -->
      <div class="toolbar-title">
        <h2><i class="fas fa-inbox"></i> الطلبات الواردة</h2>
        <span id="total-count" class="badge badge-info"></span>
      </div>

      <!-- Right: Controls -->
      <div class="toolbar-controls">

        <!-- Search -->
        <div class="toolbar-search">
          <i class="fas fa-search"></i>
          <input type="text" id="search-input" class="toolbar-input" placeholder="بحث برقم الفاتورة..."
            oninput="debouncedLoadOrders()">
        </div>

        <!-- Status -->
        <select id="status-filter" class="toolbar-select" onchange="loadOrders()">
          <option value="active">الطلبات النشطة</option>
          <option value="sent_to_cashier">أُرسلت للكاشير</option>
          <option value="confirmed">مؤكّدة</option>
          <option value="in_progress">قيد التحضير</option>
          <option value="ready">جاهزة للاستلام</option>
          <option value="paid">تم الدفع</option>
          <option value="delivered">تم التسليم</option>
          <option value="">كل الحالات</option>
        </select>

        <!-- Filter Toggle -->
        <button id="toggle-filters-btn" class="toolbar-btn toolbar-btn-filter" onclick="toggleAdvancedFilters()">
          <i class="fas fa-sliders-h"></i>
          <span>فلتر</span>
          <span id="filter-active-dot"></span>
        </button>

        <!-- Reset -->
        <button id="reset-filters-btn" class="toolbar-btn toolbar-btn-reset" onclick="resetFilters()">
          <i class="fas fa-times"></i>
          <span>مسح</span>
        </button>

      </div>
    </div>

    <!-- Advanced Filters Panel -->
    <div id="advanced-filters-panel" class="filters-panel hidden-panel">
      <div class="filters-inner">

        <!-- Sale Type -->
        <div class="filter-group">
          <div class="filter-label"><i class="fas fa-tag"></i> نوع المبيعات</div>
          <div class="sale-type-group">
            <button class="sale-type-btn active" data-type="" onclick="setSaleType(this, '')">
              <i class="fas fa-th-list"></i> الكل
            </button>
            <button class="sale-type-btn" data-type="normal" onclick="setSaleType(this, 'normal')">
              <i class="fas fa-receipt"></i> عادية
            </button>
            <button class="sale-type-btn" data-type="staff" onclick="setSaleType(this, 'staff')">
              <i class="fas fa-user-tie"></i> موظفين
            </button>
            <button class="sale-type-btn" data-type="room" onclick="setSaleType(this, 'room')">
              <i class="fas fa-bed"></i> مبيعات خارجي (غرف)
            </button>
          </div>
        </div>

        <!-- Divider -->
        <div class="filter-divider"></div>

        <!-- Date Range -->
        <div class="filter-group">
          <div class="filter-label"><i class="fas fa-calendar-alt"></i> نطاق التاريخ</div>
          <div class="date-range-group">
            <div class="date-field">
              <span class="date-label">من</span>
              <input type="date" id="filter-date-from" class="toolbar-input" onchange="debouncedLoadOrders()">
            </div>
            <span class="date-arrow">←</span>
            <div class="date-field">
              <span class="date-label">إلى</span>
              <input type="date" id="filter-date-to" class="toolbar-input" onchange="debouncedLoadOrders()">
            </div>
          </div>
        </div>

      </div>
    </div>

    <style>
      /* ══════════════════════════════════════
         CASHIER TOOLBAR
      ══════════════════════════════════════ */
      .cashier-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
      }

      .toolbar-title {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .toolbar-title h2 {
        margin: 0;
        font-size: 1.1rem;
        white-space: nowrap;
      }

      .toolbar-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
      }

      /* Shared height for all controls */
      .toolbar-search,
      .toolbar-select,
      .toolbar-input,
      .toolbar-btn {
        height: 36px;
        box-sizing: border-box;
        font-size: .875rem;
        font-family: inherit;
        border-radius: 8px;
      }

      /* Search box */
      .toolbar-search {
        position: relative;
        display: flex;
        align-items: center;
      }

      .toolbar-search>i {
        position: absolute;
        right: 11px;
        color: var(--text-muted);
        font-size: .8rem;
        pointer-events: none;
      }

      .toolbar-search .toolbar-input {
        padding-right: 30px;
        min-width: 190px;
        border-radius: 8px;
      }

      /* Input base */
      .toolbar-input {
        border: 1.5px solid var(--border);
        background: var(--bg-card);
        color: var(--text);
        padding: 0 10px;
        outline: none;
        transition: border-color .15s;
      }

      .toolbar-input:focus {
        border-color: var(--primary);
      }

      /* Select */
      .toolbar-select {
        border: 1.5px solid var(--border);
        background: var(--bg-card);
        color: var(--text);
        padding: 0 10px;
        cursor: pointer;
        outline: none;
        appearance: auto;
      }

      .toolbar-select:focus {
        border-color: var(--primary);
      }

      /* Buttons */
      .toolbar-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0 14px;
        border: 1.5px solid transparent;
        cursor: pointer;
        font-weight: 500;
        transition: all .15s ease;
        white-space: nowrap;
      }

      .toolbar-btn-filter {
        background: var(--bg-card);
        border-color: var(--primary);
        color: var(--primary);
      }

      .toolbar-btn-filter:hover {
        background: var(--primary);
        color: #fff;
      }

      .toolbar-btn-filter.active-filter {
        background: var(--primary);
        color: #fff;
      }

      .toolbar-btn-reset {
        background: var(--bg-card);
        border-color: var(--border);
        color: var(--text-muted);
        display: none;
      }

      .toolbar-btn-reset:hover {
        border-color: var(--danger);
        color: var(--danger);
      }

      /* Filter dot indicator */
      #filter-active-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--danger);
        display: none;
        flex-shrink: 0;
      }

      /* ══════════════════════════════════════
         FILTERS PANEL
      ══════════════════════════════════════ */
      .filters-panel {
        background: var(--bg-card);
        border: 1.5px solid var(--border);
        border-radius: 10px;
        margin-bottom: 14px;
        overflow: hidden;
        transition: all .22s ease;
      }

      .filters-panel.hidden-panel {
        display: none;
      }

      .filters-inner {
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 0;
        flex-wrap: wrap;
      }

      .filter-group {
        display: flex;
        flex-direction: column;
        gap: 7px;
        padding: 0 16px;
      }

      .filter-group:first-child {
        padding-right: 0;
      }

      .filter-label {
        font-size: .78rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .03em;
        display: flex;
        align-items: center;
        gap: 5px;
      }

      .filter-label i {
        color: var(--primary);
      }

      .filter-divider {
        width: 1px;
        height: 48px;
        background: var(--border);
        flex-shrink: 0;
      }

      /* Sale type buttons */
      .sale-type-group {
        display: flex;
        gap: 6px;
        align-items: center;
      }

      .sale-type-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        height: 32px;
        padding: 0 12px;
        font-size: .8rem;
        font-family: inherit;
        font-weight: 500;
        border: 1.5px solid var(--border);
        border-radius: 6px;
        background: var(--bg);
        color: var(--text);
        cursor: pointer;
        transition: all .15s ease;
        white-space: nowrap;
      }

      .sale-type-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 6%, transparent);
      }

      .sale-type-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
        font-weight: 600;
      }
    </style>
    <div class="orders-grid" id="orders-grid">
      <div style="grid-column:1/-1;text-align:center;padding:60px">
        <div class="spinner"></div>
      </div>
    </div>
  </div>

  <!-- Left (Sidebar-like): Selected order details -->
  <div class="detail-side">
    <div class="detail-header">
      <span><i class="fas fa-file-invoice"></i> تفاصيل الطلب</span>
      <span id="selected-order-num" style="font-size:.85rem;opacity:.8"></span>
    </div>
    <div id="selected-order" class="detail-body">
      <div class="empty-state" style="padding:60px 20px">
        <span class="icon"><i class="fas fa-mouse-pointer"></i></span>
        <p>اختر طلباً لعرض تفاصيله</p>
      </div>
    </div>
    <div id="selected-actions" class="detail-actions" style="display:none">

      <!-- Paid Order Payment Method Display -->
      <div id="paid-payment-display-group" style="display:none; margin-bottom:12px;">
        <label class="form-label">طريقة الدفع الحالية</label>
        <div style="display:flex; align-items:center; justify-content:space-between; background:var(--bg); border:1px solid var(--border); padding:10px 14px; border-radius:8px">
          <span id="paid-payment-current-text" style="font-weight:700"></span>
          <button id="paid-payment-edit-btn" onclick="showPaidPaymentEditForm()" class="btn btn-sm btn-outline-primary" style="padding:4px 10px; font-size:0.8rem; display:none">
            <i class="fas fa-edit"></i> تعديل طريقة الدفع
          </button>
        </div>
      </div>

      <!-- Paid Order Edit Form (shown when clicking edit button) -->
      <div id="paid-payment-edit-form" style="display:none; margin-bottom:12px; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:12px">
        <div style="font-weight:700; margin-bottom:8px; font-size:.88rem; color:var(--primary)">
          <i class="fas fa-edit"></i> تعديل طريقة الدفع
        </div>
        <div class="form-group" style="margin-bottom:8px">
          <label class="form-label">طريقة الدفع الجديدة</label>
          <select id="edit-pay-method" class="form-control" onchange="onEditPayMethodChange()">
            <option value="cash">💵 نقداً</option>
            <option value="wallet">👛 محفظة رقمية</option>
          </select>
        </div>
        <div id="edit-pay-wallet-extra" style="display:none">
          <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">اختر المحفظة <span style="color:var(--danger)">*</span></label>
            <select id="edit-pay-wallet-select" class="form-control">
              <option value="">-- اختر المحفظة --</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">رقم مرجع التحويل <small style="color:var(--text-muted)">(اختياري)</small></label>
            <input type="text" id="edit-pay-ref" class="form-control" placeholder="مثال: TXN123456789">
          </div>
        </div>
        <div style="display:flex; gap:8px; margin-top:10px">
          <button onclick="saveEditPayment()" class="btn btn-success" style="flex:2; font-weight:700; padding:8px">
            <i class="fas fa-save"></i> حفظ التعديل
          </button>
          <button onclick="cancelEditPayment()" class="btn btn-outline-secondary" style="flex:1; padding:8px; background:#fff; border:1px solid var(--border); border-radius:6px; cursor:pointer">
            إلغاء
          </button>
        </div>
      </div>

      <!-- Cash payment method selector (hidden when wallet is pre-selected) -->
      <div class="form-group" id="cash-method-group">
        <label class="form-label">طريقة الدفع</label>
        <select id="payment-method" class="form-control" onchange="onCashierPayMethodChange()">
          <option value="cash">💵 نقداً</option>
          <option value="wallet">👛 محفظة رقمية</option>
        </select>
      </div>

      <div id="cashier-wallet-extra" style="display:none;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:8px">
          <label class="form-label">اختر المحفظة <span style="color:var(--danger)">*</span></label>
          <select id="cashier-wallet-select" class="form-control" onchange="onCashierWalletChange()">
            <option value="">-- اختر المحفظة --</option>
          </select>
        </div>
        <label class="form-label">رقم مرجع التحويل <small style="color:var(--text-muted)">(اختياري)</small></label>
        <input type="text" id="cashier-wallet-ref" class="form-control" placeholder="مثال: TXN123456789">
      </div>

      <!-- Wallet payment info + reference -->
      <div id="wallet-pay-group" style="display:none">
        <div
          style="background:var(--bg);border:2px solid var(--primary);border-radius:10px;padding:14px;margin-bottom:12px">
          <div style="font-weight:700;color:var(--primary);font-size:.95rem;margin-bottom:8px">
            <i class="fas fa-wallet"></i> الدفع عبر المحفظة الرقمية
          </div>
          <div id="wallet-pay-name" style="font-size:.9rem;color:var(--text-muted)"></div>
        </div>
        <div class="form-group">
          <label class="form-label">رقم مرجع التحويل <small style="color:var(--text-muted)">(اختياري)</small></label>
          <input type="text" id="wallet-ref" class="form-control" placeholder="مثال: TXN123456789">
        </div>
      </div>

      <!-- Financial / Split Payment Note -->
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">ملاحظات الدفع / الدفع المشترك <small style="color:var(--text-muted)">(تظهر في
            التقارير)</small></label>
        <div style="display:flex; gap:8px">
          <input type="text" id="payment-note" class="form-control" placeholder="مثال: دفع 2000 كاش و 3000 محفظة"
            style="flex:1">
          <button class="btn btn-primary" onclick="savePaymentNoteOnly()"
            style="padding: 6px 14px; font-size: .88rem; background: var(--primary); border-color: var(--primary); color: #fff;"
            title="حفظ الملاحظة"><i class="fas fa-save"></i> حفظ</button>
        </div>
      </div>

      <!-- Direct Staff / Waiter Selector -->
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label"><i class="fas fa-user-tag"></i> المباشر / الويتر <small
            style="color:var(--text-muted)">(اختياري)</small></label>
        <select id="direct-staff-select" class="form-control">
          <option value="">-- بدون تحديد مباشر --</option>
        </select>
      </div>

      <button id="cashier-confirm-btn" class="btn btn-success btn-block btn-lg" onclick="confirmOrder()"><i
          class="fas fa-check-circle"></i> تأكيد الدفع وتوزيع الطلب</button>



      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-top:8px">
        <button id="append-items-btn" class="btn btn-warning btn-sm" onclick="appendItemsToSelectedOrder()"><i
            class="fas fa-plus"></i> إضافة
          أصناف</button>
        <button class="btn btn-info btn-sm" onclick="openDiscountModal()"
          style="background: #6f42c1; border-color: #6f42c1; color: #fff"><i class="fas fa-edit"></i> تعديل (خصم / غرف /
          موظفين)</button>
      </div>

      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap:8px; margin-top:8px">
        <button id="print-receipt-btn" class="btn btn-outline btn-primary btn-sm" onclick="printSelectedReceipt()"
          style="display:none" disabled title="لا يمكن طباعة الفاتورة إلا بعد تأكيد الدفع"><i class="fas fa-lock"></i>
          فاتورة</button>
        <button id="cancel-order-btn" class="btn btn-danger btn-sm" onclick="cancelSelectedOrder()"><i
            class="fas fa-times-circle"></i>
          إلغاء</button>
        <button id="delete-order-btn" class="btn btn-sm" style="background:#e11d48; color:#fff; border:none; display:none" onclick="deleteSelectedOrder()"><i
            class="fas fa-trash-alt"></i>
          حذف كامل</button>
      </div>
    </div>
  </div>
</div>

<!-- Discount and Modify Modal -->
<div class="modal-backdrop hidden" id="discount-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h3>تعديل الطلب (خصم / مبيعات خاصة)</h3>
      <button class="modal-close" onclick="closeDiscountModal()">✕</button>
    </div>
    <div class="modal-body">
      <!-- Section 1: Special Sales -->
      <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
        <h4 style="margin-top:0; color:var(--primary); font-size:1rem; margin-bottom:10px;"><i class="fas fa-tag"></i>
          نوع المبيعات (اختياري)</h4>
        <div class="form-group">
          <label class="form-label">النوع</label>
          <select id="customer-type" class="form-control" onchange="updateCustomerTypeUI()">
            <option value="normal">مبيعات عادية</option>
            <option value="room">مبيعات خارجي (غرف)</option>
            <option value="staff">مبيعات موظفين</option>
          </select>
        </div>
        <div class="form-group" id="customer-ref-group" style="display:none">
          <label class="form-label" id="customer-ref-label">رقم المرجع</label>
          <input type="text" id="customer-ref" class="form-control" placeholder="">
        </div>
        <div class="form-group" id="guest-name-group" style="display:none">
          <label class="form-label" id="guest-name-label">اسم النزيل</label>
          <input type="text" id="guest-name" class="form-control" placeholder="أدخل اسم النزيل">
        </div>
      </div>

      <!-- Section 2: Discount -->
      <div>
        <h4 style="margin-top:0; color:var(--danger); font-size:1rem; margin-bottom:10px;"><i
            class="fas fa-percent"></i> الخصم (اختياري)</h4>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px">
          <div class="form-group">
            <label class="form-label" id="discount-label">مبلغ الخصم (ريال)</label>
            <input type="number" id="discount-amount" class="form-control" placeholder="0.00" step="0.01"
              oninput="calculateLiveDiscount()">
          </div>
          <div class="form-group">
            <label class="form-label">النوع</label>
            <select id="discount-type" class="form-control" onchange="updateDiscountLabel(); calculateLiveDiscount();">
              <option value="percent">% نسبة</option>
              <option value="fixed">ريال</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">سبب الخصم</label>
          <input type="text" id="discount-reason" class="form-control" placeholder="مثال: زبون VIP، تعويض...">
        </div>
        <div
          style="font-size: .85rem; color: var(--text-muted); background: #f8f9fa; padding: 10px; border-radius: 8px; margin-top: 10px;">
          <i class="fas fa-info-circle"></i> سيتم خصم هذا المبلغ من الإجمالي النهائي للطلب إن وجد.
        </div>

        <!-- Live Price Preview Box -->
        <div id="live-discount-box"
          style="margin-top: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px; font-size: 0.9rem; color: #166534;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <span>الإجمالي قبل الخصم:</span>
            <span id="live-orig-total" style="font-weight: 600;">0.00 ريال</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: var(--danger);">
            <span>قيمة الخصم المحسوبة:</span>
            <span id="live-disc-val" style="font-weight: 600;">-0.00 ريال</span>
          </div>
          <div
            style="display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: bold; border-top: 1px dashed #bbf7d0; padding-top: 6px; color: #14532d;">
            <span>الصافي بعد الخصم:</span>
            <span id="live-final-total">0.00 ريال</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeDiscountModal()">إلغاء</button>
        <button class="btn btn-primary" onclick="submitDiscount()">حفظ التعديلات</button>
      </div>
    </div>
  </div>


  <script>
    let ordersData = [], selectedOrderId = null, isProcessing = false;
    let loadOrdersTimer = null;
    let cashierWallets = [];
    let activeSaleType = ''; // '' = all, 'normal', 'staff', 'room'

    async function loadCashierWallets() {
      const res = await apiCall('/api/wallets.php');
      if (!res.success) return;
      cashierWallets = res.data;
      const sel = document.getElementById('cashier-wallet-select');
      if (!sel) return;
      sel.innerHTML = '<option value="">-- اختر المحفظة --</option>';
      cashierWallets.forEach(w => {
        const opt = document.createElement('option');
        opt.value = w.id;
        opt.dataset.name = w.name;
        opt.dataset.number = w.account_number;
        opt.textContent = w.name + ' — ' + w.account_number;
        sel.appendChild(opt);
      });
    }

    function onCashierWalletChange() {
      // No extra UI needed; wallet name is read from selected option on submit
    }

    function debouncedLoadOrders() {
      clearTimeout(loadOrdersTimer);
      loadOrdersTimer = setTimeout(loadOrders, 300);
    }

    // ── Advanced Filters ─────────────────────────────────────
    function toggleAdvancedFilters() {
      const panel = document.getElementById('advanced-filters-panel');
      panel.classList.toggle('hidden-panel');
    }

    function setSaleType(btn, type) {
      activeSaleType = type;
      document.querySelectorAll('.sale-type-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      updateFilterIndicator();
      loadOrders();
    }

    function updateFilterIndicator() {
      const dateFrom = document.getElementById('filter-date-from')?.value || '';
      const dateTo = document.getElementById('filter-date-to')?.value || '';
      const hasFilter = activeSaleType !== '' || dateFrom !== '' || dateTo !== '';
      const dot = document.getElementById('filter-active-dot');
      const resetBtn = document.getElementById('reset-filters-btn');
      if (dot) dot.style.display = hasFilter ? 'inline-block' : 'none';
      if (resetBtn) resetBtn.style.display = hasFilter ? '' : 'none';
    }

    function resetFilters() {
      activeSaleType = '';
      document.querySelectorAll('.sale-type-btn').forEach(b => b.classList.remove('active'));
      const allBtn = document.querySelector('.sale-type-btn[data-type=""]');
      if (allBtn) allBtn.classList.add('active');
      const dateFrom = document.getElementById('filter-date-from');
      const dateTo = document.getElementById('filter-date-to');
      if (dateFrom) dateFrom.value = '';
      if (dateTo) dateTo.value = '';
      updateFilterIndicator();
      loadOrders();
    }
    // ─────────────────────────────────────────────────────────

    async function loadOrders() {
      const status = document.getElementById('status-filter').value;
      const search = document.getElementById('search-input') ? document.getElementById('search-input').value.trim() : '';
      const dateFrom = document.getElementById('filter-date-from')?.value || '';
      const dateTo = document.getElementById('filter-date-to')?.value || '';

      let url = '/api/orders.php?limit=100';
      if (status) url += '&status=' + status;
      if (search) url += '&search=' + encodeURIComponent(search);
      if (activeSaleType) url += '&customer_type=' + encodeURIComponent(activeSaleType);
      if (dateFrom) url += '&from_date=' + encodeURIComponent(dateFrom);
      if (dateTo) url += '&to_date=' + encodeURIComponent(dateTo);

      updateFilterIndicator();

      const res = await apiCall(url);
      if (!res.success) return;
      ordersData = res.data;
      document.getElementById('total-count').textContent = ordersData.length + ' طلب';
      renderOrders();
    }

    function renderOrders() {
      const grid = document.getElementById('orders-grid');
      if (!ordersData.length) {
        grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><span class="icon"><i class="fas fa-check-double"></i></span><h3>لا توجد طلبات معلقة</h3></div>';
        return;
      }

      // Clear empty state if it exists
      const emptyState = grid.querySelector('.empty-state');
      if (emptyState) emptyState.remove();

      const currentIds = new Set(ordersData.map(o => String(o.id)));
      const existingCards = grid.querySelectorAll('.order-card');

      // 1. Remove cards that are no longer in the data
      existingCards.forEach(card => {
        const id = card.id.replace('order-card-', '');
        if (!currentIds.has(id)) {
          card.classList.add('fade-out');
          setTimeout(() => card.remove(), 300);
        }
      });



      // Helper for status badges (reusing or defining same logic)
      const getStatusBadge = (s) => {
        const map = { pending: 'badge-pending', sent_to_cashier: 'badge-sent', confirmed: 'badge-confirmed', in_progress: 'badge-in_progress', ready: 'badge-ready', paid: 'badge-paid', delivered: 'badge-delivered', cancelled: 'badge-cancelled' };
        const label = { pending: 'معلق', sent_to_cashier: 'أرسلت للكاشير', confirmed: 'مؤكدة', in_progress: 'قيد التحضير', ready: 'جاهزة', paid: 'مدفوعة', delivered: 'مسلمة', cancelled: 'ملغاة' };
        return `<span class="badge ${map[s] || ''}">${label[s] || s}</span>`;
      };

      // 2. Add or Update cards in current data order
      ordersData.forEach((o, index) => {
        let card = document.getElementById('order-card-' + o.id);
        const isNew = !card;
        const itemsHtml = (o.items || []).slice(0, 3).map(i => `<div class="order-item-line" style="display:flex; justify-content:space-between; font-size:.85rem; padding:4px 0; border-bottom:1px dashed var(--border);"><span>${i.item_number ? `[${i.item_number}] ` : ''}${i.item_name_ar} x${i.quantity}</span><span>${parseFloat(i.subtotal).toFixed(2)}</span></div>`).join('');

        const cardHtml = `
      <div class="order-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 10px">
        <div style="display:flex; align-items:center; gap:6px">
          <strong style="color: var(--primary); font-size: 1.1rem">#${o.order_number}</strong>
          ${(o.print_count > 0 || o.kitchen_print_count > 0) ? `
             <div style="font-size: 0.75rem; display:inline-block; margin-right:8px;">
               ${o.print_count > 0 ? `<span title="طباعة كاشير" style="background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:4px;margin-left:4px;border:1px solid #bae6fd"><i class="fas fa-print"></i> ك:${o.print_count}</span>` : ''}
               ${o.kitchen_print_count > 0 ? `<span title="طباعة مطبخ" style="background:#ffedd5;color:#c2410c;padding:2px 6px;border-radius:4px;border:1px solid #fed7aa"><i class="fas fa-print"></i> م:${o.kitchen_print_count}</span>` : ''}
             </div>
          ` : ''}
          ${(o.items || []).some(i => i.status === 'rejected') ? '<span class="pulse-red" title="يوجد أصناف مرفوضة" style="width:10px; height:10px; background:var(--danger); border-radius:50%; display:inline-block"></span>' : ''}
        </div>
        ${o.table_number ? `<span class="table-badge" style="background: var(--secondary); padding: 4px 12px; font-size: .85rem">طاولة ${o.table_number}</span>` : ''}
        ${getStatusBadge(o.status)}
      </div>
      <div style="font-size: .82rem; color: var(--text-muted); margin-bottom: 8px">
        <i class="far fa-clock"></i> ${formatDate(o.created_at)} &nbsp;|&nbsp; <i class="fas fa-user"></i> ${o.waiter_name}
      </div>
      <div class="order-items-preview" style="flex:1; margin-bottom: 10px">
        ${itemsHtml}
        ${o.item_count > 3 ? `<span style="color:var(--primary); font-size:.8rem">+ ${o.item_count - 3} أصناف أخرى</span>` : ''}
      </div>
      <div class="order-card-footer" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:10px;">
        <strong style="color:var(--primary)">${parseFloat(o.total - (o.refund_amount || 0)).toFixed(2)} ريال</strong>
        <span class="badge ${o.id == selectedOrderId ? 'badge-confirmed' : 'badge-sent'}" id="order-badge-${o.id}">
          <i class="fas fa-eye"></i> ${o.id == selectedOrderId ? 'محدد' : 'للتفاصيل'}
        </span>
      </div>
    `;

        if (isNew) {
          const temp = document.createElement('div');
          temp.id = 'order-card-' + o.id;
          temp.className = 'order-card new ' + (o.id == selectedOrderId ? 'selected' : '');
          temp.onclick = () => selectOrder(o.id);
          temp.innerHTML = cardHtml;

          // Maintain order
          if (index === 0) {
            grid.prepend(temp);
          } else {
            const prevCard = document.getElementById('order-card-' + ordersData[index - 1].id);
            if (prevCard && prevCard.nextSibling) {
              grid.insertBefore(temp, prevCard.nextSibling);
            } else {
              grid.appendChild(temp);
            }
          }
          // Remove 'new' class after animation
          setTimeout(() => temp.classList.remove('new'), 2000);
        } else {
          card.classList.remove('fade-out'); // FIX: Ensure visibility
          const currentContent = card.innerHTML;
          if (currentContent !== cardHtml) {
            card.innerHTML = cardHtml;
            // Re-apply classes if needed
            if (o.id == selectedOrderId) card.classList.add('selected');
            else card.classList.remove('selected');
          }
        }
      });
    }

    async function selectOrder(id) {
      const oldId = selectedOrderId;
      selectedOrderId = id;

      // Instant selection highlight in the list without re-rendering everything
      if (oldId && document.getElementById('order-card-' + oldId)) {
        const oldCard = document.getElementById('order-card-' + oldId);
        oldCard.classList.remove('selected');
        const oldBadge = document.getElementById('order-badge-' + oldId);
        if (oldBadge) {
          oldBadge.classList.replace('badge-confirmed', 'badge-sent');
          oldBadge.textContent = 'انقر للتفاصيل';
        }
      }

      const newCard = document.getElementById('order-card-' + id);
      if (newCard) {
        newCard.classList.add('selected');
        const newBadge = document.getElementById('order-badge-' + id);
        if (newBadge) {
          newBadge.classList.replace('badge-sent', 'badge-confirmed');
          newBadge.textContent = 'محدد';
        }
      }

      const detailBody = document.getElementById('selected-order');
      detailBody.classList.add('loading');

      const res = await apiCall('/api/orders.php?action=single&id=' + id);
      if (!res.success) {
        detailBody.classList.remove('loading');
        if (res.message) showToast(res.message, 'danger');
        return;
      }
      const o = res.data;

      // Extract payment note if it exists in o.notes
      let payNoteValue = '';
      if (o.notes) {
        const match = o.notes.match(/ملاحظة الدفع:\s*([^|]+)/);
        if (match) {
          payNoteValue = match[1].trim();
        }
      }
      if (document.getElementById('payment-note')) {
        document.getElementById('payment-note').value = payNoteValue;
      }

      const hasUnconfirmed = o.items.some(i => parseInt(i.is_confirmed || 0) === 0);

      document.getElementById('selected-order-num').textContent = '#' + o.order_number;

      // Payment method display
      var payDisplay = '';
      if (o.payment_method === 'wallet') {
        payDisplay = '<i class="fas fa-wallet" style="color:var(--primary)"></i> ' + (o.wallet_name || 'محفظة');
      } else {
        payDisplay = '<i class="fas fa-money-bill-wave" style="color:var(--success)"></i> نقداً';
      }

      if (o.payment_reference) {
        payDisplay += ' <small style="color:var(--text-muted)">[مرجع: ' + o.payment_reference + ']</small>';
      }

      const canEditPayment = parseInt(o.is_paid_once || 0) === 1 && !(o.is_closed_shift && !o.bypass_shift_lock);
      if (parseInt(o.is_payment_modified || 0) === 1) {
        payDisplay += ' <span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:4px;padding:2px 6px;font-size:.75rem;font-weight:700;margin-right:4px"><i class="fas fa-pen" style="font-size:.7rem"></i> معدّل</span>';
      }

      let shiftAlertHtml = '';
      if (o.is_closed_shift) {
        if (!o.bypass_shift_lock) {
          shiftAlertHtml = `
            <div style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:12px; font-weight:bold; text-align:center; font-size:0.95rem">
              <i class="fas fa-lock"></i> هذا الطلب ينتمي لشفت مغلق ومقفل بالكامل 🔒
            </div>
          `;
        } else {
          shiftAlertHtml = `
            <div style="background:#fef9c3; border:1px solid #fef08a; color:#854d0e; padding:12px; border-radius:8px; margin-bottom:12px; font-weight:bold; text-align:center; font-size:0.95rem">
              <i class="fas fa-unlock"></i> طلب في شفت مغلق (مسموح بالتعديل لتوافر الصلاحية) 🔓
            </div>
          `;
        }
      }

      let additionsBreakdownHtml = '';
      if (parseInt(o.is_paid_once || 0) === 1 && hasUnconfirmed) {
        const paidTotal = parseFloat(o.db_total || 0);
        const combinedTotal = parseFloat(o.total || 0);
        const pendingTotal = combinedTotal - paidTotal;
        additionsBreakdownHtml = `
          <div style="background:#fff9db; border:1px solid #ffe066; padding:10px 14px; border-radius:6px; margin: 10px 14px; font-size:.88rem">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px">
                <span style="color:#495057; font-weight:600"> إجمالي الطلب المدفوع سابقاً:</span>
                <span style="font-weight:700; color:#2b8a3e">${paidTotal.toFixed(2)} ريال</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px">
                <span style="color:#495057; font-weight:600"> قيمة الإضافات المعلقة:</span>
                <span style="font-weight:700; color:#f08c00">+ ${pendingTotal.toFixed(2)} ريال</span>
            </div>
            <div style="border-top:1px dashed #ffe066; margin:6px 0; padding-top:6px; display:flex; justify-content:space-between; font-weight:bold">
                <span style="color:#212529"> الإجمالي بعد اعتماد الإضافات:</span>
                <span style="color:#d9480f">${combinedTotal.toFixed(2)} ريال</span>
            </div>
          </div>
        `;
      }

      document.getElementById('selected-order').innerHTML = `
    ${shiftAlertHtml}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;font-size:.9rem">
      <div><strong>الطاولة:</strong> ${o.table_number || 'غير محدد'}</div>
      <div><strong>الحالة:</strong> ${statusBadge(o.status)}</div>
      <div><strong>الويتر (مُدخل الطلب):</strong> ${o.waiter_name}</div>
      <div><strong>المباشر (متابع الطلب):</strong> ${o.direct_name || 'غير محدد'}</div>
      <div><strong>الكاشير (مؤكد الطلب):</strong> ${o.cashier_name || 'لم يؤكد بعد'}</div>
      <div><strong>الوقت:</strong> ${formatDate(o.created_at)}</div>
      <div style="grid-column:1/-1">
        <strong>الدفع:</strong> ${payDisplay}
      </div>
    </div>

    ${o.notes ? `<div class="alert alert-info" style="margin-bottom:12px;font-size:.88rem"><i class="fas fa-sticky-note"></i> ${o.notes}</div>` : ''}
    <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden">
      ${o.items.map(i => {
        const isPending = parseInt(i.is_confirmed || 0) === 0;
        const bgStyle = isPending ? 'background:#fffbf0; border-left: 3px solid #f08c00;' : '';
        const pendingBadge = isPending ? '<span class="badge" style="background:#f08c00; color:#fff; font-weight:bold; font-size:.75rem; padding:2px 6px; border-radius:4px; margin-right:5px">إضافة معلقة ⏳</span>' : '';
        return `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border);font-size:.88rem;${bgStyle}">
          <div>
            <div style="font-weight:600">${i.item_number ? `<span style="color:var(--primary)">[${i.item_number}]</span> ` : ''}${i.item_name_ar} ${i.status === 'rejected' ? '<span class="badge badge-danger">مرفوض ❌</span>' : ''} ${pendingBadge}</div>
            <small style="color:var(--text-muted)">${i.cat_name_ar} | x${i.quantity}</small>
            ${i.notes ? `<div style="font-size:.76rem;color:var(--warning)">📝 ${i.notes}</div>` : ''}
            ${i.rejection_reason ? `<div style="font-size:.76rem;color:var(--danger);font-weight:700">⚠️ السبب: ${i.rejection_reason}</div>` : ''}
          </div>
          <div style="display:flex;align-items:center;gap:12px">
            <div style="font-weight:700;color:var(--primary)">${parseFloat(i.subtotal).toFixed(2)}</div>
            ${((o.is_closed_shift && !o.bypass_shift_lock) || (o.paid_at !== null && isPending !== true) || ['delivered', 'cancelled'].includes(o.status) || (parseInt(o.is_paid_once || 0) === 1 && parseInt(i.is_appended || 0) !== 1 && isPending !== true)) ? '' : `<button class="btn btn-sm btn-danger" style="padding:4px 8px" onclick="deleteItem(${o.id}, ${i.id})" title="حذف الصنف"><i class="fas fa-trash"></i></button>`}
          </div>
        </div>`;
      }).join('')}
      
      <div style="padding:10px 14px; background:#fdfdfd; border-bottom:1px solid var(--border); font-size:.85rem">
        <div style="display:flex; justify-content:space-between; margin-bottom:4px">
            <span>المجموع الفرعي:</span>
            <span>${parseFloat(o.subtotal).toFixed(2)}</span>
        </div>
        ${o.service_charge > 0 ? `
        <div style="display:flex; justify-content:space-between; margin-bottom:4px">
            <span>رسوم الخدمة:</span>
            <span>${parseFloat(o.service_charge).toFixed(2)}</span>
        </div>` : ''}
        ${o.tax > 0 ? `
        <div style="display:flex; justify-content:space-between; margin-bottom:4px">
            <span>الضريبة:</span>
            <span>${parseFloat(o.tax).toFixed(2)}</span>
        </div>` : ''}
        ${o.manual_discount > 0 ? `
        <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:var(--danger); font-weight:600">
            <span>الخصم اليدوي (${o.discount_reason || ''}):</span>
            <span>-${parseFloat(o.manual_discount).toFixed(2)}</span>
        </div>` : ''}
      </div>
      ${additionsBreakdownHtml}
      <div style="padding:12px 14px;background:#fafafa;display:flex;justify-content:space-between;font-weight:900;font-size:1.1rem">
        <span>صافي الإجمالي</span>
        <span style="color:var(--primary)">${parseFloat(o.total - (o.refund_amount || 0)).toFixed(2)} ريال</span>
      </div>
    </div>
    ${(() => {
          const rejectedItems = o.items.filter(i => i.status === 'rejected');
          if (rejectedItems.length === 0 || parseInt(o.is_paid_once || 0) === 0) return '';
          const refundSum = rejectedItems.reduce((acc, i) => acc + parseFloat(i.subtotal), 0);
          const isFull = rejectedItems.length === o.items.length;
          const statusToSet = isFull ? 'refunded' : 'partially_refunded';

          return `
        <div style="background:var(--danger-light); color:var(--danger); padding:12px; border-radius:8px; margin-top:15px; border:1px solid var(--danger)">
          <div style="font-weight:bold; font-size:1.05rem; margin-bottom:5px; text-align:center">
            ⚠️ ${isFull ? 'طلب مرفوض بالكامل' : 'يوجد أصناف مرفوضة'}
          </div>
          <div style="margin-bottom:10px; font-size:.9rem; text-align:center">
            قيمة المرتجع: <strong>${refundSum.toFixed(2)} ريال</strong> 
            ${!isFull ? `<br><small style="color:var(--text-muted)">سيتم خصمها من الإجمالي ليكون الصافي: ${(o.total - refundSum).toFixed(2)}</small>` : ''}
          </div>
          ${(o.status !== 'refunded' && o.status !== 'partially_refunded' && !(o.is_closed_shift && !o.bypass_shift_lock)) ? `
          <button class="btn btn-danger btn-block" onclick="refundSelectedOrder('${statusToSet}')">
            <i class="fas fa-undo"></i> تأكيد إرجاع المبلغ للزبون
          </button>
          ` : (o.is_closed_shift && !o.bypass_shift_lock ? '' : '<div style="text-align:center; color:var(--success); font-weight:bold; margin-top:10px"><i class="fas fa-check-circle"></i> تم إرجاع المبلغ مسبقاً</div>')}
        </div>
      `;
        })()}
  `;

      const btn = document.getElementById('cashier-confirm-btn');
      const isPaid = o.paid_at !== null || ['cancelled', 'refunded', 'delivered'].includes(o.status);
      if (o.is_closed_shift && !o.bypass_shift_lock) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-lock"></i> الشفت مغلق ومقفل 🔒';
        btn.classList.remove('btn-success', 'btn-warning');
        btn.classList.add('btn-secondary');
      } else if (isPaid && !hasUnconfirmed) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-check-double"></i> تم الدفع مسبقاً ✅';
        btn.classList.remove('btn-success', 'btn-warning');
        btn.classList.add('btn-secondary');
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> تأكيد الدفع وتوزيع الطلب';
        btn.classList.remove('btn-secondary', 'btn-warning');
        btn.classList.add('btn-success');
      }

      // ── Print receipt button: only show for paid orders if allowed ──
      const printBtn = document.getElementById('print-receipt-btn');
      const appendBtn = document.getElementById('append-items-btn');
      if (printBtn) {
        if (window.POS_USER && window.POS_USER.can_print && o.status === 'paid' && !(o.is_closed_shift && !o.bypass_shift_lock)) {
          // Order is paid → show receipt btn
          printBtn.style.display = '';
          printBtn.disabled = false;
          printBtn.title = 'طباعة الفاتورة';
          printBtn.innerHTML = '<i class="fas fa-print"></i> فاتورة';
        } else {
          printBtn.style.display = 'none';
          printBtn.disabled = true;
        }
      }

      // ── Cancel button: only show if status is pending and user has permission ──
      const cancelBtn = document.getElementById('cancel-order-btn');
      if (cancelBtn) {
        const userPerms = (window.POS_USER && window.POS_USER.permissions) || [];
        const hasCancelPerm = (window.POS_USER && window.POS_USER.role === 'admin') || userPerms.includes('cancel_pending_orders');
        if (o.status === 'pending' && hasCancelPerm) {
          cancelBtn.style.display = '';
          cancelBtn.disabled = false;
        } else {
          cancelBtn.style.display = 'none';
          cancelBtn.disabled = true;
        }
      }

      // ── Delete button: show if admin OR cashier, but only if the order has NEVER been paid ──
      const deleteBtn = document.getElementById('delete-order-btn');
      if (deleteBtn) {
        const isAdmin = window.POS_USER && window.POS_USER.role === 'admin';
        const isCashier = window.POS_USER && window.POS_USER.role === 'cashier';
        const isNeverPaid = parseInt(o.is_paid_once || 0) === 0;
        const isShiftLocked = o.is_closed_shift && !o.bypass_shift_lock;

        if ((isAdmin || isCashier) && isNeverPaid && !isShiftLocked) {
          deleteBtn.style.display = '';
          deleteBtn.disabled = false;
        } else {
          deleteBtn.style.display = 'none';
          deleteBtn.disabled = true;
        }
      }

      // Show append button if shift is not closed or user has bypass permission
      if (appendBtn) {
        if (o.is_closed_shift && !o.bypass_shift_lock) {
          appendBtn.style.display = 'none';
        } else {
          appendBtn.style.display = '';
        }
      }

      // Show UI based on order's payment_method
      var walletGroup = document.getElementById('wallet-pay-group');
      var cashGroup = document.getElementById('cash-method-group');
      var walletExtra = document.getElementById('cashier-wallet-extra');

      const isPaidOnce = parseInt(o.is_paid_once || 0) === 1;
      const displayGroup = document.getElementById('paid-payment-display-group');
      const editForm = document.getElementById('paid-payment-edit-form');

      if (isPaidOnce) {
        // Hide normal payment options
        walletGroup.style.display = 'none';
        cashGroup.style.display = 'none';
        walletExtra.style.display = 'none';

        // Show display group, hide form initially
        displayGroup.style.display = 'block';
        editForm.style.display = 'none';

        // Set current payment text
        const currentText = o.payment_method === 'wallet'
          ? '👛 محفظة رقمية (' + (o.wallet_name || 'محفظة') + ')' + (o.payment_reference ? ' [مرجع: ' + o.payment_reference + ']' : '')
          : '💵 نقداً';
        document.getElementById('paid-payment-current-text').textContent = currentText;

        // Check permission (edit_paid_payment)
        const userPerms = (window.POS_USER && window.POS_USER.permissions) || [];
        const hasEditPaidPerm = (window.POS_USER && window.POS_USER.role === 'admin') || userPerms.includes('edit_paid_payment');

        const editBtn = document.getElementById('paid-payment-edit-btn');
        if (editBtn) {
          editBtn.style.display = hasEditPaidPerm ? 'inline-block' : 'none';
        }

        // Pre-populate editing inputs
        const editMethod = document.getElementById('edit-pay-method');
        if (editMethod) {
          editMethod.value = o.payment_method === 'wallet' ? 'wallet' : 'cash';
          onEditPayMethodChange();
        }

        const editWalletSel = document.getElementById('edit-pay-wallet-select');
        if (editWalletSel) {
          editWalletSel.innerHTML = '<option value="">-- اختر المحفظة --</option>';
          cashierWallets.forEach(w => {
            const opt = document.createElement('option');
            opt.value = w.id;
            opt.dataset.name = w.name;
            opt.textContent = w.name + (w.account_number ? ' (' + w.account_number + ')' : '');
            if (o.wallet_name && o.wallet_name.includes(w.name)) opt.selected = true;
            editWalletSel.appendChild(opt);
          });
        }

        const editRef = document.getElementById('edit-pay-ref');
        if (editRef) editRef.value = o.payment_reference || '';

      } else {
        // Hide display and edit groups
        displayGroup.style.display = 'none';
        editForm.style.display = 'none';

        if (o.payment_method === 'wallet' && o.wallet_name && o.wallet_name.trim() !== '') {
          walletGroup.style.display = 'block';
          cashGroup.style.display = 'none';
          walletExtra.style.display = 'none';
          document.getElementById('wallet-ref').value = o.payment_reference || '';
          document.getElementById('wallet-pay-name').innerHTML =
            '<i class="fas fa-wallet" style="color:var(--primary)"></i> ' +
            o.wallet_name +
            '<br><small>الرجاء التأكد من رقم مرجع التحويل أدناه</small>';
        } else {
          walletGroup.style.display = 'none';
          cashGroup.style.display = 'block';
          walletExtra.style.display = 'none';
          document.getElementById('payment-method').value = o.payment_method === 'wallet' ? 'wallet' : 'cash';
          onCashierPayMethodChange();
          if (o.payment_method === 'wallet') {
            document.getElementById('cashier-wallet-ref').value = o.payment_reference || '';
          }
        }
      }

      document.getElementById('selected-actions').style.display = 'block';

      // Small delay to make the transition feel natural
      setTimeout(() => {
        detailBody.classList.remove('loading');
      }, 50);
    }

    function onCashierPayMethodChange() {
      const method = document.getElementById('payment-method').value;
      document.getElementById('cashier-wallet-extra').style.display = (method === 'wallet') ? 'block' : 'none';
      if (method === 'wallet') {
        document.getElementById('cashier-wallet-select').value = '';
      }
    }

    async function deleteItem(orderId, itemId) {
      const confirmed = await systemConfirm('هل أنت متأكد من حذف هذا الصنف من الفاتورة؟ سيتم إعادة حساب الفاتورة تلقائياً.');
      if (!confirmed) return;
      const res = await apiCall('/api/orders.php?action=delete_item', 'POST', { order_id: orderId, item_id: itemId });
      showToast(res.message, res.success ? 'success' : 'danger');
      if (res.success) {
        loadOrders();
        selectOrder(orderId);
      }
    }


    async function confirmOrder() {
      if (!selectedOrderId || isProcessing) return;

      const btn = document.getElementById('cashier-confirm-btn');
      const originalHtml = btn.innerHTML;

      try {
        isProcessing = true;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التأكيد...';

        var walletGroup = document.getElementById('wallet-pay-group');
        var isPreSelectedWallet = walletGroup.style.display !== 'none';

        var method = 'cash';
        var ref = '';
        var walletId = null;
        var walletName = '';

        if (isPreSelectedWallet) {
          method = 'wallet';
          ref = document.getElementById('wallet-ref').value.trim();
          // wallet_name already stored in DB from waiter selection — no need to re-send
        } else {
          method = document.getElementById('payment-method').value;
          if (method === 'wallet') {
            ref = document.getElementById('cashier-wallet-ref').value.trim();
            const selWallet = document.getElementById('cashier-wallet-select');
            walletId = selWallet.value;
            if (!walletId) {
              showToast('يرجى اختيار المحفظة أولاً', 'warning');
              return;
            }
            const selOpt = selWallet.options[selWallet.selectedIndex];
            walletName = selOpt.dataset.name + ' (' + selOpt.dataset.number + ')';
          }
        }

        const payNote = document.getElementById('payment-note') ? document.getElementById('payment-note').value.trim() : '';
        const directName = (document.getElementById('direct-staff-select')?.value || '').trim();
        var payload = {
          order_id: selectedOrderId,
          status: 'paid',
          payment_method: method
        };
        if (ref) payload.payment_reference = ref;
        if (walletId) { payload.wallet_id = walletId; payload.wallet_name = walletName; }
        if (payNote) payload.payment_note = payNote;
        if (directName) payload.direct_name = directName;

        const r1 = await apiCall('/api/orders.php?action=update_status', 'POST', payload);
        if (!r1.success) {
          showToast(r1.message, 'danger');
          return;
        }

        showToast('✅ تم تأكيد الدفع ' + (method === 'wallet' ? 'عبر المحفظة' : 'نقداً') + ' وتوزيع الطلب', 'success');

        // ── Bluetooth print: trigger client-side for iPad cashiers ───────────────
        if (window.POS_USER && window.POS_USER.print_type === 'bluetooth') {
          const printOrderId = selectedOrderId; // capture before reset
          setTimeout(() => printOrder(printOrderId, 'receipt'), 300);
        }
        // ────────────────────────────────────────────────────────────────────────

        // Add fade-out animation to the confirmed order card
        const cardEl = document.getElementById('order-card-' + selectedOrderId);
        if (cardEl) {
          cardEl.classList.add('fade-out');
        }

        setTimeout(() => {
          selectedOrderId = null;
          document.getElementById('selected-order').innerHTML = `
          <div class="empty-state" style="padding:60px 20px">
            <span class="icon" style="color:var(--success)"><i class="fas fa-check-circle"></i></span>
            <p>تم إكمال الطلب بنجاح</p>
          </div>
        `;
          document.getElementById('selected-actions').style.display = 'none';
          document.getElementById('selected-order-num').textContent = '';

          if (document.getElementById('wallet-ref')) document.getElementById('wallet-ref').value = '';
          if (document.getElementById('cashier-wallet-ref')) document.getElementById('cashier-wallet-ref').value = '';
          if (document.getElementById('payment-note')) document.getElementById('payment-note').value = '';

          loadOrders();
        }, 350);
      } catch (e) {
        showToast('حدث خطأ في النظام', 'danger');
      } finally {
        isProcessing = false;
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = originalHtml;
        }
      }
    }

    async function refundSelectedOrder(targetStatus = 'refunded') {
      if (!selectedOrderId || isProcessing || !confirmAction('هل أنت متأكد من تأكيد المرتجع؟ سيتم استرجاع المبلغ وخصمه من الإيرادات.')) return;
      try {
        isProcessing = true;
        const res = await apiCall('/api/orders.php?action=update_status', 'POST', { order_id: selectedOrderId, status: targetStatus });
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
          selectedOrderId = null;
          document.getElementById('selected-actions').style.display = 'none';
          loadOrders();
          showToast('✅ تم تسجيل المرتجع بنجاح', 'success');
        }
      } finally {
        isProcessing = false;
      }
    }

    function appendItemsToSelectedOrder() {
      if (!selectedOrderId) return;
      window.location.href = `../waiter/index.php?append_to=${selectedOrderId}`;
    }

    let activeOrderData = null;
    function openDiscountModal() {
      if (!selectedOrderId) return;
      // We need the current order data to pre-fill
      apiCall('/api/orders.php?action=single&id=' + selectedOrderId).then(res => {
        if (res.success) {
          activeOrderData = res.data;
          if (activeOrderData.is_closed_shift && !activeOrderData.bypass_shift_lock) {
            showToast('عفواً، لا يمكن تطبيق خصومات على أوامر في شفت مغلق 🔒', 'warning');
            return;
          }
          if (activeOrderData.status === 'cancelled') {
            showToast('عفواً، لا يمكن تعديل خصومات أو تفاصيل مبيعات لطلب ملغي ❌', 'warning');
            return;
          }
          const isPaidOrder = parseInt(activeOrderData.is_paid_once || 0) === 1 || ['paid', 'completed', 'delivered', 'refunded', 'partially_refunded'].includes(activeOrderData.status);
          const userPerms = (window.POS_USER && window.POS_USER.permissions) || [];
          const hasEditDiscountPaidPerm = (window.POS_USER && window.POS_USER.role === 'admin') || userPerms.includes('edit_discount_after_confirmation');

          if (isPaidOrder && !hasEditDiscountPaidPerm) {
            showToast('عفواً، لا تملك صلاحية لتعديل الخصومات أو تفاصيل المبيعات بعد التأكيد 🔒', 'warning');
            return;
          }
          if (activeOrderData.manual_discount > 0) {
            document.getElementById('discount-amount').value = activeOrderData.manual_discount;
            document.getElementById('discount-type').value = 'fixed';
          } else {
            document.getElementById('discount-amount').value = '';
            document.getElementById('discount-type').value = 'percent';
          }
          document.getElementById('discount-reason').value = activeOrderData.discount_reason || '';
          document.getElementById('customer-type').value = activeOrderData.customer_type || 'normal';
          document.getElementById('customer-ref').value = activeOrderData.customer_ref || '';
          if (document.getElementById('guest-name')) {
            document.getElementById('guest-name').value = activeOrderData.guest_name || '';
          }
          if (typeof updateDiscountLabel === 'function') updateDiscountLabel();
          if (typeof updateCustomerTypeUI === 'function') updateCustomerTypeUI();
          if (typeof calculateLiveDiscount === 'function') calculateLiveDiscount();
          document.getElementById('discount-modal').classList.remove('hidden');
        }
      });
    }

    function closeDiscountModal() {
      document.getElementById('discount-modal').classList.add('hidden');
    }

    function updateDiscountLabel() {
      const type = document.getElementById('discount-type').value;
      document.getElementById('discount-label').textContent = type === 'percent' ? 'نسبة الخصم (%)' : 'مبلغ الخصم (ريال)';
    }

    function calculateLiveDiscount() {
      if (!activeOrderData) return;

      const subtotal = parseFloat(activeOrderData.subtotal || 0);
      const tax = parseFloat(activeOrderData.tax || 0);
      const service = parseFloat(activeOrderData.service_charge || 0);
      const originalTotal = subtotal + tax + service;

      const discountInput = document.getElementById('discount-amount').value;
      const discountType = document.getElementById('discount-type').value;

      let discountValue = 0;
      if (discountInput !== '') {
        const val = parseFloat(discountInput);
        if (!isNaN(val) && val >= 0) {
          if (discountType === 'percent') {
            discountValue = originalTotal * (val / 100);
          } else {
            discountValue = val;
          }
        }
      }

      // Ensure discount doesn't exceed original total
      if (discountValue > originalTotal) {
        discountValue = originalTotal;
      }

      const finalTotal = originalTotal - discountValue;

      document.getElementById('live-orig-total').textContent = originalTotal.toFixed(2) + ' ريال';
      document.getElementById('live-disc-val').textContent = '-' + discountValue.toFixed(2) + ' ريال';
      document.getElementById('live-final-total').textContent = finalTotal.toFixed(2) + ' ريال';
    }

    function updateCustomerTypeUI() {
      const type = document.getElementById('customer-type').value;
      const refGroup = document.getElementById('customer-ref-group');
      const refLabel = document.getElementById('customer-ref-label');
      const refInput = document.getElementById('customer-ref');
      const guestGroup = document.getElementById('guest-name-group');
      const guestInput = document.getElementById('guest-name');
      if (type === 'room') {
        refGroup.style.display = 'block';
        refLabel.textContent = 'رقم الغرفة *';
        refInput.placeholder = 'أدخل رقم الغرفة';
        if (guestGroup) guestGroup.style.display = 'block';
      } else if (type === 'staff') {
        refGroup.style.display = 'block';
        refLabel.textContent = 'رقم الموظف / الاسم *';
        refInput.placeholder = 'أدخل رقم أو اسم الموظف';
        if (guestGroup) {
          guestGroup.style.display = 'none';
          guestInput.value = '';
        }
      } else {
        refGroup.style.display = 'none';
        refInput.value = '';
        if (guestGroup) {
          guestGroup.style.display = 'none';
          guestInput.value = '';
        }
      }
    }

    async function submitDiscount() {
      const value = document.getElementById('discount-amount').value;
      const type = document.getElementById('discount-type').value;
      const reason = document.getElementById('discount-reason').value;
      const custType = document.getElementById('customer-type').value;
      const custRef = document.getElementById('customer-ref').value;
      const guestName = document.getElementById('guest-name') ? document.getElementById('guest-name').value : '';

      if (value !== '') {
        const parsedVal = parseFloat(value);
        if (parsedVal < 0) {
          showToast('قيمة الخصم لا يمكن أن تكون سالبة', 'warning');
          return;
        }
        if (type === 'percent' && parsedVal > 100) {
          showToast('نسبة الخصم لا يمكن أن تتجاوز 100%', 'warning');
          return;
        }
        if (type === 'fixed' && activeOrderData) {
          const subtotal = parseFloat(activeOrderData.subtotal || 0);
          const tax = parseFloat(activeOrderData.tax || 0);
          const service = parseFloat(activeOrderData.service_charge || 0);
          const maxAllowed = subtotal + tax + service;
          if (parsedVal > maxAllowed) {
            showToast(`قيمة الخصم لا يمكن أن تتجاوز إجمالي الفاتورة (${maxAllowed.toFixed(2)} ريال)`, 'warning');
            return;
          }
        }
      }

      if (value !== '' && parseFloat(value) > 0 && reason.trim() === '') {
        showToast('يرجى إدخال سبب الخصم', 'warning');
        return;
      }

      if (custType !== 'normal' && custRef.trim() === '') {
        showToast('يرجى إدخال المرجع (رقم الغرفة / الموظف)', 'warning');
        return;
      }

      try {
        const res = await apiCall('/api/orders.php?action=apply_discount', 'POST', {
          order_id: selectedOrderId,
          value: value === '' ? 0 : parseFloat(value),
          type: type,
          reason: reason,
          customer_type: custType,
          customer_ref: custRef,
          guest_name: guestName
        });

        if (res.success) {
          showToast('تم حفظ التعديلات بنجاح', 'success');
          closeDiscountModal();
          selectOrder(selectedOrderId); // Refresh detail
          loadOrders(); // Refresh list
        } else {
          showToast(res.message, 'danger');
        }
      } catch (e) {
        showToast('حدث خطأ أثناء تطبيق الخصم', 'danger');
      }
    }

    async function savePaymentNoteOnly() {
      if (!selectedOrderId) {
        showToast('يرجى اختيار طلب أولاً', 'warning');
        return;
      }
      const val = document.getElementById('payment-note').value.trim();
      try {
        const res = await apiCall('/api/orders.php?action=update_payment_note', 'POST', {
          order_id: selectedOrderId,
          payment_note: val
        });
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
          selectOrder(selectedOrderId);
          loadOrders();
        }
      } catch (e) {
        showToast('حدث خطأ أثناء حفظ ملاحظة الدفع', 'danger');
      }
    }

    async function cancelSelectedOrder() {
      if (!selectedOrderId || isProcessing) return;
      const reason = prompt('يرجى كتابة سبب إلغاء الطلب:');
      if (reason === null) return; // user cancelled prompt
      if (reason.trim() === '') {
        showToast('يجب إدخال سبب الإلغاء لإتمام العملية', 'warning');
        return;
      }
      try {
        isProcessing = true;
        const res = await apiCall('/api/orders.php?action=update_status', 'POST', {
          order_id: selectedOrderId,
          status: 'cancelled',
          cancellation_reason: reason.trim()
        });
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
          selectedOrderId = null;
          document.getElementById('selected-actions').style.display = 'none';
          loadOrders();
        }
      } finally {
        isProcessing = false;
      }
    }

    async function deleteSelectedOrder() {
      if (!selectedOrderId || isProcessing) return;
      const confirmed = await systemConfirm('⚠️ تحذير: هل أنت متأكد تماماً من رغبتك في حذف هذا الطلب بالكامل من النظام؟\nهذا الإجراء سيقوم بمسح الطلب بجميع أصنافه نهائياً ولا يمكن التراجع عنه.', 'danger');
      if (!confirmed) return;

      try {
        isProcessing = true;
        const res = await apiCall('/api/orders.php?action=delete', 'POST', { id: selectedOrderId });
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
          selectedOrderId = null;
          document.getElementById('selected-actions').style.display = 'none';
          document.getElementById('selected-order').innerHTML = `
            <div class="empty-state" style="padding:60px 20px">
              <span class="icon" style="color:var(--danger)"><i class="fas fa-trash-alt"></i></span>
              <p>تم حذف الطلب نهائياً</p>
            </div>
          `;
          document.getElementById('selected-order-num').textContent = '';
          loadOrders();
        }
      } catch (e) {
        showToast('حدث خطأ أثناء محاولة حذف الطلب', 'danger');
      } finally {
        isProcessing = false;
      }
    }

    function printSelectedReceipt() {
      if (!selectedOrderId) return;
      printReceipt(selectedOrderId);
      // Hide "Add Items" after receipt is printed
      const appendBtn = document.getElementById('append-items-btn');
      if (appendBtn) appendBtn.style.display = 'none';
    }

    document.getElementById('status-filter').addEventListener('change', loadOrders);

    // Wait for app.js (apiCall/onSSE) to be ready, then initialize
    (function waitForApp() {
      if (typeof apiCall === 'function') {
        loadOrders();
        loadCashierWallets();
        loadDirectStaff();
        if (typeof onSSE === 'function') {
          onSSE('new_order', function (data) {
            playNotificationSound(); // Loud alert for cashier
            showToast('🆕 طلب جديد: ' + (data.order_number || '') + ' (طاولة ' + (data.table || '?') + ')', 'success', 5000);
            loadOrders();
          });
          onSSE('order_status_changed', loadOrders);
          onSSE('item_status_changed', loadOrders);
          onSSE('order_deleted', loadOrders);
        }
      } else {
        setTimeout(waitForApp, 50);
      }
    })();

    // ── Direct Staff Loader ────────────────────────────────────────
    async function loadDirectStaff() {
      try {
        const res = await apiCall('/api/direct_staff.php?action=list&active=1');
        if (!res.success || !res.data || !res.data.length) return;
        const sel = document.getElementById('direct-staff-select');
        if (!sel) return;
        res.data.forEach(function (s) {
          const opt = document.createElement('option');
          opt.value = s.name;
          opt.textContent = s.name;
          sel.appendChild(opt);
        });
      } catch (e) {
        // Silently fail — feature is optional
        console.warn('[DirectStaff] Could not load staff list:', e);
      }
    }

    function onEditPayMethodChange() {
      const method = document.getElementById('edit-pay-method').value;
      const extra = document.getElementById('edit-pay-wallet-extra');
      if (extra) extra.style.display = method === 'wallet' ? 'block' : 'none';
    }

    function showPaidPaymentEditForm() {
      document.getElementById('paid-payment-display-group').style.display = 'none';
      document.getElementById('paid-payment-edit-form').style.display = 'block';
    }

    function cancelEditPayment() {
      document.getElementById('paid-payment-edit-form').style.display = 'none';
      document.getElementById('paid-payment-display-group').style.display = 'block';
    }

    async function saveEditPayment() {
      const orderId = selectedOrderId;
      if (!orderId) { showToast('لم يتم تحديد طلب', 'warning'); return; }

      const method = document.getElementById('edit-pay-method')?.value || 'cash';
      const ref    = document.getElementById('edit-pay-ref')?.value?.trim() || '';

      const payload = { order_id: orderId, payment_method: method };

      if (method === 'wallet') {
        const walletSel = document.getElementById('edit-pay-wallet-select');
        const walletId  = walletSel?.value;
        if (!walletId) { showToast('يرجى اختيار المحفظة', 'warning'); return; }
        const selOpt = walletSel.options[walletSel.selectedIndex];
        const walletName = selOpt.dataset.name
          + (selOpt.textContent.match(/\(([^)]+)\)/) ? ' (' + selOpt.textContent.match(/\(([^)]+)\)/)[1] + ')' : '');
        payload.wallet_id   = walletId;
        payload.wallet_name = walletName;
        if (ref) payload.payment_reference = ref;
      }

      const res = await apiCall('/api/orders.php?action=update_payment', 'POST', payload);
      showToast(res.message, res.success ? 'success' : 'danger');
      if (res.success) {
        selectOrder(orderId);
      }
    }


    function systemConfirm(message, type = 'warning') {
      return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
        overlay.style.background = 'rgba(0, 0, 0, 0.4)';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '99999';
        overlay.style.backdropFilter = 'blur(4px)';

        const box = document.createElement('div');
        box.style.background = 'var(--bg-card, #fff)';
        box.style.padding = '24px';
        box.style.borderRadius = '16px';
        box.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)';
        box.style.maxWidth = '400px';
        box.style.width = '90%';
        box.style.textAlign = 'center';
        box.style.border = '1px solid var(--border)';

        const icon = document.createElement('div');
        const iconClass = type === 'danger' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle';
        const iconColor = type === 'danger' ? '#ef4444' : '#f59e0b';
        icon.innerHTML = `<i class="fas ${iconClass}" style="font-size: 2.8rem; color: ${iconColor};"></i>`;
        icon.style.marginBottom = '16px';

        const title = document.createElement('h3');
        title.textContent = 'تأكيد الإجراء';
        title.style.margin = '0 0 10px 0';
        title.style.fontSize = '1.25rem';
        title.style.fontWeight = '800';
        title.style.color = 'var(--text-main)';

        const text = document.createElement('p');
        text.textContent = message;
        text.style.margin = '0 0 24px 0';
        text.style.fontSize = '0.92rem';
        text.style.lineHeight = '1.5';
        text.style.color = '#4b5563';

        const btnContainer = document.createElement('div');
        btnContainer.style.display = 'grid';
        btnContainer.style.gridTemplateColumns = '1fr 1fr';
        btnContainer.style.gap = '12px';

        const btnYes = document.createElement('button');
        btnYes.className = type === 'danger' ? 'btn btn-danger' : 'btn btn-warning';
        btnYes.style.padding = '10px 16px';
        btnYes.style.fontWeight = 'bold';
        btnYes.style.fontSize = '0.9rem';
        btnYes.style.borderRadius = '8px';
        btnYes.style.border = 'none';
        btnYes.style.cursor = 'pointer';
        if (type !== 'danger') {
          btnYes.style.background = '#f59e0b';
          btnYes.style.color = '#fff';
        }
        btnYes.textContent = type === 'danger' ? 'نعم، حذف الكل' : 'نعم، حذف';

        const btnNo = document.createElement('button');
        btnNo.className = 'btn btn-secondary';
        btnNo.style.padding = '10px 16px';
        btnNo.style.fontWeight = 'bold';
        btnNo.style.fontSize = '0.9rem';
        btnNo.style.borderRadius = '8px';
        btnNo.style.border = '1px solid #d1d5db';
        btnNo.style.background = '#fff';
        btnNo.style.color = '#374151';
        btnNo.style.cursor = 'pointer';
        btnNo.textContent = 'تراجع';

        btnContainer.appendChild(btnYes);
        btnContainer.appendChild(btnNo);
        box.appendChild(icon);
        box.appendChild(title);
        box.appendChild(text);
        box.appendChild(btnContainer);
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        btnYes.onclick = () => {
          document.body.removeChild(overlay);
          resolve(true);
        };
        btnNo.onclick = () => {
          document.body.removeChild(overlay);
          resolve(false);
        };
      });
    }
  </script>
  <?php cashierFooter(); ?>