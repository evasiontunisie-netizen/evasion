// ============================================================
// ERP PRO - Analytics Page
// ============================================================
window.load_analytics = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.analytics = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Statistiques & Analytics</h2><p class="text-slate-500 text-sm mt-0.5">Analyse approfondie de vos performances</p></div>
      <div class="flex gap-2">
        <input type="date" id="ana-from" class="form-input text-sm" value="${new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10)}" onchange="loadAnalytics()" />
        <input type="date" id="ana-to"   class="form-input text-sm" value="${new Date().toISOString().slice(0,10)}" onchange="loadAnalytics()" />
      </div>
    </div>

    <!-- Summary Cards -->
    <div id="ana-kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      ${Array(4).fill(0).map(()=>'<div class="kpi-card"><div class="skeleton h-4 w-20 mb-3 rounded"></div><div class="skeleton h-8 w-28 mb-2 rounded"></div></div>').join('')}
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-slate-800 dark:text-white">Ventes quotidiennes</h3></div>
        <div class="chart-container"><canvas id="ana-daily-chart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-slate-800 dark:text-white">Évolution mensuelle (12 mois)</h3></div>
        <div class="chart-container"><canvas id="ana-monthly-chart"></canvas></div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-slate-800 dark:text-white">Top 10 Produits</h3></div>
        <div id="ana-top-products" class="space-y-2"></div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-slate-800 dark:text-white">Ventes par showroom</h3></div>
        <div class="chart-container" style="max-height:220px"><canvas id="ana-warehouse-chart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="font-semibold text-slate-800 dark:text-white">Top 10 Clients</h3></div>
        <div id="ana-top-customers" class="space-y-2"></div>
      </div>
    </div>
  </div>`;
  setTimeout(loadAnalytics, 50);
};

window.loadAnalytics = async function() {
  const from = document.getElementById('ana-from')?.value || '';
  const to   = document.getElementById('ana-to')?.value || '';
  try {
    const [dashboard, sales] = await Promise.all([
      ERP.get('analytics/dashboard'),
      ERP.get('analytics/sales-report', { from, to }),
    ]);

    // KPIs
    const kpis = dashboard?.kpis || {};
    const kpiEl = document.getElementById('ana-kpis');
    if (kpiEl) {
      const total = sales?.daily?.reduce((s, d) => s + parseFloat(d.revenue || 0), 0) || 0;
      const orders= sales?.daily?.reduce((s, d) => s + parseInt(d.orders || 0), 0) || 0;
      const avg   = orders > 0 ? total / orders : 0;
      kpiEl.innerHTML = [
        { label: 'CA période',       value: ERP.currency(total),         icon: '📊', color: 'bg-blue-50 dark:bg-blue-900/20 text-blue-500' },
        { label: 'Commandes',        value: ERP.number(orders),          icon: '🛍️', color: 'bg-purple-50 dark:bg-purple-900/20 text-purple-500' },
        { label: 'Panier moyen',     value: ERP.currency(avg),           icon: '🧮', color: 'bg-orange-50 dark:bg-orange-900/20 text-orange-500' },
        { label: 'Taux conversion',  value: '—',                         icon: '🎯', color: 'bg-green-50 dark:bg-green-900/20 text-green-500' },
      ].map(k => `<div class="kpi-card"><div class="${k.color} w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-3">${k.icon}</div><div class="kpi-value">${k.value}</div><p class="text-sm text-slate-500 dark:text-slate-400 mt-1">${k.label}</p></div>`).join('');
    }

    // Daily chart
    const dailyCtx = document.getElementById('ana-daily-chart');
    if (dailyCtx && sales?.daily) {
      if (window._anaDailyChart) window._anaDailyChart.destroy();
      window._anaDailyChart = new Chart(dailyCtx, {
        type: 'bar',
        data: {
          labels: sales.daily.map(d => ERP.date(d.date)),
          datasets: [{
            label: 'CA (MAD)',
            data: sales.daily.map(d => parseFloat(d.revenue)),
            backgroundColor: 'rgba(239,68,68,0.6)',
            borderColor: '#EF4444',
            borderWidth: 2,
            borderRadius: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: 'rgba(241,245,249,0.8)' } } }
        }
      });
    }

    // Monthly chart
    const monthlyCtx = document.getElementById('ana-monthly-chart');
    if (monthlyCtx && dashboard?.monthly_revenue) {
      if (window._anaMonthlyChart) window._anaMonthlyChart.destroy();
      const months = dashboard.monthly_revenue;
      const labels = months.map(m => `${['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'][m.month-1]} ${m.year}`);
      window._anaMonthlyChart = new Chart(monthlyCtx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'CA',
            data: months.map(m => parseFloat(m.revenue)),
            borderColor: '#F97316',
            backgroundColor: 'rgba(249,115,22,0.08)',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#F97316',
            tension: 0.4,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: 'rgba(241,245,249,0.8)' } } }
        }
      });
    }

    // Warehouse chart
    const whCtx = document.getElementById('ana-warehouse-chart');
    if (whCtx && dashboard?.revenue_by_warehouse) {
      if (window._anaWhChart) window._anaWhChart.destroy();
      const wh = dashboard.revenue_by_warehouse;
      window._anaWhChart = new Chart(whCtx, {
        type: 'bar',
        data: {
          labels: wh.map(w => w.name),
          datasets: [{
            data: wh.map(w => parseFloat(w.revenue)),
            backgroundColor: ['#EF4444','#F97316','#F59E0B','#10B981'],
            borderRadius: 8,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false } }, y: { grid: { display: false } } },
          indexAxis: 'y',
        }
      });
    }

    // Top products
    const topPEl = document.getElementById('ana-top-products');
    if (topPEl && dashboard?.top_products) {
      const max = Math.max(...dashboard.top_products.map(p => p.sold_qty));
      topPEl.innerHTML = dashboard.top_products.slice(0,10).map((p,i) => `
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-300 dark:text-slate-600 w-4 text-right">${i+1}</span>
          <div class="flex-1 min-w-0">
            <div class="flex justify-between items-center mb-0.5">
              <span class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">${p.name}</span>
              <span class="text-xs font-semibold text-slate-800 dark:text-white ml-1">${ERP.currency(p.revenue)}</span>
            </div>
            <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full"><div class="h-full bg-gradient-to-r from-red-500 to-orange-400 rounded-full" style="width:${(p.sold_qty/max*100).toFixed(0)}%"></div></div>
          </div>
          <span class="text-xs text-slate-400 w-10 text-right">${p.sold_qty}u.</span>
        </div>
      `).join('');
    }

    // Top customers
    const topCEl = document.getElementById('ana-top-customers');
    if (topCEl && dashboard?.top_customers) {
      topCEl.innerHTML = dashboard.top_customers.slice(0,10).map((c,i) => `
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-300 dark:text-slate-600 w-4">${i+1}</span>
          <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">${(c.name||'?')[0]}</div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">${c.name}</p>
            <p class="text-[10px] text-slate-400">${c.orders} cmd.</p>
          </div>
          <span class="text-xs font-semibold text-slate-800 dark:text-white">${ERP.currency(c.spent)}</span>
        </div>
      `).join('');
    }

  } catch(e) { ERP.toast('Erreur chargement analytics', 'error'); console.error(e); }
};
