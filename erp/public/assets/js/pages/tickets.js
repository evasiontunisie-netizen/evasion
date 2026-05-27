// ============================================================
// ERP PRO - Tickets / SAV Page
// ============================================================

window.load_tickets = async function() {
  const app = document.querySelector('[x-data]').__x.$data;
  app.pages.tickets = getTicketsHTML();
  setTimeout(() => { fetchTickets(); loadTicketStats(); }, 50);
};

function getTicketsHTML() {
  return `
  <div id="tickets-page">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Tickets SAV</h2>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5" id="tickets-count"></p>
      </div>
      <button onclick="showNewTicketModal()" class="btn btn-primary self-start">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouveau ticket
      </button>
    </div>

    <!-- KPIs -->
    <div id="ticket-kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      ${[
        { label: 'Ouverts', id: 'kpi-open', color: 'bg-red-50 dark:bg-red-900/20 text-red-500', icon: '🔴' },
        { label: 'En cours', id: 'kpi-in_progress', color: 'bg-blue-50 dark:bg-blue-900/20 text-blue-500', icon: '🔵' },
        { label: 'Résolus', id: 'kpi-resolved', color: 'bg-green-50 dark:bg-green-900/20 text-green-500', icon: '🟢' },
        { label: 'Urgents', id: 'kpi-urgent', color: 'bg-orange-50 dark:bg-orange-900/20 text-orange-500', icon: '🟠' },
      ].map(k => `
        <div class="kpi-card">
          <div class="${k.color} w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-3">${k.icon}</div>
          <div class="kpi-value" id="${k.id}">—</div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">${k.label}</p>
        </div>
      `).join('')}
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="flex flex-wrap gap-3">
        <div class="search-box flex-1 min-w-[200px]">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input id="ticket-search" type="text" placeholder="Rechercher..." class="w-full text-sm" oninput="debounceTicketSearch()" />
        </div>
        <select id="filter-ticket-status" onchange="fetchTickets()" class="form-input form-select text-sm w-auto">
          <option value="">Tous statuts</option>
          <option value="open">Ouvert</option>
          <option value="in_progress">En cours</option>
          <option value="resolved">Résolu</option>
          <option value="closed">Fermé</option>
        </select>
        <select id="filter-ticket-priority" onchange="fetchTickets()" class="form-input form-select text-sm w-auto">
          <option value="">Toutes priorités</option>
          <option value="urgent">Urgente</option>
          <option value="high">Haute</option>
          <option value="medium">Moyenne</option>
          <option value="low">Basse</option>
        </select>
        <select id="filter-ticket-category" onchange="fetchTickets()" class="form-input form-select text-sm w-auto">
          <option value="">Toutes catégories</option>
          <option value="sav">SAV</option>
          <option value="delivery">Livraison</option>
          <option value="defective">Produit défectueux</option>
          <option value="refund">Remboursement</option>
          <option value="complaint">Réclamation</option>
          <option value="technical">Assistance technique</option>
        </select>
      </div>
    </div>

    <!-- Tickets Table -->
    <div class="card p-0 overflow-hidden">
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>N° Ticket</th>
              <th>Sujet</th>
              <th>Client</th>
              <th>Catégorie</th>
              <th>Priorité</th>
              <th>Assigné à</th>
              <th>Statut</th>
              <th>Créé le</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="tickets-tbody">
            <tr><td colspan="9" class="py-12 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>
          </tbody>
        </table>
      </div>
      <div id="tickets-pagination" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800"></div>
    </div>
  </div>

  <!-- Ticket Detail Modal -->
  <div id="ticket-detail-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-3xl w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white" id="ticket-detail-subject"></h3>
          <p class="text-xs text-slate-400 mt-0.5" id="ticket-detail-number"></p>
        </div>
        <button onclick="closeTicketModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
      </div>
      <div class="flex flex-col md:flex-row">
        <!-- Messages -->
        <div class="flex-1 flex flex-col p-4 min-h-0">
          <div id="ticket-messages" class="flex-1 overflow-y-auto space-y-3 max-h-80 mb-4 pr-2"></div>
          <div class="flex gap-2">
            <input id="ticket-reply" type="text" placeholder="Votre réponse..."
                   class="form-input flex-1 text-sm" onkeydown="if(event.key==='Enter')replyTicket()" />
            <button onclick="replyTicket()" class="btn btn-primary text-sm">Envoyer</button>
          </div>
        </div>
        <!-- Sidebar Info -->
        <div class="w-full md:w-56 border-t md:border-t-0 md:border-l border-slate-100 dark:border-slate-700 p-4 space-y-4 flex-shrink-0">
          <div>
            <p class="text-xs font-semibold uppercase text-slate-400 mb-2">Statut</p>
            <select id="ticket-status-select" onchange="changeTicketStatus(this.value)" class="form-input form-select text-sm w-full">
              <option value="open">Ouvert</option>
              <option value="in_progress">En cours</option>
              <option value="resolved">Résolu</option>
              <option value="closed">Fermé</option>
            </select>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase text-slate-400 mb-1">Client</p>
            <p class="text-sm text-slate-700 dark:text-slate-300" id="ticket-client-info">—</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase text-slate-400 mb-1">Priorité</p>
            <div id="ticket-priority-badge"></div>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase text-slate-400 mb-1">Assigné</p>
            <p class="text-sm text-slate-700 dark:text-slate-300" id="ticket-assigned-info">—</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- New Ticket Modal -->
  <div id="new-ticket-modal" class="modal-overlay" style="display:none">
    <div class="modal max-w-lg w-full">
      <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white">Nouveau ticket</h3>
        <button onclick="document.getElementById('new-ticket-modal').style.display='none'" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
      </div>
      <form onsubmit="submitNewTicket(event)" class="p-6 space-y-4">
        <div>
          <label class="form-label">Sujet *</label>
          <input name="subject" type="text" class="form-input" placeholder="Sujet du ticket" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Catégorie *</label>
            <select name="category" class="form-input form-select" required>
              <option value="sav">SAV</option>
              <option value="delivery">Livraison</option>
              <option value="defective">Produit défectueux</option>
              <option value="refund">Remboursement</option>
              <option value="complaint">Réclamation</option>
              <option value="technical">Assistance technique</option>
            </select>
          </div>
          <div>
            <label class="form-label">Priorité *</label>
            <select name="priority" class="form-input form-select" required>
              <option value="low">Basse</option>
              <option value="medium" selected>Moyenne</option>
              <option value="high">Haute</option>
              <option value="urgent">Urgente</option>
            </select>
          </div>
        </div>
        <div>
          <label class="form-label">Description</label>
          <textarea name="description" rows="4" class="form-input resize-none" placeholder="Décrivez le problème..."></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="document.getElementById('new-ticket-modal').style.display='none'" class="btn btn-secondary">Annuler</button>
          <button type="submit" class="btn btn-primary">Créer le ticket</button>
        </div>
      </form>
    </div>
  </div>
  `;
}

let _ticketPage = 1;
let _currentTicketId = null;

async function fetchTickets() {
  const tbody = document.getElementById('tickets-tbody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="py-8 text-center text-slate-400"><div class="loading-spinner mx-auto mb-2"></div>Chargement...</td></tr>';

  const params = {
    page: _ticketPage, per_page: 25,
    search:   document.getElementById('ticket-search')?.value || '',
    status:   document.getElementById('filter-ticket-status')?.value || '',
    priority: document.getElementById('filter-ticket-priority')?.value || '',
    category: document.getElementById('filter-ticket-category')?.value || '',
  };

  try {
    const res = await ERP.get('tickets', params);
    const tickets = res?.data || [];
    const meta    = res?.meta || {};

    const countEl = document.getElementById('tickets-count');
    if (countEl) countEl.textContent = `${ERP.number(meta.total || 0)} ticket${meta.total > 1 ? 's' : ''}`;

    if (!tbody) return;
    if (!tickets.length) { tbody.innerHTML = '<tr><td colspan="9" class="py-12 text-center text-slate-400">Aucun ticket trouvé</td></tr>'; return; }

    const catLabels = { sav: 'SAV', delivery: 'Livraison', defective: 'Défectueux', refund: 'Remboursement', complaint: 'Réclamation', technical: 'Technique', other: 'Autre' };

    tbody.innerHTML = tickets.map(t => `
      <tr>
        <td><code class="text-xs text-blue-500 dark:text-blue-400 cursor-pointer hover:underline" onclick="openTicketDetail(${t.id})">${t.ticket_number}</code></td>
        <td><p class="text-sm font-medium text-slate-700 dark:text-slate-200 max-w-[200px] truncate cursor-pointer hover:text-red-500" onclick="openTicketDetail(${t.id})">${t.subject}</p></td>
        <td><span class="text-sm text-slate-600 dark:text-slate-400">${t.customer_name || '—'}</span><br><span class="text-xs text-slate-400">${t.customer_phone || ''}</span></td>
        <td><span class="badge badge-gray">${catLabels[t.category] || t.category}</span></td>
        <td>${ERP.statusBadge(t.priority, 'priority')}</td>
        <td><span class="text-sm text-slate-600 dark:text-slate-400">${t.assigned_name || '—'}</span></td>
        <td>${ERP.statusBadge(t.status, 'ticket')}</td>
        <td><span class="text-xs text-slate-400">${ERP.date(t.created_at, 'long')}</span></td>
        <td>
          <div class="flex items-center justify-end gap-1">
            <button onclick="openTicketDetail(${t.id})" class="btn btn-icon btn-secondary btn-sm" title="Ouvrir">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </button>
            <button onclick="changeTicketStatusDirect(${t.id}, 'resolved')" class="btn btn-icon btn-sm" style="background:#DCFCE7;color:#15803D" title="Résoudre">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    document.getElementById('tickets-pagination').innerHTML = `
      <span class="text-sm text-slate-500">${meta.from || 0}–${meta.to || 0} sur ${meta.total || 0}</span>
      <div class="flex gap-1">
        <button class="page-btn" ${_ticketPage <= 1 ? 'disabled' : ''} onclick="_ticketPage--;fetchTickets()">‹</button>
        <button class="page-btn" ${_ticketPage >= meta.last_page ? 'disabled' : ''} onclick="_ticketPage++;fetchTickets()">›</button>
      </div>
    `;
  } catch(e) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-red-400">Erreur: ${e.message}</td></tr>`;
  }
}

async function loadTicketStats() {
  try {
    const stats = await ERP.get('tickets/stats');
    if (stats) {
      ['open','in_progress','resolved','urgent'].forEach(k => {
        const el = document.getElementById(`kpi-${k}`);
        if (el) el.textContent = ERP.number(stats[k] || 0);
      });
    }
  } catch {}
}

window.openTicketDetail = async function(id) {
  _currentTicketId = id;
  const modal = document.getElementById('ticket-detail-modal');
  modal.style.display = 'flex';

  try {
    const ticket = await ERP.get(`tickets/${id}`);
    document.getElementById('ticket-detail-subject').textContent  = ticket.subject;
    document.getElementById('ticket-detail-number').textContent   = ticket.ticket_number;
    document.getElementById('ticket-client-info').textContent     = ticket.customer_name || '—';
    document.getElementById('ticket-assigned-info').textContent   = ticket.assigned_name || 'Non assigné';
    document.getElementById('ticket-priority-badge').innerHTML    = ERP.statusBadge(ticket.priority, 'priority');
    document.getElementById('ticket-status-select').value         = ticket.status;

    const msgs = document.getElementById('ticket-messages');
    msgs.innerHTML = ticket.messages.map(m => `
      <div class="flex gap-2 ${m.user_id ? '' : 'flex-row-reverse'}">
        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
          ${((m.user_name || m.customer_name || 'U')[0]).toUpperCase()}
        </div>
        <div class="${m.user_id ? 'bg-slate-100 dark:bg-slate-700' : 'bg-red-50 dark:bg-red-900/20'} rounded-xl px-3 py-2 max-w-[80%]">
          <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-0.5">${m.user_name || m.customer_name || 'Client'}</p>
          <p class="text-sm text-slate-700 dark:text-slate-200">${m.message}</p>
          <p class="text-[10px] text-slate-400 mt-0.5">${ERP.date(m.created_at, 'long')}</p>
        </div>
      </div>
    `).join('') || '<p class="text-center text-slate-400 text-sm">Aucun message</p>';
    msgs.scrollTop = msgs.scrollHeight;
  } catch(e) { ERP.toast('Erreur chargement', 'error'); }
};

window.closeTicketModal = function() {
  document.getElementById('ticket-detail-modal').style.display = 'none';
  _currentTicketId = null;
};

window.replyTicket = async function() {
  const inp = document.getElementById('ticket-reply');
  const msg = inp.value.trim();
  if (!msg || !_currentTicketId) return;

  try {
    await ERP.post(`tickets/${_currentTicketId}/messages`, { message: msg });
    inp.value = '';
    ERP.toast('Message envoyé', 'success');
    openTicketDetail(_currentTicketId);
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.changeTicketStatus = async function(status) {
  if (!_currentTicketId) return;
  try {
    await ERP.patch(`tickets/${_currentTicketId}/status`, { status });
    ERP.toast('Statut mis à jour', 'success');
    fetchTickets();
    loadTicketStats();
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.changeTicketStatusDirect = async function(id, status) {
  try {
    await ERP.patch(`tickets/${id}/status`, { status });
    ERP.toast('Ticket résolu', 'success');
    fetchTickets();
    loadTicketStats();
  } catch(e) { ERP.toast(e.message, 'error'); }
};

window.showNewTicketModal = function() {
  document.getElementById('new-ticket-modal').style.display = 'flex';
};

window.submitNewTicket = async function(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target));
  try {
    const res = await ERP.post('tickets', data);
    ERP.toast(`Ticket ${res.ticket_number} créé`, 'success');
    document.getElementById('new-ticket-modal').style.display = 'none';
    e.target.reset();
    fetchTickets();
    loadTicketStats();
  } catch(err) { ERP.toast(err.message, 'error'); }
};

window.debounceTicketSearch = ERP.debounce(() => { _ticketPage = 1; fetchTickets(); }, 400);
