// ============================================================
// ERP PRO - Employees / HR Page
// ============================================================
window.load_employees = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.employees = `
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Ressources Humaines</h2><p class="text-slate-500 text-sm mt-0.5" id="emp-count"></p></div>
      <button onclick="showAddEmployeeModal()" class="btn btn-primary self-start">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Nouvel employé
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-5 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl w-fit">
      ${[['employees-tab','Employés'],['attendance-tab','Présences'],['salaries-tab','Salaires']].map(([id,label]) => `
        <button id="${id}" onclick="switchHRTab('${id}')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors ${id === 'employees-tab' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}">${label}</button>
      `).join('')}
    </div>

    <!-- Employees List -->
    <div id="hr-employees" class="block">
      <div class="card mb-4"><div class="flex flex-wrap gap-3">
        <div class="search-box flex-1 min-w-[200px]"><svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><input id="emp-search" type="text" placeholder="Rechercher..." class="w-full text-sm" oninput="debounceEmpSearch()" /></div>
        <select id="filter-emp-status" onchange="fetchEmployees()" class="form-input form-select text-sm w-auto"><option value="">Tous statuts</option><option value="active">Actif</option><option value="inactive">Inactif</option><option value="on_leave">En congé</option></select>
      </div></div>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" id="employees-grid">
        ${Array(6).fill(0).map(() => '<div class="skeleton h-40 rounded-2xl"></div>').join('')}
      </div>
    </div>

    <!-- Attendance -->
    <div id="hr-attendance" class="hidden">
      <div class="card p-0 overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex gap-3">
          <input type="month" id="att-month" value="${new Date().toISOString().slice(0,7)}" onchange="fetchAttendance()" class="form-input text-sm w-auto" />
        </div>
        <div class="table-wrapper"><table class="data-table">
          <thead><tr><th>Employé</th><th>Date</th><th>Entrée</th><th>Sortie</th><th>Statut</th><th>Retard</th></tr></thead>
          <tbody id="attendance-tbody"><tr><td colspan="6" class="py-8 text-center text-slate-400">Sélectionnez un mois</td></tr></tbody>
        </table></div>
      </div>
    </div>

    <!-- Salaries -->
    <div id="hr-salaries" class="hidden">
      <div class="card p-0 overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex gap-3 items-center">
          <select id="sal-month" onchange="fetchSalaries()" class="form-input form-select text-sm w-auto">
            ${Array.from({length:12},(_,i)=>`<option value="${i+1}" ${i+1===new Date().getMonth()+1?'selected':''}>${['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'][i]}</option>`).join('')}
          </select>
          <input type="number" id="sal-year" value="${new Date().getFullYear()}" min="2020" class="form-input text-sm w-24" onchange="fetchSalaries()" />
          <button onclick="fetchSalaries()" class="btn btn-primary btn-sm">Charger</button>
        </div>
        <div class="table-wrapper"><table class="data-table">
          <thead><tr><th>Employé</th><th>Département</th><th>Salaire base</th><th>Primes</th><th>Déductions</th><th>Net</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody id="salaries-tbody"><tr><td colspan="8" class="py-8 text-center text-slate-400">Sélectionnez une période</td></tr></tbody>
        </table></div>
      </div>
    </div>
  </div>

  <!-- Add Employee Modal -->
  <div id="add-emp-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-2xl w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white">Nouvel employé</h3>
        <button onclick="document.getElementById('add-emp-modal').style.display='none'" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button>
      </div>
      <form onsubmit="submitEmployee(event)" class="p-6 grid grid-cols-2 gap-4">
        <div><label class="form-label">Prénom *</label><input name="first_name" type="text" class="form-input" required /></div>
        <div><label class="form-label">Nom *</label><input name="last_name" type="text" class="form-input" required /></div>
        <div><label class="form-label">Email</label><input name="email" type="email" class="form-input" /></div>
        <div><label class="form-label">Téléphone</label><input name="phone" type="text" class="form-input" /></div>
        <div><label class="form-label">CIN</label><input name="cin" type="text" class="form-input" /></div>
        <div><label class="form-label">Date naissance</label><input name="birthday" type="date" class="form-input" /></div>
        <div><label class="form-label">Date embauche *</label><input name="hire_date" type="date" class="form-input" required value="${new Date().toISOString().slice(0,10)}" /></div>
        <div><label class="form-label">Contrat</label><select name="contract_type" class="form-input form-select"><option value="cdi">CDI</option><option value="cdd">CDD</option><option value="interim">Intérim</option><option value="freelance">Freelance</option></select></div>
        <div><label class="form-label">Salaire de base (MAD)</label><input name="base_salary" type="number" step="0.01" class="form-input" placeholder="0.00" /></div>
        <div><label class="form-label">Magasin</label><select name="warehouse_id" class="form-input form-select"><option value="">— Choisir —</option><option value="1">Showroom Casa</option><option value="2">Showroom Rabat</option><option value="3">Dépôt</option></select></div>
        <div class="col-span-2"><label class="form-label">Adresse</label><input name="address" type="text" class="form-input" /></div>
        <div class="col-span-2 flex justify-end gap-3">
          <button type="button" onclick="document.getElementById('add-emp-modal').style.display='none'" class="btn btn-secondary">Annuler</button>
          <button type="submit" class="btn btn-primary">Créer l'employé</button>
        </div>
      </form>
    </div>
  </div>`;
  setTimeout(fetchEmployees, 50);
};

window.switchHRTab = function(tab) {
  ['hr-employees','hr-attendance','hr-salaries'].forEach(id => {
    document.getElementById(id)?.classList.add('hidden');
  });
  ['employees-tab','attendance-tab','salaries-tab'].forEach(id => {
    const btn = document.getElementById(id);
    if (btn) btn.className = `px-4 py-2 rounded-lg text-sm font-medium transition-colors ${id === tab ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`;
  });
  const map = { 'employees-tab': 'hr-employees', 'attendance-tab': 'hr-attendance', 'salaries-tab': 'hr-salaries' };
  document.getElementById(map[tab])?.classList.remove('hidden');
  if (tab === 'attendance-tab') fetchAttendance();
  if (tab === 'salaries-tab')   fetchSalaries();
};

window.fetchEmployees = async function() {
  const grid = document.getElementById('employees-grid');
  if (!grid) return;
  grid.innerHTML = Array(6).fill(0).map(() => '<div class="skeleton h-40 rounded-2xl"></div>').join('');
  try {
    const res  = await ERP.get('employees', { search: document.getElementById('emp-search')?.value || '', status: document.getElementById('filter-emp-status')?.value || '' });
    const emps = res?.data || [];
    const el   = document.getElementById('emp-count');
    if (el) el.textContent = `${ERP.number(res?.meta?.total || 0)} employé(s)`;

    grid.innerHTML = emps.map(e => `
      <div class="card hover:shadow-lg cursor-pointer transition-all" onclick="viewEmployee(${e.id})">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
            ${((e.first_name||'?')[0]+(e.last_name||'?')[0]).toUpperCase()}
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-slate-800 dark:text-white truncate">${e.first_name} ${e.last_name}</p>
            <p class="text-xs text-slate-400 truncate">${e.position_title || e.department_name || '—'}</p>
          </div>
          <span class="badge ${e.status === 'active' ? 'badge-green' : e.status === 'on_leave' ? 'badge-yellow' : 'badge-gray'}">${e.status === 'active' ? 'Actif' : e.status === 'on_leave' ? 'Congé' : 'Inactif'}</span>
        </div>
        <div class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
          <div class="flex items-center gap-2"><span>📍</span><span>${e.warehouse_name || '—'}</span></div>
          <div class="flex items-center gap-2"><span>📱</span><span>${e.phone || '—'}</span></div>
          <div class="flex items-center gap-2"><span>💰</span><span>${ERP.currency(e.base_salary)} / mois</span></div>
        </div>
        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex gap-2">
          <span class="text-xs text-slate-400">Code: <strong class="text-slate-600 dark:text-slate-300">${e.employee_code}</strong></span>
          <span class="ml-auto text-xs text-slate-400">Depuis ${ERP.date(e.hire_date)}</span>
        </div>
      </div>
    `).join('') || '<div class="col-span-3 text-center py-12 text-slate-400">Aucun employé</div>';
  } catch(e) { grid.innerHTML = `<div class="col-span-3 text-center text-red-400 py-8">Erreur: ${e.message}</div>`; }
};

window.fetchAttendance = async function() {
  const tbody = document.getElementById('attendance-tbody');
  if (!tbody) return;
  const [year, month] = (document.getElementById('att-month')?.value || new Date().toISOString().slice(0,7)).split('-');
  tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';
  try {
    const rows = await ERP.get('employees/attendance', { month, year });
    const statLabels = { present: 'Présent', absent: 'Absent', late: 'Retard', holiday: 'Férié', on_leave: 'Congé', half_day: 'Mi-journée' };
    const statColors = { present: 'badge-green', absent: 'badge-red', late: 'badge-yellow', holiday: 'badge-blue', on_leave: 'badge-purple', half_day: 'badge-orange' };
    tbody.innerHTML = (rows || []).map(a => `
      <tr>
        <td><span class="text-sm font-medium text-slate-700 dark:text-slate-200">${a.first_name} ${a.last_name}</span><br><span class="text-xs text-slate-400">${a.employee_code}</span></td>
        <td><span class="text-sm">${ERP.date(a.date)}</span></td>
        <td><span class="text-sm text-slate-600 dark:text-slate-400">${a.check_in || '—'}</span></td>
        <td><span class="text-sm text-slate-600 dark:text-slate-400">${a.check_out || '—'}</span></td>
        <td><span class="badge ${statColors[a.status] || 'badge-gray'}">${statLabels[a.status] || a.status}</span></td>
        <td><span class="text-sm ${a.late_minutes > 0 ? 'text-amber-500 font-medium' : 'text-slate-400'}">${a.late_minutes > 0 ? `${a.late_minutes} min` : '—'}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="6" class="py-8 text-center text-slate-400">Aucune donnée</td></tr>';
  } catch(e) { tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-red-400">Erreur</td></tr>`; }
};

window.fetchSalaries = async function() {
  const tbody = document.getElementById('salaries-tbody');
  if (!tbody) return;
  const month = document.getElementById('sal-month')?.value;
  const year  = document.getElementById('sal-year')?.value;
  tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';
  try {
    const res  = await ERP.get('employees/salaries', { month, year });
    const rows = res?.data || [];
    tbody.innerHTML = rows.map(s => `
      <tr>
        <td><span class="font-medium text-slate-700 dark:text-slate-200 text-sm">${s.first_name} ${s.last_name}</span><br><span class="text-xs text-slate-400">${s.employee_code}</span></td>
        <td><span class="text-sm text-slate-500 dark:text-slate-400">${s.department_name || '—'}</span></td>
        <td><span class="text-sm">${ERP.currency(s.base_salary)}</span></td>
        <td><span class="text-sm text-emerald-500">+${ERP.currency(s.bonuses)}</span></td>
        <td><span class="text-sm text-red-500">-${ERP.currency(parseFloat(s.deductions)+parseFloat(s.advances))}</span></td>
        <td><span class="font-bold text-slate-800 dark:text-white">${ERP.currency(s.net_salary)}</span></td>
        <td><span class="badge ${s.status==='paid'?'badge-green':s.status==='approved'?'badge-blue':'badge-gray'}">${s.status==='paid'?'Payé':s.status==='approved'?'Approuvé':'Brouillon'}</span></td>
        <td><button class="btn btn-sm btn-secondary text-xs">PDF</button></td>
      </tr>
    `).join('') || '<tr><td colspan="8" class="py-12 text-center text-slate-400">Aucune fiche de paie pour cette période</td></tr>';
  } catch(e) { tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-400">Erreur</td></tr>`; }
};

window.showAddEmployeeModal = function() { document.getElementById('add-emp-modal').style.display = 'flex'; };

window.submitEmployee = async function(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  try {
    const res = await ERP.post('employees', data);
    ERP.toast(`Employé ${res.employee_code} créé`, 'success');
    document.getElementById('add-emp-modal').style.display = 'none';
    e.target.reset();
    fetchEmployees();
  } catch(err) { ERP.toast(err.message, 'error'); }
};

window.viewEmployee = async function(id) {
  try {
    const emp = await ERP.get(`employees/${id}`);
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `<div class="modal max-w-2xl w-full"><div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between"><div><h3 class="font-semibold text-slate-900 dark:text-white">${emp.first_name} ${emp.last_name}</h3><p class="text-xs text-slate-400 mt-0.5">${emp.employee_code} • ${emp.position_title||emp.department_name||''}</p></div><button onclick="this.closest('.modal-overlay').remove()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button></div><div class="p-6 grid grid-cols-2 gap-4 text-sm"><div><p class="text-xs text-slate-400 mb-0.5">Email</p><p class="font-medium text-slate-700 dark:text-slate-200">${emp.email||'—'}</p></div><div><p class="text-xs text-slate-400 mb-0.5">Téléphone</p><p class="font-medium text-slate-700 dark:text-slate-200">${emp.phone||'—'}</p></div><div><p class="text-xs text-slate-400 mb-0.5">Salaire</p><p class="font-bold text-slate-800 dark:text-white">${ERP.currency(emp.base_salary)}/mois</p></div><div><p class="text-xs text-slate-400 mb-0.5">Embauche</p><p class="font-medium">${ERP.date(emp.hire_date)}</p></div><div class="col-span-2"><p class="text-xs text-slate-400 mb-2">Ce mois</p><div class="grid grid-cols-4 gap-2">${['present','absent','late','total_late_minutes'].map(k=>`<div class="bg-slate-50 dark:bg-slate-800 rounded-lg p-2 text-center"><div class="text-lg font-bold text-slate-800 dark:text-white">${emp.attendance_summary?.[k]||0}</div><div class="text-xs text-slate-400">${{present:'Présents',absent:'Absents',late:'Retards',total_late_minutes:'Min retard'}[k]}</div></div>`).join('')}</div></div></div></div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  } catch(e) { ERP.toast('Erreur', 'error'); }
};

window.debounceEmpSearch = ERP.debounce(fetchEmployees, 400);
