// ============================================================
// ERP PRO - Products Page
// ============================================================

window._productsState = {
  products: [], categories: [], brands: [],
  page: 1, lastPage: 1, total: 0,
  search: '', category: '', active: '',
  sort: 'created_at', dir: 'desc',
  loading: false,
};

window.load_products = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.products = getProductsHTML();
  setTimeout(async () => {
    await loadCategories();
    await loadBrands();
    await fetchProducts();
    bindProductSearch();
  }, 50);
};

function getProductsHTML() {
  return `
  <div id="products-page">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Produits</h2>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5" id="products-count">Chargement...</p>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="showProductModal()" class="btn btn-primary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nouveau produit
        </button>
        <button class="btn btn-secondary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
          Import
        </button>
        <button class="btn btn-secondary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Export
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="flex flex-wrap gap-3">
        <div class="search-box flex-1 min-w-[200px]">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input id="product-search" type="text" placeholder="Rechercher par nom, SKU, barcode..." class="w-full" />
        </div>
        <select id="filter-category" onchange="filterProducts()" class="form-input form-select text-sm w-auto min-w-[140px]">
          <option value="">Toutes catégories</option>
        </select>
        <select id="filter-active" onchange="filterProducts()" class="form-input form-select text-sm w-auto">
          <option value="">Tous les statuts</option>
          <option value="1">Actif</option>
          <option value="0">Inactif</option>
        </select>
        <select id="sort-products" onchange="sortProducts(this.value)" class="form-input form-select text-sm w-auto">
          <option value="created_at_desc">Plus récent</option>
          <option value="name_asc">Nom A-Z</option>
          <option value="sale_price_asc">Prix ↑</option>
          <option value="sale_price_desc">Prix ↓</option>
        </select>
      </div>
    </div>

    <!-- Products Table -->
    <div class="card p-0 overflow-hidden">
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th class="w-12"><input type="checkbox" class="rounded accent-red-500" /></th>
              <th>Produit</th>
              <th>SKU</th>
              <th>Catégorie</th>
              <th>Prix vente</th>
              <th>Stock total</th>
              <th>Statut</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="products-tbody">
            <tr><td colspan="8" class="py-12 text-center text-slate-400">
              <div class="loading-spinner mx-auto mb-3"></div>
              Chargement...
            </td></tr>
          </tbody>
        </table>
      </div>
      <div id="products-pagination" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800"></div>
    </div>
  </div>

  <!-- Product Modal -->
  <div id="product-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-2xl w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white" id="modal-title">Nouveau produit</h3>
        <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
      </div>
      <form id="product-form" onsubmit="submitProduct(event)" class="p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="form-label">Nom du produit *</label>
            <input name="name" type="text" class="form-input" placeholder="Ex: Nike Air Max 270" required />
          </div>
          <div>
            <label class="form-label">SKU</label>
            <input name="sku" type="text" class="form-input" placeholder="Ex: NAM-270-BLK" />
          </div>
          <div>
            <label class="form-label">Code-barres</label>
            <input name="barcode" type="text" class="form-input" placeholder="Ex: 1234567890123" />
          </div>
          <div>
            <label class="form-label">Prix d'achat (MAD)</label>
            <input name="purchase_price" type="number" step="0.01" min="0" class="form-input" placeholder="0.00" />
          </div>
          <div>
            <label class="form-label">Prix de vente *</label>
            <input name="sale_price" type="number" step="0.01" min="0" class="form-input" placeholder="0.00" required />
          </div>
          <div>
            <label class="form-label">Prix promo (MAD)</label>
            <input name="promo_price" type="number" step="0.01" min="0" class="form-input" placeholder="0.00" />
          </div>
          <div>
            <label class="form-label">TVA (%)</label>
            <input name="tax_rate" type="number" step="0.01" min="0" max="100" class="form-input" value="20" />
          </div>
          <div>
            <label class="form-label">Catégorie</label>
            <select name="category_id" id="modal-category" class="form-input form-select"><option value="">— Choisir —</option></select>
          </div>
          <div>
            <label class="form-label">Marque</label>
            <select name="brand_id" id="modal-brand" class="form-input form-select"><option value="">— Choisir —</option></select>
          </div>
          <div>
            <label class="form-label">Stock minimum</label>
            <input name="min_stock" type="number" min="0" class="form-input" value="5" />
          </div>
          <div>
            <label class="form-label">Unité</label>
            <select name="unit" class="form-input form-select">
              <option value="pcs">Pièce</option>
              <option value="kg">Kilogramme</option>
              <option value="l">Litre</option>
              <option value="m">Mètre</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="form-label">Description courte</label>
            <textarea name="short_description" class="form-input resize-none" rows="2" placeholder="Brève description du produit..."></textarea>
          </div>
          <div class="md:col-span-2 flex items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input name="is_active" type="checkbox" class="w-4 h-4 rounded accent-red-500" checked />
              <span class="text-sm text-slate-600 dark:text-slate-400">Produit actif</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input name="is_featured" type="checkbox" class="w-4 h-4 rounded accent-red-500" />
              <span class="text-sm text-slate-600 dark:text-slate-400">Produit vedette</span>
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" onclick="closeProductModal()" class="btn btn-secondary">Annuler</button>
          <button type="submit" class="btn btn-primary" id="product-submit-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Enregistrer
          </button>
        </div>
      </form>
    </div>
  </div>
  `;
}

async function fetchProducts() {
  const s = window._productsState;
  s.loading = true;
  const tbody = document.getElementById('products-tbody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';

  try {
    const params = { page: s.page, per_page: 25, search: s.search, category: s.category, active: s.active, sort: s.sort, dir: s.dir };
    const res = await ERP.get('products', params);
    if (!res) return;

    s.products  = res.data;
    s.total     = res.meta?.total || 0;
    s.lastPage  = res.meta?.last_page || 1;

    const countEl = document.getElementById('products-count');
    if (countEl) countEl.textContent = `${ERP.number(s.total)} produit${s.total > 1 ? 's' : ''}`;

    renderProductsTable(s.products);
    renderProductsPagination();
  } catch(e) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-400">Erreur: ${e.message}</td></tr>`;
  } finally {
    s.loading = false;
  }
}

function renderProductsTable(products) {
  const tbody = document.getElementById('products-tbody');
  if (!tbody) return;
  if (!products.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="py-12 text-center text-slate-400">Aucun produit trouvé</td></tr>';
    return;
  }

  tbody.innerHTML = products.map(p => `
    <tr>
      <td><input type="checkbox" value="${p.id}" class="rounded accent-red-500" /></td>
      <td>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-slate-100 dark:bg-slate-700">
            ${p.primary_image
              ? `<img src="/erp/storage/uploads/${p.primary_image}" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22%23CBD5E1%22 viewBox=%220 0 24 24%22><path d=%22M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z%22/></svg>'" />`
              : '<div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>'
            }
          </div>
          <div class="min-w-0">
            <p class="font-medium text-slate-800 dark:text-white text-sm truncate max-w-[200px]">${p.name}</p>
            ${p.brand_name ? `<p class="text-xs text-slate-400">${p.brand_name}</p>` : ''}
          </div>
        </div>
      </td>
      <td><code class="text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">${p.sku || '—'}</code></td>
      <td><span class="text-sm text-slate-600 dark:text-slate-400">${p.category_name || '—'}</span></td>
      <td>
        <div>
          <span class="font-semibold text-slate-800 dark:text-white text-sm">${ERP.currency(p.sale_price)}</span>
          ${p.promo_price ? `<span class="text-xs text-red-500 ml-1 line-through">${ERP.currency(p.promo_price)}</span>` : ''}
        </div>
        <span class="text-xs text-slate-400">Achat: ${ERP.currency(p.purchase_price)}</span>
      </td>
      <td>
        <span class="${parseInt(p.total_stock) <= parseInt(p.min_stock) ? 'text-red-500 font-semibold' : 'text-slate-700 dark:text-slate-300'} text-sm">
          ${ERP.number(p.total_stock)} u.
        </span>
        ${parseInt(p.total_stock) <= parseInt(p.min_stock) && parseInt(p.total_stock) > 0 ? '<br><span class="text-xs text-amber-500">⚠ Stock faible</span>' : ''}
        ${parseInt(p.total_stock) === 0 ? '<br><span class="text-xs text-red-500">Rupture</span>' : ''}
      </td>
      <td>${p.is_active ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Inactif</span>'}</td>
      <td>
        <div class="flex items-center justify-end gap-1">
          <button onclick="viewProduct(${p.id})" class="btn btn-icon btn-secondary btn-sm" title="Voir">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
          <button onclick="editProduct(${p.id})" class="btn btn-icon btn-secondary btn-sm" title="Modifier">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button onclick="deleteProduct(${p.id}, '${p.name.replace(/'/g, "\\'")}')" class="btn btn-icon btn-danger btn-sm" title="Supprimer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function renderProductsPagination() {
  const s = window._productsState;
  const el = document.getElementById('products-pagination');
  if (!el) return;

  const from = (s.page - 1) * 25 + 1;
  const to   = Math.min(s.page * 25, s.total);

  el.innerHTML = `
    <span class="text-sm text-slate-500 dark:text-slate-400">${from}–${to} sur ${ERP.number(s.total)}</span>
    <div class="pagination">
      <button class="page-btn" ${s.page <= 1 ? 'disabled' : ''} onclick="goPage(${s.page - 1})">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      ${getPaginationButtons(s.page, s.lastPage)}
      <button class="page-btn" ${s.page >= s.lastPage ? 'disabled' : ''} onclick="goPage(${s.page + 1})">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
  `;
}

function getPaginationButtons(current, last) {
  let pages = [];
  const range = 2;
  for (let i = Math.max(1, current - range); i <= Math.min(last, current + range); i++) pages.push(i);
  if (pages[0] > 1)    { if (pages[0] > 2) pages.unshift('...'); pages.unshift(1); }
  if (pages[pages.length-1] < last) { if (pages[pages.length-1] < last-1) pages.push('...'); pages.push(last); }

  return pages.map(p => p === '...'
    ? '<span class="page-btn cursor-default">…</span>'
    : `<button class="page-btn ${p === current ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`
  ).join('');
}

window.goPage = function(p) { window._productsState.page = p; fetchProducts(); };

function bindProductSearch() {
  const inp = document.getElementById('product-search');
  if (!inp) return;
  inp.addEventListener('input', ERP.debounce(() => {
    window._productsState.search = inp.value;
    window._productsState.page   = 1;
    fetchProducts();
  }, 400));
}

window.filterProducts = function() {
  window._productsState.category = document.getElementById('filter-category')?.value || '';
  window._productsState.active   = document.getElementById('filter-active')?.value || '';
  window._productsState.page     = 1;
  fetchProducts();
};

window.sortProducts = function(val) {
  const [sort, dir] = val.split('_');
  window._productsState.sort = sort;
  window._productsState.dir  = dir;
  fetchProducts();
};

async function loadCategories() {
  try {
    const res = await ERP.get('analytics/dashboard'); // Placeholder - would use /categories
    const cats = [{ id: 1, name: 'Chaussures' }, { id: 2, name: 'Vêtements' }, { id: 3, name: 'Accessoires' }];
    const selects = document.querySelectorAll('#filter-category, #modal-category');
    selects.forEach(sel => {
      cats.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id; opt.textContent = c.name;
        sel.appendChild(opt);
      });
    });
  } catch {}
}

async function loadBrands() {
  try {
    const brands = [{ id: 1, name: 'Nike' }, { id: 2, name: 'Adidas' }, { id: 3, name: 'Puma' }];
    const sel = document.getElementById('modal-brand');
    if (sel) brands.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id; opt.textContent = b.name;
      sel.appendChild(opt);
    });
  } catch {}
}

window.showProductModal = function(data = null) {
  const modal = document.getElementById('product-modal');
  const title = document.getElementById('modal-title');
  const form  = document.getElementById('product-form');
  if (!modal) return;
  form.reset();
  form.dataset.editId = '';
  if (data) {
    title.textContent = 'Modifier le produit';
    form.dataset.editId = data.id;
    Object.keys(data).forEach(k => {
      const field = form.elements[k];
      if (field) {
        if (field.type === 'checkbox') field.checked = !!data[k];
        else field.value = data[k] ?? '';
      }
    });
  } else {
    title.textContent = 'Nouveau produit';
  }
  modal.style.display = 'flex';
};

window.closeProductModal = function() {
  document.getElementById('product-modal').style.display = 'none';
};

window.submitProduct = async function(e) {
  e.preventDefault();
  const form   = e.target;
  const editId = form.dataset.editId;
  const btn    = document.getElementById('product-submit-btn');
  const data   = Object.fromEntries(new FormData(form));
  data.is_active  = form.elements.is_active.checked   ? 1 : 0;
  data.is_featured= form.elements.is_featured.checked ? 1 : 0;

  btn.disabled = true;
  btn.innerHTML = '<div class="loading-spinner w-4 h-4"></div> Enregistrement...';

  try {
    if (editId) {
      await ERP.put(`products/${editId}`, data);
      ERP.toast('Produit mis à jour', 'success');
    } else {
      await ERP.post('products', data);
      ERP.toast('Produit créé avec succès', 'success');
    }
    window.closeProductModal();
    fetchProducts();
  } catch(err) {
    ERP.toast(err.message || 'Erreur', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Enregistrer';
  }
};

window.editProduct = async function(id) {
  try {
    const product = await ERP.get(`products/${id}`);
    if (product) window.showProductModal(product);
  } catch(e) { ERP.toast('Erreur chargement', 'error'); }
};

window.deleteProduct = async function(id, name) {
  const ok = await ERP.confirm(`Supprimer le produit "${name}" ? Cette action est irréversible.`, 'Supprimer produit');
  if (!ok) return;
  try {
    await ERP.delete(`products/${id}`);
    ERP.toast('Produit supprimé', 'success');
    fetchProducts();
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.viewProduct = function(id) {
  window.editProduct(id); // Re-use modal for view
};
