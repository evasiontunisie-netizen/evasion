// ============================================================
// ERP PRO - Dashboard Page
// ============================================================

window.load_dashboard = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.dashboard = getDashboardHTML();

  // Load data
  try {
    const data = await ERP.get('analytics/dashboard');
    if (data) {
      window._dashData = data;
      renderKPIs(data.kpis);
      setTimeout(() => {
        renderRevenueChart(data.revenue_days);
        renderCategoryChart(data.revenue_by_category);
        renderPaymentChart(data.payment_methods);
        renderTopProducts(data.top_products);
        renderTopCustomers(data.top_customers);
        renderWarehouseRevenue(data.revenue_by_warehouse);
      }, 100);
    }
  } catch(e) {
    console.error('Dashboard load error', e);
  }
};

function getDashboardHTML() {
  return `
  <div id="dashboard-page">
    <!-- Welcome -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Bonjour ! 👋</h2>
        <p class="text-slate-500 dark:text-slate-400 mt-0.5 text-sm">Voici un aperçu de votre activité aujourd'hui.</p>
      </div>
      <div class="flex items-center gap-2">
        <select id="dash-period" class="form-input form-select text-sm w-auto" onchange="changePeriod(this.value)">
          <option value="today">Aujourd'hui</option>
          <option value="week">Cette semaine</option>
          <option value="month" selected>Ce mois</option>
          <option value="year">Cette année</option>
        </select>
        <button onclick="window.load_dashboard()" class="btn btn-secondary text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Actualiser
        </button>
      </div>
    </div>

    <!-- KPI Grid -->
    <div id="kpi-grid" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      ${Array(8).fill(0).map((_, i) => `<div class="kpi-card"><div class="skeleton h-4 w-20 mb-3 rounded"></div><div class="skeleton h-8 w-28 mb-2 rounded"></div><div class="skeleton h-3 w-16 rounded"></div></div>`).join('')}
    </div>

    <!-- Charts Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
      <!-- Revenue Chart -->
      <div class="lg:col-span-2 card">
        <div class="card-header">
          <div>
            <h3 class="font-semibold text-slate-800 dark:text-white">Chiffre d'affaires — 7 jours</h3>
            <p class="text-xs text-slate-400 mt-0.5">Évolution quotidienne des ventes</p>
          </div>
          <div class="flex gap-2">
            <div class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>CA</div>
            <div class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span>Commandes</div>
          </div>
        </div>
        <div class="chart-container"><canvas id="revenueChart"></canvas></div>
      </div>

      <!-- Category Pie -->
      <div class="card">
        <div class="card-header">
          <h3 class="font-semibold text-slate-800 dark:text-white">Par catégorie</h3>
        </div>
        <div class="chart-container" style="max-height:220px"><canvas id="categoryChart"></canvas></div>
        <div id="category-legend" class="mt-3 space-y-1.5"></div>
      </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-5">
      <!-- Payment Methods -->
      <div class="card">
        <div class="card-header">
          <h3 class="font-semibold text-slate-800 dark:text-white">Modes de paiement</h3>
        </div>
        <div class="chart-container" style="max-height:200px"><canvas id="paymentChart"></canvas></div>
      </div>

      <!-- Warehouse Revenue -->
      <div class="card lg:col-span-2">
        <div class="card-header">
          <h3 class="font-semibold text-slate-800 dark:text-white">Ventes par showroom</h3>
        </div>
        <div id="warehouse-revenue" class="space-y-3 mt-2"></div>
      </div>
    </div>

    <!-- Top Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Top Products -->
      <div class="card">
        <div class="card-header">
          <h3 class="font-semibold text-slate-800 dark:text-white">Top Produits</h3>
          <span class="text-xs text-slate-400">Ce mois</span>
        </div>
        <div id="top-products-list" class="space-y-2"></div>
      </div>

      <!-- Top Customers -->
      <div class="card">
        <div class="card-header">
          <h3 class="font-semibold text-slate-800 dark:text-white">Top Clients</h3>
          <span class="text-xs text-slate-400">Ce mois</span>
        </div>
        <div id="top-customers-list" class="space-y-2"></div>
      </div>
    </div>
  </div>`;
}

function renderKPIs(kpis) {
  const grid = document.getElementById('kpi-grid');
  if (!grid || !kpis) return;

  const items = [
    { label: "CA Aujourd'hui", value: ERP.currency(kpis.revenue_today), icon: '💰', color: 'text-emerald-500', bg: 'bg-emerald-50 dark:bg-emerald-900/20', trend: '+12%' },
    { label: 'CA ce mois',      value: ERP.currency(kpis.revenue_month), icon: '📈', color: 'text-blue-500', bg: 'bg-blue-50 dark:bg-blue-900/20', trend: '+8%' },
    { label: "Cmd. aujourd'hui",value: ERP.number(kpis.orders_today),    icon: '🛍️', color: 'text-purple-500', bg: 'bg-purple-50 dark:bg-purple-900/20', trend: null },
    { label: 'Cmd. ce mois',    value: ERP.number(kpis.orders_month),    icon: '📦', color: 'text-orange-500', bg: 'bg-orange-50 dark:bg-orange-900/20', trend: null },
    { label: 'Nouveaux clients',value: ERP.number(kpis.new_customers),   icon: '👥', color: 'text-cyan-500', bg: 'bg-cyan-50 dark:bg-cyan-900/20', trend: null },
    { label: 'Tickets ouverts', value: ERP.number(kpis.open_tickets),    icon: '🎫', color: kpis.open_tickets > 0 ? 'text-red-500' : 'text-green-500', bg: 'bg-red-50 dark:bg-red-900/20', trend: null },
    { label: 'Stock faible',    value: ERP.number(kpis.low_stock_items), icon: '⚠️', color: kpis.low_stock_items > 0 ? 'text-amber-500' : 'text-green-500', bg: 'bg-amber-50 dark:bg-amber-900/20', trend: null },
    { label: 'Livraisons',      value: ERP.number(kpis.pending_deliveries), icon: '🚚', color: 'text-indigo-500', bg: 'bg-indigo-50 dark:bg-indigo-900/20', trend: null },
  ];

  grid.innerHTML = items.map(item => `
    <div class="kpi-card">
      <div class="flex items-start justify-between mb-3">
        <div class="${item.bg} w-10 h-10 rounded-xl flex items-center justify-center text-xl">${item.icon}</div>
        ${item.trend ? `<span class="text-xs font-medium text-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 rounded-full">${item.trend}</span>` : ''}
      </div>
      <div class="kpi-value">${item.value}</div>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">${item.label}</p>
    </div>
  `).join('');
}

function renderRevenueChart(days) {
  const ctx = document.getElementById('revenueChart');
  if (!ctx || !days) return;
  if (window._revenueChart) window._revenueChart.destroy();

  const isDark = document.documentElement.classList.contains('dark');
  const labels  = days.map(d => ERP.date(d.date));
  const revenue = days.map(d => parseFloat(d.revenue));
  const orders  = days.map(d => parseInt(d.orders));

  window._revenueChart = new Chart(ctx, {
    data: {
      labels,
      datasets: [
        {
          type: 'bar',
          label: 'CA (MAD)',
          data: revenue,
          backgroundColor: 'rgba(239,68,68,0.15)',
          borderColor: '#EF4444',
          borderWidth: 2,
          borderRadius: 6,
          yAxisID: 'y',
        },
        {
          type: 'line',
          label: 'Commandes',
          data: orders,
          borderColor: '#60A5FA',
          backgroundColor: 'rgba(96,165,250,0.1)',
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: '#60A5FA',
          tension: 0.4,
          fill: true,
          yAxisID: 'y1',
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: isDark ? '#1E293B' : 'white',
          titleColor: isDark ? '#F1F5F9' : '#0F172A',
          bodyColor: isDark ? '#94A3B8' : '#64748B',
          borderColor: isDark ? '#334155' : '#F1F5F9',
          borderWidth: 1,
          padding: 12,
          callbacks: {
            label: ctx => ctx.datasetIndex === 0 ? ` CA: ${ERP.currency(ctx.raw)}` : ` Commandes: ${ctx.raw}`
          }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: isDark ? '#475569' : '#94A3B8', font: { size: 11 } } },
        y: { position: 'left', grid: { color: isDark ? '#1E293B' : '#F8FAFC' }, ticks: { color: isDark ? '#475569' : '#94A3B8', font: { size: 11 }, callback: v => ERP.number(v) } },
        y1: { position: 'right', grid: { display: false }, ticks: { color: isDark ? '#475569' : '#94A3B8', font: { size: 11 } } }
      }
    }
  });
}

function renderCategoryChart(categories) {
  const ctx = document.getElementById('categoryChart');
  const legend = document.getElementById('category-legend');
  if (!ctx || !categories?.length) return;
  if (window._categoryChart) window._categoryChart.destroy();

  const colors = ['#EF4444','#F97316','#F59E0B','#10B981','#3B82F6','#8B5CF6','#EC4899','#06B6D4'];
  const labels  = categories.map(c => c.name);
  const values  = categories.map(c => parseFloat(c.revenue));

  window._categoryChart = new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      cutout: '70%',
    }
  });

  if (legend) {
    const total = values.reduce((a, b) => a + b, 0);
    legend.innerHTML = categories.slice(0, 5).map((c, i) => `
      <div class="flex items-center justify-between text-xs">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:${colors[i]}"></span>
          <span class="text-slate-600 dark:text-slate-400 truncate max-w-[100px]">${c.name}</span>
        </div>
        <span class="font-medium text-slate-700 dark:text-slate-300">${((values[i]/total)*100).toFixed(0)}%</span>
      </div>
    `).join('');
  }
}

function renderPaymentChart(methods) {
  const ctx = document.getElementById('paymentChart');
  if (!ctx || !methods?.length) return;
  if (window._paymentChart) window._paymentChart.destroy();

  const labels  = { cash: 'Espèces', card: 'Carte', transfer: 'Virement', online: 'En ligne', mixed: 'Mixte' };
  const colors  = { cash: '#10B981', card: '#3B82F6', transfer: '#8B5CF6', online: '#F97316', mixed: '#F59E0B' };

  window._paymentChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: methods.map(m => labels[m.payment_method] || m.payment_method),
      datasets: [{
        data: methods.map(m => parseFloat(m.revenue)),
        backgroundColor: methods.map(m => colors[m.payment_method] || '#94A3B8'),
        borderWidth: 0,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } }
      }
    }
  });
}

function renderTopProducts(products) {
  const el = document.getElementById('top-products-list');
  if (!el || !products) return;

  if (!products.length) { el.innerHTML = '<p class="text-center text-slate-400 text-sm py-4">Aucune donnée</p>'; return; }

  const max = Math.max(...products.map(p => p.sold_qty));
  el.innerHTML = products.slice(0, 8).map((p, i) => `
    <div class="flex items-center gap-3 group">
      <span class="text-xs font-bold text-slate-300 dark:text-slate-600 w-4 text-right">${i+1}</span>
      <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${p.name}</span>
          <span class="text-sm font-semibold text-slate-800 dark:text-white ml-2 flex-shrink-0">${ERP.currency(p.revenue)}</span>
        </div>
        <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
          <div class="h-full bg-gradient-to-r from-red-500 to-orange-400 rounded-full transition-all duration-500" style="width:${(p.sold_qty/max*100).toFixed(0)}%"></div>
        </div>
      </div>
      <span class="text-xs text-slate-400 flex-shrink-0">${ERP.number(p.sold_qty)} u.</span>
    </div>
  `).join('');
}

function renderTopCustomers(customers) {
  const el = document.getElementById('top-customers-list');
  if (!el || !customers) return;

  if (!customers.length) { el.innerHTML = '<p class="text-center text-slate-400 text-sm py-4">Aucune donnée</p>'; return; }

  el.innerHTML = customers.slice(0, 8).map((c, i) => `
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
        ${(c.name || 'C')[0].toUpperCase()}
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${c.name}</p>
        <p class="text-xs text-slate-400">${ERP.number(c.orders)} commandes</p>
      </div>
      <span class="text-sm font-semibold text-slate-800 dark:text-white">${ERP.currency(c.spent)}</span>
    </div>
  `).join('');
}

function renderWarehouseRevenue(warehouses) {
  const el = document.getElementById('warehouse-revenue');
  if (!el || !warehouses) return;

  const max = Math.max(...warehouses.map(w => w.revenue));
  el.innerHTML = warehouses.map(w => `
    <div class="flex items-center gap-3">
      <div class="w-36 flex-shrink-0">
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${w.name}</p>
        <p class="text-xs text-slate-400">${ERP.number(w.orders)} cmd.</p>
      </div>
      <div class="flex-1 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
        <div class="h-full bg-gradient-to-r from-red-500 to-orange-400 rounded-full" style="width:${max > 0 ? (w.revenue/max*100).toFixed(0) : 0}%"></div>
      </div>
      <span class="text-sm font-semibold text-slate-800 dark:text-white w-28 text-right">${ERP.currency(w.revenue)}</span>
    </div>
  `).join('');
}

window.initDashboardCharts = function() {
  if (window._dashData) {
    const d = window._dashData;
    renderRevenueChart(d.revenue_days);
    renderCategoryChart(d.revenue_by_category);
    renderPaymentChart(d.payment_methods);
  }
};
