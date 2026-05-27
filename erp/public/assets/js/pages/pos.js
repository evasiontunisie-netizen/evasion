// ============================================================
// ERP PRO - POS (Point of Sale) Page
// ============================================================

window._posState = {
  cart: [], search: '', products: [], customer: null, warehouseId: 1,
  paymentMethod: 'cash', discountPercent: 0, cashReceived: 0,
  loading: false, searchResults: [],
};

window.load_pos = function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.pos = getPOSHTML();
  setTimeout(() => {
    bindPOSSearch();
    updateCartDisplay();
  }, 50);
};

function getPOSHTML() {
  return `
  <div id="pos-page" class="flex flex-col lg:flex-row h-[calc(100vh-64px)] bg-slate-50 dark:bg-slate-900">
    <!-- Products Panel -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- POS Header -->
      <div class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 px-4 py-3 flex items-center gap-3">
        <div class="search-box flex-1">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input id="pos-search" type="text" placeholder="Rechercher produit, scanner barcode..." class="w-full text-sm" autocomplete="off" />
        </div>
        <select id="pos-warehouse" class="form-input form-select text-sm w-auto" onchange="window._posState.warehouseId = parseInt(this.value)">
          <option value="1">Showroom Casa</option>
          <option value="2">Showroom Rabat</option>
          <option value="3">Dépôt</option>
        </select>
      </div>

      <!-- Search Results -->
      <div id="pos-search-results" class="absolute top-16 left-0 right-0 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-xl shadow-xl z-50 mx-4 mt-1 max-h-80 overflow-y-auto hidden"></div>

      <!-- Quick Categories -->
      <div class="flex gap-2 px-4 py-2 overflow-x-auto bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
        <button onclick="filterPOSCategory('')"    class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-500 text-white">Tous</button>
        <button onclick="filterPOSCategory('1')"   class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-red-50">Chaussures</button>
        <button onclick="filterPOSCategory('2')"   class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-red-50">Vêtements</button>
        <button onclick="filterPOSCategory('3')"   class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-red-50">Accessoires</button>
      </div>

      <!-- Products Grid -->
      <div id="pos-products-grid" class="flex-1 overflow-y-auto p-3">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3">
          ${Array(12).fill(0).map(() => `<div class="skeleton h-48 rounded-xl"></div>`).join('')}
        </div>
      </div>
    </div>

    <!-- Cart Panel -->
    <div class="w-full lg:w-96 flex flex-col bg-white dark:bg-slate-800 border-t lg:border-t-0 lg:border-l border-slate-100 dark:border-slate-700">
      <!-- Cart Header -->
      <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="font-semibold text-slate-800 dark:text-white">Panier</span>
          <span class="badge badge-red" id="cart-count">0</span>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="selectCustomer()" class="btn btn-sm btn-outline text-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span id="customer-btn-label">Client</span>
          </button>
          <button onclick="clearCart()" class="btn btn-sm btn-danger text-xs" title="Vider">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>

      <!-- Customer Info -->
      <div id="customer-info" class="hidden px-4 py-2 bg-blue-50 dark:bg-blue-900/20 border-b border-slate-100 dark:border-slate-700">
        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium" id="customer-name-display"></p>
      </div>

      <!-- Cart Items -->
      <div id="cart-items" class="flex-1 overflow-y-auto px-3 py-2 space-y-2">
        <div id="empty-cart" class="flex flex-col items-center justify-center h-full py-12 text-center">
          <svg class="w-12 h-12 text-slate-200 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          <p class="text-slate-400 text-sm">Le panier est vide</p>
          <p class="text-slate-300 dark:text-slate-600 text-xs mt-1">Recherchez ou scannez un produit</p>
        </div>
      </div>

      <!-- Cart Summary -->
      <div class="border-t border-slate-100 dark:border-slate-700 px-4 py-3 space-y-2.5">
        <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
          <span>Sous-total</span>
          <span id="cart-subtotal">0,00 MAD</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-slate-600 dark:text-slate-400">Remise (%)</span>
          <input type="number" id="cart-discount" min="0" max="100" value="0" oninput="recalcCart()"
                 class="form-input text-sm w-20 text-right py-1 px-2" />
        </div>
        <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
          <span>TVA (incluse)</span>
          <span id="cart-tax">0,00 MAD</span>
        </div>
        <hr class="divider" />
        <div class="flex justify-between font-bold text-lg text-slate-900 dark:text-white">
          <span>TOTAL</span>
          <span id="cart-total">0,00 MAD</span>
        </div>

        <!-- Payment Method -->
        <div class="grid grid-cols-4 gap-1.5 mt-2">
          ${[
            ['cash', '💵', 'Espèces'],
            ['card', '💳', 'Carte'],
            ['transfer', '🏦', 'Virement'],
            ['mixed', '🔀', 'Mixte'],
          ].map(([val, icon, label]) => `
            <button onclick="setPaymentMethod('${val}')"
                    data-pay="${val}"
                    class="pay-btn flex flex-col items-center gap-1 py-2 rounded-xl border-2 text-xs font-medium transition-all ${val === 'cash' ? 'border-red-500 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'border-slate-100 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-red-300'}">
              <span class="text-base">${icon}</span>
              <span>${label}</span>
            </button>
          `).join('')}
        </div>

        <!-- Cash received (for cash payment) -->
        <div id="cash-section">
          <label class="form-label text-xs">Montant reçu (MAD)</label>
          <input type="number" id="cash-received" step="0.01" placeholder="0.00" oninput="calcChange()"
                 class="form-input text-sm text-right" />
          <p id="change-display" class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 hidden font-medium"></p>
        </div>

        <button onclick="submitOrder()" id="pos-checkout-btn"
                class="btn btn-primary w-full py-3 text-base font-semibold rounded-xl mt-1 disabled:opacity-50">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Valider la vente
        </button>
      </div>
    </div>
  </div>

  <!-- Receipt Modal -->
  <div id="receipt-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-sm w-full">
      <div class="p-6 text-center" id="receipt-content">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Vente enregistrée !</h3>
        <p class="text-slate-500 text-sm mb-6" id="receipt-order-number"></p>
        <div id="receipt-details" class="text-left text-sm mb-6"></div>
        <div class="flex gap-3">
          <button onclick="printReceipt()" class="btn btn-secondary flex-1">🖨️ Imprimer</button>
          <button onclick="newSale()" class="btn btn-primary flex-1">Nouvelle vente</button>
        </div>
      </div>
    </div>
  </div>
  `;
}

async function bindPOSSearch() {
  const inp = document.getElementById('pos-search');
  if (!inp) return;

  inp.addEventListener('input', ERP.debounce(async () => {
    const q = inp.value.trim();
    if (!q) { hidePOSResults(); return; }

    try {
      const res = await ERP.get('products', { search: q, active: 1, per_page: 20 });
      showPOSSearchResults(res?.data || []);
    } catch {}
  }, 300));

  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const results = document.getElementById('pos-search-results');
      const first   = results?.querySelector('[data-product]');
      if (first) { addToCart(JSON.parse(first.dataset.product)); hidePOSResults(); inp.value = ''; inp.focus(); }
    }
  });

  // Load initial products
  loadPOSProducts();
}

async function loadPOSProducts(categoryId = '') {
  const grid = document.getElementById('pos-products-grid');
  if (!grid) return;

  try {
    const res = await ERP.get('products', { active: 1, per_page: 50, category: categoryId });
    const products = res?.data || [];
    window._posState.products = products;

    grid.innerHTML = `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3">
      ${products.map(p => `
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 overflow-hidden hover:border-red-300 hover:shadow-md transition-all cursor-pointer active:scale-95 select-none"
             onclick='addToCart(${JSON.stringify({id:p.id,name:p.name,sku:p.sku,sale_price:p.sale_price,promo_price:p.promo_price,tax_rate:p.tax_rate,image:p.primary_image}).replace(/'/g,"&#39;")})'>
          <div class="h-28 bg-slate-100 dark:bg-slate-700 overflow-hidden">
            ${p.primary_image
              ? `<img src="/erp/storage/uploads/${p.primary_image}" class="w-full h-full object-cover" onerror="this.style.display='none'" />`
              : `<div class="w-full h-full flex items-center justify-center text-2xl">📦</div>`
            }
          </div>
          <div class="p-2">
            <p class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate leading-tight mb-0.5">${p.name}</p>
            <p class="text-sm font-bold text-red-500">${ERP.currency(p.promo_price || p.sale_price)}</p>
            <p class="text-[10px] text-slate-400">Stock: ${ERP.number(p.total_stock || 0)}</p>
          </div>
        </div>
      `).join('')}
    </div>`;
  } catch(e) { console.error(e); }
}

function showPOSSearchResults(products) {
  const el = document.getElementById('pos-search-results');
  if (!el) return;
  if (!products.length) { el.innerHTML = '<p class="py-4 text-center text-sm text-slate-400">Aucun résultat</p>'; el.classList.remove('hidden'); return; }

  el.innerHTML = products.map(p => `
    <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer transition-colors"
         data-product='${JSON.stringify({id:p.id,name:p.name,sku:p.sku,sale_price:p.sale_price,promo_price:p.promo_price,tax_rate:p.tax_rate}).replace(/'/g,"&#39;")}'
         onclick='addToCartFromSearch(this)'>
      <div class="w-10 h-10 bg-slate-100 dark:bg-slate-700 rounded-lg overflow-hidden flex-shrink-0">
        ${p.primary_image ? `<img src="/erp/storage/uploads/${p.primary_image}" class="w-full h-full object-cover" />` : '<div class="w-full h-full flex items-center justify-center text-lg">📦</div>'}
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${p.name}</p>
        <p class="text-xs text-slate-400">${p.sku || ''}</p>
      </div>
      <span class="text-sm font-bold text-red-500 flex-shrink-0">${ERP.currency(p.promo_price || p.sale_price)}</span>
    </div>
  `).join('');
  el.classList.remove('hidden');
  document.addEventListener('click', hidePOSResults, { once: true });
}

function hidePOSResults() {
  document.getElementById('pos-search-results')?.classList.add('hidden');
}

window.addToCartFromSearch = function(el) {
  try { addToCart(JSON.parse(el.dataset.product)); } catch {}
  hidePOSResults();
  document.getElementById('pos-search').value = '';
};

window.addToCart = function(product) {
  const cart = window._posState.cart;
  const existing = cart.find(i => i.id === product.id);
  const price = parseFloat(product.promo_price) > 0 ? parseFloat(product.promo_price) : parseFloat(product.sale_price);

  if (existing) {
    existing.qty++;
  } else {
    cart.push({ ...product, qty: 1, unit_price: price, discount: 0 });
  }
  updateCartDisplay();
};

window.removeFromCart = function(id) {
  window._posState.cart = window._posState.cart.filter(i => i.id !== id);
  updateCartDisplay();
};

window.updateQty = function(id, qty) {
  const item = window._posState.cart.find(i => i.id === id);
  if (item) {
    item.qty = Math.max(1, parseInt(qty) || 1);
    updateCartDisplay();
  }
};

window.clearCart = function() {
  window._posState.cart = [];
  window._posState.customer = null;
  updateCartDisplay();
  document.getElementById('customer-info').classList.add('hidden');
  document.getElementById('customer-btn-label').textContent = 'Client';
};

window.recalcCart = function() { updateCartDisplay(); };

function updateCartDisplay() {
  const cart   = window._posState.cart;
  const items  = document.getElementById('cart-items');
  const empty  = document.getElementById('empty-cart');
  const count  = document.getElementById('cart-count');
  const discount= parseFloat(document.getElementById('cart-discount')?.value || 0);

  if (!items) return;

  const totalItems = cart.reduce((s, i) => s + i.qty, 0);
  if (count) count.textContent = totalItems;

  if (!cart.length) {
    if (empty) empty.style.display = 'flex';
    document.querySelectorAll('.cart-item-row').forEach(el => el.remove());
    setCartTotals(0, 0, 0);
    return;
  }

  if (empty) empty.style.display = 'none';

  // Remove old rows
  document.querySelectorAll('.cart-item-row').forEach(el => el.remove());

  let subtotal = 0;
  cart.forEach(item => {
    const lineTotal = item.unit_price * item.qty;
    subtotal += lineTotal;
    const row = document.createElement('div');
    row.className = 'cart-item-row bg-slate-50 dark:bg-slate-700/50 rounded-xl p-2.5';
    row.innerHTML = `
      <div class="flex items-start gap-2">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${item.name}</p>
          <p class="text-xs text-slate-400">${ERP.currency(item.unit_price)} / u.</p>
        </div>
        <button onclick="removeFromCart(${item.id})" class="text-slate-300 hover:text-red-500 transition-colors p-0.5 flex-shrink-0">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
      </div>
      <div class="flex items-center justify-between mt-1.5">
        <div class="flex items-center gap-1.5">
          <button onclick="updateQty(${item.id}, ${item.qty - 1})" class="w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-600 hover:bg-red-100 text-slate-600 flex items-center justify-center text-sm font-bold transition-colors">−</button>
          <input type="number" min="1" value="${item.qty}" onchange="updateQty(${item.id}, this.value)"
                 class="w-12 text-center text-sm font-medium bg-white dark:bg-slate-600 border border-slate-200 dark:border-slate-500 rounded-md py-0.5 px-1" />
          <button onclick="updateQty(${item.id}, ${item.qty + 1})" class="w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-600 hover:bg-green-100 text-slate-600 flex items-center justify-center text-sm font-bold transition-colors">+</button>
        </div>
        <span class="font-semibold text-slate-800 dark:text-white text-sm">${ERP.currency(lineTotal)}</span>
      </div>
    `;
    items.appendChild(row);
  });

  const discountAmt = subtotal * (discount / 100);
  const discounted  = subtotal - discountAmt;
  const totalTax    = cart.reduce((s, i) => s + (i.unit_price * i.qty * ((i.tax_rate || 20) / 100)), 0) * (1 - discount/100);
  const total       = discounted;

  setCartTotals(subtotal, totalTax, total, discountAmt);
}

function setCartTotals(subtotal, tax, total, discount = 0) {
  const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  setEl('cart-subtotal', ERP.currency(subtotal));
  setEl('cart-tax',      ERP.currency(tax));
  setEl('cart-total',    ERP.currency(total));
  window._posState.currentTotal = total;
  calcChange();
}

window.calcChange = function() {
  const received = parseFloat(document.getElementById('cash-received')?.value || 0);
  const total    = window._posState.currentTotal || 0;
  const change   = received - total;
  const el       = document.getElementById('change-display');
  if (el && received > 0) {
    el.classList.remove('hidden');
    el.textContent = change >= 0 ? `Monnaie à rendre: ${ERP.currency(change)}` : `Manquant: ${ERP.currency(-change)}`;
    el.className = `text-xs mt-1 font-medium ${change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'}`;
  }
};

window.setPaymentMethod = function(method) {
  window._posState.paymentMethod = method;
  document.querySelectorAll('.pay-btn').forEach(btn => {
    const active = btn.dataset.pay === method;
    btn.className = `pay-btn flex flex-col items-center gap-1 py-2 rounded-xl border-2 text-xs font-medium transition-all ${active ? 'border-red-500 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'border-slate-100 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-red-300'}`;
  });
  document.getElementById('cash-section').style.display = method === 'cash' ? 'block' : 'none';
};

window.submitOrder = async function() {
  const s    = window._posState;
  const cart = s.cart;
  if (!cart.length) { ERP.toast('Le panier est vide', 'warning'); return; }

  const btn = document.getElementById('pos-checkout-btn');
  btn.disabled = true;
  btn.innerHTML = '<div class="loading-spinner w-5 h-5"></div> Traitement...';

  const discount = parseFloat(document.getElementById('cart-discount')?.value || 0);
  const paid     = s.paymentMethod === 'cash' ? parseFloat(document.getElementById('cash-received')?.value || 0) : s.currentTotal;

  try {
    const order = await ERP.post('orders', {
      warehouse_id:    s.warehouseId,
      customer_id:     s.customer?.id || null,
      source:          'pos',
      payment_method:  s.paymentMethod,
      discount_percent:discount,
      amount_paid:     paid,
      items: cart.map(i => ({
        product_id:    i.id,
        quantity:      i.qty,
        unit_price:    i.unit_price,
        tax_rate:      i.tax_rate || 20,
      })),
    });

    if (order) {
      document.getElementById('receipt-order-number').textContent = `Commande: ${order.order_number}`;
      document.getElementById('receipt-details').innerHTML = `
        <div class="space-y-1 text-slate-600 dark:text-slate-400">
          <div class="flex justify-between"><span>Total</span><strong class="text-slate-800 dark:text-white">${ERP.currency(order.total)}</strong></div>
          ${s.paymentMethod === 'cash' ? `<div class="flex justify-between"><span>Payé</span><strong class="text-slate-800 dark:text-white">${ERP.currency(paid)}</strong></div>
          <div class="flex justify-between"><span>Monnaie</span><strong class="text-emerald-500">${ERP.currency(Math.max(0, paid - order.total))}</strong></div>` : ''}
        </div>
      `;
      document.getElementById('receipt-modal').style.display = 'flex';
    }
  } catch(e) {
    ERP.toast(e.message || 'Erreur lors de la vente', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Valider la vente';
  }
};

window.newSale = function() {
  document.getElementById('receipt-modal').style.display = 'none';
  window.clearCart();
  document.getElementById('cart-discount').value = 0;
};

window.printReceipt = function() { window.print(); };

window.filterPOSCategory = function(catId) {
  document.querySelectorAll('#pos-page button[onclick^="filterPOSCategory"]').forEach(btn => {
    btn.className = `flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium ${
      btn.getAttribute('onclick').includes(`'${catId}'`) ? 'bg-red-500 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-red-50'
    }`;
  });
  loadPOSProducts(catId);
};

window.selectCustomer = async function() {
  const name = prompt('Nom ou téléphone du client:');
  if (!name) return;
  try {
    const res = await ERP.get('customers', { search: name, per_page: 5 });
    const customers = res?.data || [];
    if (customers.length) {
      window._posState.customer = customers[0];
      document.getElementById('customer-info').classList.remove('hidden');
      document.getElementById('customer-name-display').textContent = `${customers[0].first_name} ${customers[0].last_name} • ${customers[0].phone || ''}`;
      document.getElementById('customer-btn-label').textContent = customers[0].first_name;
    } else {
      ERP.toast('Aucun client trouvé', 'warning');
    }
  } catch(e) { ERP.toast('Erreur', 'error'); }
};
