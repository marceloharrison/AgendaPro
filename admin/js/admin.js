(function () {
	'use strict';

	const root = document.getElementById('agendapro-root');
	if (!root) return;

	const config = window.agendaproAdmin || {};
	const base = (config.apiUrl || '').replace(/\/$/, '');
	const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' };
	const page = new URLSearchParams(window.location.search).get('page') || 'agendapro';

	const state = {
		tab: 'agendamentos',
		finTab: 'receivables',
		clientTab: 'list',
		settingsTab: 'general',
		clientDetailId: null,
		periodFrom: '',
		periodTo: '',
		dashViewMode: 'list',
		dashAppts: [],
		dashApptPage: 1,
		dashCalDate: new Date().toISOString().slice(0, 10),
		services: [],
		professionals: [],
		packages: [],
		settings: {},
		dashboard: null,
		appointments: { items: [], total: 0 },
		apptPage: 1,
		apptSearch: '',
		apptStatus: '',
		apptDateFrom: '',
		apptDateTo: '',
		apptOrderby: 'appointment_date',
		apptOrder: 'DESC',
		clientPkgs: { items: [], total: 0 },
		clientPkgPage: 1,
		clientPkgSearch: '',
		clientPkgStatus: '',
		clientPkgDateFrom: '',
		clientPkgDateTo: '',
		packages: [],
		receivables: { items: [], total: 0 },
		receivablePage: 1,
		receivableSearch: '',
		receivableStatus: '',
		receivableDateFrom: '',
		receivableDateTo: '',
		expenses: { items: [], total: 0 },
		expensePage: 1,
		expenseSearch: '',
		expenseStatus: '',
		expenseDateFrom: '',
		expenseDateTo: '',
		inactiveClients: [],
		inactiveSearch: '',
		selectedInactive: new Set(),
		clients: [],
		clientPage: 1,
		clientSearch: '',
		anamnesisSections: [],
		kbItems: [],
		overflows: {}
	};

	const PER_PAGE = 25;

	const icons = {
		add: 'dashicons-plus-alt2', calendar: 'dashicons-calendar-alt', list: 'dashicons-list-view',
		clock: 'dashicons-clock', check: 'dashicons-yes-alt', cancel: 'dashicons-dismiss',
		filter: 'dashicons-filter', edit: 'dashicons-edit', trash: 'dashicons-trash',
		save: 'dashicons-saved', phone: 'dashicons-phone', money: 'dashicons-money-alt',
		user: 'dashicons-admin-users', package: 'dashicons-products', settings: 'dashicons-admin-generic'
	};

	const statusMap = { pending: 'Pendente', confirmed: 'Confirmado', cancelled: 'Cancelado' };
	const finStatusMap = { pending: 'Pendente', paid: 'Pago', cancelled: 'Cancelado', overdue: 'Vencido' };

	const request = async (path, options = {}) => {
		const r = await fetch(base + path, { method: options.method || 'GET', headers, body: options.body ? JSON.stringify(options.body) : undefined });
		const data = await r.json().catch(() => ({}));
		if (!r.ok) throw new Error(data.message || 'Erro inesperado.');
		return data;
	};

	const escape = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
	const ic = n => `<span class="dashicons ${icons[n] || n}" aria-hidden="true"></span>`;
	const toast = (m, t = 'success') => { const icons = {success:'dashicons-yes-alt',error:'dashicons-dismiss',warning:'dashicons-warning',info:'dashicons-info'}; const container = document.querySelector('.ap-toast-container') || (() => { const c = document.createElement('div'); c.className = 'ap-toast-container'; document.body.appendChild(c); return c; })(); const e = document.createElement('div'); e.className = 'ap-toast ap-toast--' + t; e.innerHTML = '<span class="ap-toast-icon dashicons ' + (icons[t] || icons.info) + '"></span><div class="ap-toast-body">' + m + '</div>'; container.appendChild(e); setTimeout(() => { e.style.opacity = '0'; e.style.transform = 'translateX(20px)'; e.style.transition = 'all .2s ease'; setTimeout(() => e.remove(), 200); }, 3000); };
	const money = v => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
	const fmtDate = v => { if (!v) return ''; const [y, m, d] = v.split('-'); return `${d}/${m}/${y}`; };
	const toIso = v => { if (!v) return ''; if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v; const c = v.replace(/\D/g, ''); return c.length === 8 ? `${c.slice(4)}-${c.slice(2,4)}-${c.slice(0,2)}` : v; };
	const maskPhone = v => { const n = String(v || '').replace(/\D/g, '').slice(0, 11); if (n.length <= 2) return n ? `(${n}` : ''; if (n.length <= 7) return `(${n.slice(0,2)}) ${n.slice(2)}`; return `(${n.slice(0,2)}) ${n.slice(2,7)}-${n.slice(7)}`; };
	const normalizeTime = v => String(v || '').slice(0, 5);
	const todayIso = () => new Date().toISOString().slice(0, 10);
	const monthStart = () => new Date().toISOString().slice(0, 8) + '01';
	const photo = (url, name) => url ? `<img src="${escape(url)}" alt="${escape(name)}">` : `<span>${escape((name || '?').charAt(0).toUpperCase())}</span>`;

	const loadAll = async () => {
		const [services, professionals, packages, settings, clients] = await Promise.all([
			request('/services'), request('/professionals'), request('/packages'), request('/settings'), request('/clients')
		]);
		state.services = services; state.professionals = professionals; state.packages = packages; state.settings = settings; state.clients = clients;
	};

	const pageTitle = () => ({
		agendapro: state.settings.menu_dashboard || 'Painel',
		'agendapro-appointments': state.settings.menu_appointments || 'Agendamentos',
		'agendapro-services': state.settings.menu_services || 'Serviços',
		'agendapro-professionals': state.settings.menu_professionals || 'Profissionais',
		'agendapro-clients': state.settings.menu_clients || 'Clientes',
		'agendapro-financial': state.settings.menu_financial || 'Financeiro',
		'agendapro-settings': state.settings.menu_settings || 'Configurações'
	}[page] || 'AgendaPro');

	const pageSubtitle = () => ({
		agendapro: 'Visão geral do seu negócio.',
		'agendapro-appointments': 'Gerencie seus agendamentos e horários.',
		'agendapro-services': 'Cadastre e organize seus serviços.',
		'agendapro-professionals': 'Gerencie sua equipe de profissionais.',
		'agendapro-clients': 'Visualize e gerencie seus clientes.',
		'agendapro-financial': 'Controle financeiro de receitas e despesas.',
		'agendapro-settings': 'Configure o sistema e as preferências.'
	}[page] || '');

	const layout = (content, action = '') => {
		root.innerHTML = `<div class="ap-native-page">
			<div class="ap-topbar">
				<div class="ap-title-with-logo">
					${state.settings.logo_url ? `<img class="ap-title-logo" src="${escape(state.settings.logo_url)}" alt="">` : ''}
					<div>
						<h1 class="ap-page-title">${pageTitle()}</h1>
						${pageSubtitle() ? `<p class="ap-page-date">${pageSubtitle()}</p>` : `<p class="ap-page-date">${new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p>`}
					</div>
				</div>${action}
			</div>${content}
		</div>`;
	};

	const pagination = (current, total, kind) => {
		const pages = Math.max(1, Math.ceil(total / PER_PAGE));
		return `<div class="ap-pagination">
			<span class="ap-pagination-info">${total} registro${total !== 1 ? 's' : ''} &middot; Pág. ${current} de ${pages}</span>
			<div class="ap-pagination-btns">
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-page-kind="${kind}" data-page-number="${current - 1}" ${current <= 1 ? 'disabled' : ''}>${ic('calendar')} Anterior</button>
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-page-kind="${kind}" data-page-number="${current + 1}" ${current >= pages ? 'disabled' : ''}>Próxima ${ic('calendar')}</button>
			</div>
		</div>`;
	};

	const gridCard = ({ title, icon = 'list', count = 0, table = '', footer = '', extra = '' } = {}) => {
		const total = Number(count || 0);
		return `<div class="ap-card">
			<div class="ap-card-header">
				<h3 class="ap-card-title">${ic(icon)} ${title}</h3>
				${extra || `<span class="ap-muted">${total} registro${total !== 1 ? 's' : ''}</span>`}
			</div>
			<div class="ap-table-wrap" style="border:none;border-radius:0"><table class="ap-table">${table}</table></div>
			${footer}
		</div>`;
	};

	const overflowMenu = (id, links) => {
		const isOpen = state.overflows[id];
		return `<div class="ap-overflow-wrap">
			<button class="ap-overflow-btn" data-overflow="${id}">⋮</button>
			<div class="ap-overflow-menu${isOpen ? ' show' : ''}" data-overflow-menu="${id}">
				${links.map(l => `<button data-${l.action}-${l.key}="${l.value}" class="${l.cls || ''}">${ic(l.icon || 'edit')} ${l.label}</button>`).join('')}
			</div>
		</div>`;
	};

	/* ── DASHBOARD ─────────────────────────────────── */
	const renderDashboard = () => {
		const from = state.periodFrom || monthStart();
		const to = state.periodTo || todayIso();
		layout(`<div class="ap-period-filter">
			<input type="date" value="${from}" data-period-from>
			<span class="ap-period-sep">até</span>
			<input type="date" value="${to}" data-period-to>
			<button class="ap-btn ap-btn-primary ap-btn-sm" data-load-dashboard>Filtrar</button>
		</div><div data-dashboard-content></div>`);
		loadDashboard(from, to);
	};

	const loadDashboard = async (from, to) => {
		const wrap = root.querySelector('[data-dashboard-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		try {
			const d = await request('/dashboard/stats?date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to));
			state.dashboard = d;
			state.periodFrom = from; state.periodTo = to;
			wrap.innerHTML = renderDashboardKPIs(d) + '<div data-dash-appts-wrap></div>';
			loadDashAppts();
		} catch (e) { wrap.innerHTML = `<div class="ap-empty">Erro: ${escape(e.message)}</div>`; }
	};

	const loadDashAppts = async () => {
		const wrap = root.querySelector('[data-dash-appts-wrap]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner ap-spinner--sm"></div>';
		try {
			const q = new URLSearchParams({ per_page: 25, page: state.dashApptPage, orderby: 'appointment_date', order: 'DESC' });
			if (state.dashCalDate && state.dashViewMode === 'calendar') { q.set('date_from', state.dashCalDate); q.set('date_to', state.dashCalDate); }
			const d = await request('/appointments?' + q);
			state.dashAppts = d.total !== undefined ? d : { items: d, total: d.length };
			wrap.innerHTML = renderDashAppts();
		} catch (e) { wrap.innerHTML = `<div class="ap-error-state">Erro ao carregar agendamentos.</div>`; }
	};

	const renderDashAppts = () => {
		const items = state.dashAppts.items || [];
		const total = state.dashAppts.total || items.length;
		const pages = Math.max(1, Math.ceil(total / 25));

		if (state.dashViewMode === 'calendar') {
			return renderDashCalendar(items);
		}
		return renderDashList(items, total, pages);
	};

	const renderDashList = (items, total, pages) => {
		const rows = items.length ? items.map(a => `<tr>
			<td style="color:var(--ap-text-tertiary);width:48px">#${a.id}</td><td>${fmtDate(a.appointment_date)}</td>
			<td style="font-weight:var(--ap-weight-semibold)">${normalizeTime(a.start_time)}</td>
			<td><strong>${escape(a.client_name)}</strong></td><td>${escape(a.service_name)}</td>
			<td>${a.package_id ? '<span class="ap-tag">Pacote</span>' : '<span style="color:var(--ap-text-tertiary)">Normal</span>'}</td>
			<td><span class="ap-badge ap-badge-${a.status}">${statusMap[a.status] || a.status}</span></td>
			<td>${escape(a.professional_name)}</td>
		</tr>`).join('') : `<tr><td colspan="8" class="ap-table-empty"><div class="ap-empty-text">Nenhum agendamento encontrado.</div></td></tr>`;

		return `<div class="ap-card" style="margin-top:var(--ap-space-4)">
			<div class="ap-card-header">
				<h3 class="ap-card-title">${ic('calendar')} Últimos Agendamentos</h3>
				<div class="ap-segmented">
					<button class="ap-segmented-btn active" data-dash-view="list">${ic('list')}</button>
					<button class="ap-segmented-btn" data-dash-view="calendar">${ic('calendar')}</button>
				</div>
			</div>
			<div class="ap-table-wrap" style="border:none;border-radius:0"><table class="ap-table">
				<thead><tr><th>ID</th><th>Data</th><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Tipo</th><th>Status</th><th>Profissional</th></tr></thead>
				<tbody>${rows}</tbody>
			</table></div>
			<div class="ap-pagination">
				<span class="ap-pagination-info">${total} registro${total !== 1 ? 's' : ''} &middot; Pág. ${state.dashApptPage} de ${pages}</span>
				<div class="ap-pagination-btns">
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-dash-appt-page="${state.dashApptPage - 1}" ${state.dashApptPage <= 1 ? 'disabled' : ''}>Anterior</button>
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-dash-appt-page="${state.dashApptPage + 1}" ${state.dashApptPage >= pages ? 'disabled' : ''}>Próxima</button>
				</div>
			</div>
		</div>`;
	};
	const calendarCache = {};
	let calDrawInterval = null;

	const renderDashCalendar = (items) => {
		setTimeout(() => drawDashCal(), 50);
		const dayAppts = items.filter(a => a.appointment_date === state.dashCalDate);
		const dayList = dayAppts.length ? dayAppts.map(a => `<div class="ap-appointment-card">
			<div class="ap-appointment-card-left">
				<div class="ap-appointment-time"><div class="ap-appointment-time-value">${normalizeTime(a.start_time)}</div></div>
				<div class="ap-appointment-body">
					<div class="ap-appointment-name">${escape(a.client_name)}</div>
					<div class="ap-appointment-detail">
						<span>${escape(a.service_name)}</span>
						<span>${escape(a.professional_name)}</span>
						${a.package_id ? '<span class="ap-tag">Pacote</span>' : ''}
					</div>
				</div>
			</div>
			<span class="ap-badge ap-badge-${a.status}">${statusMap[a.status] || a.status}</span>
		</div>`).join('') : `<div class="ap-empty"><div class="ap-empty-text">Nenhum agendamento nesta data.</div></div>`;

		return `<div class="ap-card" style="margin-top:var(--ap-space-4)">
			<div class="ap-card-header">
				<h3 class="ap-card-title">${ic('calendar')} Agendamentos</h3>
				<div class="ap-segmented">
					<button class="ap-segmented-btn" data-dash-view="list">${ic('list')}</button>
					<button class="ap-segmented-btn active" data-dash-view="calendar">${ic('calendar')}</button>
				</div>
			</div>
			<div class="ap-calendar-layout" style="margin-top:0">
				<div class="ap-calendar-card"><div id="ap-dash-calendar"></div></div>
				<div class="ap-day-panel">
					<h2>${new Date(state.dashCalDate + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })}</h2>
					<div>${dayList}</div>
				</div>
			</div>
		</div>`;
	};

	const drawDashCal = () => {
		const el = document.getElementById('ap-dash-calendar');
		if (!el || typeof FullCalendar === 'undefined') return;
		const calDate = new Date(state.dashCalDate + 'T00:00:00');
		const cal = new FullCalendar.Calendar(el, {
			initialView: 'dayGridMonth',
			initialDate: calDate,
			locale: 'pt-br',
			headerToolbar: {
				left: 'prev',
				center: 'title',
				right: 'next'
			},
			height: 'auto',
			events: (info, success) => {
				const key = info.startStr + '|' + info.endStr;
				if (calendarCache[key]) { success(calendarCache[key]); return; }
				request('/appointments?date_from=' + info.startStr.slice(0, 10) + '&date_to=' + info.endStr.slice(0, 10) + '&per_page=500').then(d => {
					const items = d.items || d;
					const events = items.map(a => ({
						title: a.client_name,
						start: a.appointment_date + 'T' + a.start_time,
						backgroundColor: a.status === 'cancelled' ? 'var(--ap-danger)' : a.status === 'confirmed' ? 'var(--ap-success)' : 'var(--ap-primary)',
						borderColor: 'transparent'
					}));
					calendarCache[key] = events;
					success(events);
				}).catch(() => success([]));
			},
			dateClick: (info) => {
				state.dashCalDate = info.dateStr;
				loadDashAppts();
			}
		});
		cal.render();
	};

	const renderDashboardKPIs = d => {
		const kpis = [
			{ cls: '', variant: 'success', label: 'Receita Recebida', value: money((d.revenue || {}).received || 0), icon: 'money', sub: `Pendente: ${money((d.revenue || {}).pending || 0)} · Vencidas: ${money((d.revenue || {}).overdue || 0)}` },
			{ cls: '', variant: 'primary', label: 'Agendamentos Hoje', value: d.today_count || 0, icon: 'calendar', sub: `${(d.appointments_count || {}).confirmed || 0} confirmados / ${(d.appointments_count || {}).pending || 0} pendentes` },
			{ cls: '', variant: '', label: 'Total do Período', value: (d.appointments_count || {}).total || 0, icon: 'list', sub: `${d.month_confirmed || 0} confirmados · ${d.month_cancelled || 0} cancelados` },
			{ cls: '', variant: 'warning', label: 'Taxa de Ocupação', value: (d.occupancy || {}).rate + '%', icon: 'clock', sub: `${(d.occupancy || {}).occupied || 0} ocupados / ${(d.occupancy || {}).available || 0} disponíveis` },
			{ cls: '', variant: 'danger', label: 'Clientes Inativos', value: d.inactive_clients || 0, icon: 'user', sub: 'Precisando de resgate' }
		];
		const variantMap = { primary:'ap-kpi-today', success:'ap-kpi-revenue', warning:'ap-kpi-occupancy', danger:'ap-kpi-inactive' };
		return `<div class="ap-kpi-grid">${kpis.map(k => `<div class="ap-kpi ${k.cls} ${variantMap[k.variant] || ''}">
			<div class="ap-kpi-icon">${ic(k.icon)}</div>
			<div class="ap-kpi-value">${k.value}</div>
			${k.sub ? `<div class="ap-kpi-sub">${k.sub}</div>` : ''}
			<div class="ap-kpi-label">${k.label}</div>
		</div>`).join('')}</div>`;
	};

	const renderDashboardSummary = (d, from, to) => `
		<div class="ap-card" style="margin-top:0">
			<div class="ap-card-header">
				<h3 class="ap-card-title">${ic('money')} Resumo Financeiro</h3>
				<span class="ap-muted">Período: ${fmtDate(from)} a ${fmtDate(to)}</span>
			</div>
			<div class="ap-dash-summary">
				<div class="ap-dash-summary-item">
					<div class="ap-dash-summary-label">Receita Recebida</div>
					<div class="ap-dash-summary-value" style="color:var(--ap-success)">${money((d.revenue || {}).received || 0)}</div>
				</div>
				<div class="ap-dash-summary-item">
					<div class="ap-dash-summary-label">A Receber</div>
					<div class="ap-dash-summary-value" style="color:var(--ap-warning)">${money((d.revenue || {}).pending || 0)}</div>
				</div>
				<div class="ap-dash-summary-item">
					<div class="ap-dash-summary-label">Vencidas</div>
					<div class="ap-dash-summary-value" style="color:var(--ap-danger)">${money((d.revenue || {}).overdue || 0)}</div>
				</div>
				<div class="ap-dash-summary-item">
					<div class="ap-dash-summary-label">Despesas Pagas</div>
					<div class="ap-dash-summary-value" style="color:var(--ap-success)">${money((d.expenses || {}).paid || 0)}</div>
				</div>
			</div>
		</div>`;

	/* ── APPOINTMENTS MODULE ───────────────────────── */
	const renderAppointments = () => {
		layout(`<div class="ap-inner-tabs">
			<button class="ap-inner-tab${state.tab === 'agendamentos' ? ' active' : ''}" data-ap-tab="agendamentos">${ic('calendar')} Agendamentos</button>
			<button class="ap-inner-tab${state.tab === 'pacotes' ? ' active' : ''}" data-ap-tab="pacotes">${ic('package')} Pacotes de Agendamentos</button>
			<button class="ap-inner-tab${state.tab === 'gerenciar-pacotes' ? ' active' : ''}" data-ap-tab="gerenciar-pacotes">${ic('list')} Gerenciamento de Pacotes</button>
		</div><div data-ap-content></div>`);
		renderApptTab();
	};

	const renderApptTab = () => {
		const wrap = root.querySelector('[data-ap-content]');
		if (!wrap) return;
		if (state.tab === 'agendamentos') renderApptGrid(wrap);
		else if (state.tab === 'pacotes') renderClientPkgGrid(wrap);
		else renderPkgMgrGrid(wrap);
	};

	/* Appointments Grid */
	const renderApptGrid = wrap => {
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		const q = new URLSearchParams({ per_page: PER_PAGE, page: state.apptPage, orderby: state.apptOrderby, order: state.apptOrder });
		if (state.apptStatus) q.set('status', state.apptStatus);
		if (state.apptDateFrom) q.set('date_from', state.apptDateFrom);
		if (state.apptDateTo) q.set('date_to', state.apptDateTo);
		if (state.apptSearch) q.set('search', state.apptSearch);
		request('/appointments?' + q).then(d => {
			state.appointments = d.total !== undefined ? d : { items: d, total: d.length };
			const items = state.appointments.items || [];
			const rows = items.length ? items.map(a => `<tr class="ap-clickable" data-appt-open="${a.id}">
				<td style="width:48px;color:var(--ap-text-tertiary)">#${a.id}</td><td>${fmtDate(a.appointment_date)}</td><td style="font-weight:var(--ap-weight-semibold)">${normalizeTime(a.start_time)}</td>
				<td><strong>${escape(a.client_name)}</strong></td><td>${escape(a.service_name)}</td>
				<td>${a.package_id ? '<span class="ap-tag">Pacote</span>' : '<span style="color:var(--ap-text-tertiary)">Normal</span>'}</td>
				<td><select class="ap-status-select ap-badge-${a.status}" data-appt-status-change="${a.id}" title="Alterar status">${Object.entries(statusMap).map(([v,l]) => `<option value="${v}" ${a.status === v ? 'selected' : ''}>${l}</option>`).join('')}</select></td>
				<td><span class="ap-avatar ap-avatar--sm" style="vertical-align:middle;margin-right:6px">${photo('', a.professional_name)}</span>${escape(a.professional_name)}</td>
				<td>${overflowMenu('appt_' + a.id, [{ action: 'edit', key: 'appointment', value: a.id, label: 'Editar' }, { action: 'delete', key: 'appointment', value: a.id, label: 'Excluir', cls: 'ap-danger-text' }])}</td>
			</tr>`).join('') : `<tr><td colspan="9" class="ap-table-empty"><div class="ap-empty-icon">${ic('calendar')}</div><div class="ap-empty-title">Nenhum agendamento</div><div class="ap-empty-text">Nenhum agendamento encontrado com os filtros atuais.</div></td></tr>`;
			wrap.innerHTML = `<div class="ap-grid-filters">
				<div style="display:flex;align-items:center;gap:var(--ap-space-2);flex-wrap:wrap;flex:1;min-width:0">
					<input class="ap-input" placeholder="Buscar cliente..." value="${escape(state.apptSearch)}" data-appt-search style="width:220px">
					<select class="ap-select" data-appt-status style="width:150px"><option value="">Todos os status</option>${Object.entries(statusMap).map(([v,l]) => `<option value="${v}" ${state.apptStatus === v ? 'selected' : ''}>${l}</option>`).join('')}</select>
					<input class="ap-input" type="date" value="${state.apptDateFrom}" data-appt-from style="width:160px">
					<span class="ap-filter-sep">até</span>
					<input class="ap-input" type="date" value="${state.apptDateTo}" data-appt-to style="width:160px">
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-appt-filter>${ic('filter')} Filtrar</button>
				</div>
				<button class="ap-btn ap-btn-primary ap-btn-icon" data-new-appointment title="Novo Agendamento">${ic('add')}</button>
			</div>
			${gridCard({
				title: 'Agendamentos',
				icon: 'calendar',
				count: state.appointments.total || items.length,
				table: `<thead><tr>
					<th class="ap-sortable" data-appt-sort="id">ID</th>
					<th class="ap-sortable" data-appt-sort="appointment_date">Data</th>
					<th>Horário</th>
					<th class="ap-sortable" data-appt-sort="client_name">Cliente</th>
					<th>Serviço</th><th>Tipo</th><th class="ap-sortable" data-appt-sort="status">Status</th>
					<th>Profissional</th><th style="width:48px"></th>
				</tr></thead>
				<tbody>${rows}</tbody>`,
				footer: pagination(state.apptPage, state.appointments.total || items.length, 'appt')
			})}`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-error-state"><span class="ap-error-state-icon">${ic('cancel')}</span>Erro: ${escape(e.message)}</div>`; });
	};

	/* Client Packages Grid */
	const renderClientPkgGrid = wrap => {
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		const q = new URLSearchParams({ per_page: PER_PAGE, page: state.clientPkgPage });
		if (state.clientPkgStatus) q.set('status', state.clientPkgStatus);
		if (state.clientPkgDateFrom) q.set('date_from', state.clientPkgDateFrom);
		if (state.clientPkgDateTo) q.set('date_to', state.clientPkgDateTo);
		if (state.clientPkgSearch) q.set('search', state.clientPkgSearch);
		request('/client-packages?' + q).then(d => {
			state.clientPkgs = d;
			const items = d.items || [];
			const rows = items.length ? items.map(cp => {
				const used = Number(cp.used_sessions || 0);
				const total = Number(cp.package_sessions || 0);
				return `<tr class="ap-clickable" data-client-package-open="${cp.id}">
					<td style="color:var(--ap-text-tertiary)">#${cp.id}</td><td>${fmtDate(cp.purchase_date)}</td><td>${escape(cp.client_name)}</td>
					<td>${escape(cp.package_name)}</td>
					<td><span class="ap-badge ap-badge-${cp.status}">${cp.status === 'active' ? 'Ativo' : cp.status === 'cancelled' ? 'Cancelado' : 'Expirado'}</span></td>
					<td><span class="ap-tooltip-trigger" data-pkg-appointments="${cp.id}">${used}/${total}</span></td>
					<td><span class="ap-tooltip-trigger" data-pkg-professionals="${cp.id}">Ver profs.</span></td>
					<td>${overflowMenu('cp_' + cp.id, [
						{ action: 'book', key: 'package', value: cp.id, label: 'Novo Agendamento', icon: 'calendar' },
						{ action: 'delete', key: 'client-package', value: cp.id, label: 'Excluir Pacote', icon: 'trash', cls: 'ap-danger-text' }
					])}</td>
				</tr>`;
			}).join('') : `<tr><td colspan="8" class="ap-table-empty"><div class="ap-empty-icon">${ic('package')}</div><div class="ap-empty-title">Nenhum pacote</div><div class="ap-empty-text">Nenhum pacote de agendamento encontrado.</div></td></tr>`;
			wrap.innerHTML = `<div class="ap-grid-filters">
				<div style="display:flex;align-items:center;gap:var(--ap-space-2);flex-wrap:wrap;flex:1;min-width:0">
					<input class="ap-input" placeholder="Buscar..." value="${escape(state.clientPkgSearch)}" data-cp-search style="width:220px">
					<select class="ap-select" data-cp-status style="width:150px"><option value="">Todos os status</option><option value="active" ${state.clientPkgStatus==='active'?'selected':''}>Ativo</option><option value="cancelled" ${state.clientPkgStatus==='cancelled'?'selected':''}>Cancelado</option><option value="expired" ${state.clientPkgStatus==='expired'?'selected':''}>Expirado</option></select>
					<input class="ap-input" type="date" value="${state.clientPkgDateFrom}" data-cp-from style="width:160px">
					<span class="ap-filter-sep">até</span>
					<input class="ap-input" type="date" value="${state.clientPkgDateTo}" data-cp-to style="width:160px">
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-cp-filter>${ic('filter')} Filtrar</button>
				</div>
				<div style="display:flex;gap:var(--ap-space-2)">
					<button class="ap-btn ap-btn-primary ap-btn-sm" data-new-client-package>${ic('add')} Vincular Pacote</button>
				</div>
			</div>
			${gridCard({
				title: 'Pacotes de Agendamentos',
				icon: 'package',
				count: d.total,
				table: `<thead><tr><th>ID</th><th>Data da Compra</th><th>Cliente</th><th>Pacote</th><th>Status</th><th>Sessoes</th><th>Profissionais</th><th style="width:48px"></th></tr></thead>
				<tbody>${rows}</tbody>`,
				footer: pagination(state.clientPkgPage, d.total, 'clientPkg')
			})}`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-error-state"><span class="ap-error-state-icon">${ic('cancel')}</span>Erro: ${escape(e.message)}</div>`; });
	};

	/* Package Management Grid */
	const renderPkgMgrGrid = wrap => {
		const rows = state.packages.length ? state.packages.map(pkg => `<tr>
			<td style="color:var(--ap-text-tertiary)">#${pkg.id}</td><td><strong>${escape(pkg.name)}</strong></td><td style="font-weight:var(--ap-weight-semibold)">${pkg.sessions}</td>
			<td>${money(pkg.price)}</td><td>${pkg.validity_days} dias</td>
			<td><span class="ap-badge ap-badge-${Number(pkg.active) ? 'active' : 'inactive'}">${Number(pkg.active) ? 'Ativo' : 'Inativo'}</span></td>
			<td>${overflowMenu('pkg_' + pkg.id, [{ action: 'edit', key: 'package', value: pkg.id, label: 'Editar' }, { action: 'delete', key: 'package', value: pkg.id, label: 'Excluir', cls: 'ap-danger-text' }])}</td>
		</tr>`).join('') : `<tr><td colspan="7" class="ap-table-empty"><div class="ap-empty-icon">${ic('package')}</div><div class="ap-empty-title">Nenhum pacote</div><div class="ap-empty-text">Cadastre seu primeiro pacote de agendamentos.</div></td></tr>`;
		wrap.innerHTML = gridCard({
			title: 'Gerenciamento de Pacotes',
			icon: 'list',
			count: state.packages.length,
			table: `<thead><tr><th>ID</th><th>Nome</th><th>Sessoes</th><th>Valor</th><th>Validade</th><th>Status</th><th style="width:48px"></th></tr></thead>
			<tbody>${rows}</tbody>`,
			extra: `<button class="ap-btn ap-btn-primary ap-btn-sm" data-new-package>${ic('add')} Novo Pacote</button>`
		});
	};

	/* ── FINANCIAL MODULE ──────────────────────────── */
	const renderFinancial = () => {
		layout(`<div class="ap-inner-tabs">
			<button class="ap-inner-tab${state.finTab === 'receivables' ? ' active' : ''}" data-fin-tab="receivables">${ic('money')} Receitas</button>
			<button class="ap-inner-tab${state.finTab === 'expenses' ? ' active' : ''}" data-fin-tab="expenses">${ic('list')} Despesas</button>
		</div><div data-fin-summary></div><div data-fin-content></div>`, state.finTab === 'expenses' ? `<button class="ap-btn ap-btn-primary ap-btn-icon" data-new-expense title="Nova Despesa">${ic('add')}</button>` : '');
		loadFinSummary();
		if (state.finTab === 'receivables') renderReceivables();
		else renderExpenses();
	};

	const loadFinSummary = async () => {
		const wrap = root.querySelector('[data-fin-summary]');
		if (!wrap) return;
		try {
			const from = state.periodFrom || monthStart();
			const to = state.periodTo || todayIso();
			const d = await request('/dashboard/stats?date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to));
			wrap.innerHTML = renderDashboardSummary(d, from, to);
		} catch (e) { wrap.innerHTML = ''; }
	};

	const renderReceivables = () => {
		const wrap = root.querySelector('[data-fin-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		const q = new URLSearchParams({ per_page: PER_PAGE, page: state.receivablePage });
		if (state.receivableStatus) q.set('status', state.receivableStatus);
		if (state.receivableDateFrom) q.set('date_from', state.receivableDateFrom);
		if (state.receivableDateTo) q.set('date_to', state.receivableDateTo);
		if (state.receivableSearch) q.set('search', state.receivableSearch);
		request('/financial/receivables?' + q).then(d => {
			state.receivables = d;
			const items = d.items || [];
			const rows = items.length ? items.map(r => `<tr class="ap-clickable" data-receivable-detail="${r.id}">
				<td style="color:var(--ap-text-tertiary)">#${r.id}</td><td>${fmtDate(r.appointment_date)}</td><td>${escape(r.client_name)}</td>
				<td>${escape(r.professional_name)}</td><td>${escape(r.service_name || '#') + r.appointment_id}</td>
				<td style="font-weight:var(--ap-weight-semibold)">${money(r.amount)}</td><td>${r.payment_method || '<span style="color:var(--ap-text-tertiary)">—</span>'}</td>
				<td><span class="ap-badge ap-badge-${r.status}">${finStatusMap[r.status] || r.status}</span></td>
			</tr>`).join('') : `<tr><td colspan="8" class="ap-table-empty"><div class="ap-empty-icon">${ic('money')}</div><div class="ap-empty-title">Nenhuma conta a receber</div><div class="ap-empty-text">Nenhuma conta encontrada.</div></td></tr>`;
			wrap.innerHTML = `<div class="ap-grid-filters">
				<input class="ap-input" placeholder="Buscar..." value="${escape(state.receivableSearch)}" data-rec-search style="width:220px">
				<select class="ap-select" data-rec-status style="width:150px"><option value="">Todos os status</option>${Object.entries(finStatusMap).map(([v,l]) => `<option value="${v}" ${state.receivableStatus===v?'selected':''}>${l}</option>`).join('')}</select>
				<input class="ap-input" type="date" value="${state.receivableDateFrom}" data-rec-from style="width:160px">
				<span class="ap-filter-sep">até</span>
				<input class="ap-input" type="date" value="${state.receivableDateTo}" data-rec-to style="width:160px">
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-rec-filter>${ic('filter')} Filtrar</button>
			</div>
			<div class="ap-card" style="padding:0"><div class="ap-table-wrap" style="border:none;border-radius:0"><table class="ap-table">
				<thead><tr><th>ID</th><th>Data</th><th>Cliente</th><th>Profissional</th><th>Agendamento</th><th>Valor</th><th>Pgto</th><th>Status</th></tr></thead>
				<tbody>${rows}</tbody>
			</table></div></div>
			${pagination(state.receivablePage, d.total, 'receivable')}`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-error-state"><span class="ap-error-state-icon">${ic('cancel')}</span>Erro: ${escape(e.message)}</div>`; });
	};

	const renderExpenses = () => {
		const wrap = root.querySelector('[data-fin-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		const q = new URLSearchParams({ per_page: PER_PAGE, page: state.expensePage });
		if (state.expenseStatus) q.set('status', state.expenseStatus);
		if (state.expenseDateFrom) q.set('date_from', state.expenseDateFrom);
		if (state.expenseDateTo) q.set('date_to', state.expenseDateTo);
		if (state.expenseSearch) q.set('search', state.expenseSearch);
		request('/financial/expenses?' + q).then(d => {
			state.expenses = d;
			const items = d.items || [];
			const rows = items.length ? items.map(e => `<tr>
				<td style="color:var(--ap-text-tertiary)">${escape(e.code || '#' + e.id)}</td><td>${fmtDate(e.expense_date)}</td><td>${fmtDate(e.due_date)}</td>
				<td>${escape(e.description)}</td><td>${escape(e.category || '<span style="color:var(--ap-text-tertiary)">—</span>')}</td><td>${escape(e.supplier || '<span style="color:var(--ap-text-tertiary)">—</span>')}</td>
				<td style="font-weight:var(--ap-weight-semibold)">${money(e.amount)}</td><td>${e.payment_method || '<span style="color:var(--ap-text-tertiary)">—</span>'}</td>
				<td><span class="ap-badge ap-badge-${e.status}">${finStatusMap[e.status] || e.status}</span></td>
				<td>${overflowMenu('exp_' + e.id, [
					{ action: 'edit', key: 'expense', value: e.id, label: 'Editar' },
					{ action: 'pay', key: 'expense', value: e.id, label: 'Registrar Pagamento', icon: 'money' },
					{ action: 'delete', key: 'expense', value: e.id, label: 'Excluir', cls: 'ap-danger-text' }
				])}</td>
			</tr>`).join('') : `<tr><td colspan="10" class="ap-table-empty"><div class="ap-empty-icon">${ic('money')}</div><div class="ap-empty-title">Nenhuma despesa</div><div class="ap-empty-text">Nenhuma despesa encontrada.</div></td></tr>`;
			wrap.innerHTML = `<div class="ap-grid-filters">
				<input class="ap-input" placeholder="Buscar..." value="${escape(state.expenseSearch)}" data-exp-search style="width:220px">
				<select class="ap-select" data-exp-status style="width:150px"><option value="">Todos os status</option>${Object.entries(finStatusMap).map(([v,l]) => `<option value="${v}" ${state.expenseStatus===v?'selected':''}>${l}</option>`).join('')}</select>
				<input class="ap-input" type="date" value="${state.expenseDateFrom}" data-exp-from style="width:160px">
				<span class="ap-filter-sep">até</span>
				<input class="ap-input" type="date" value="${state.expenseDateTo}" data-exp-to style="width:160px">
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-exp-filter>${ic('filter')} Filtrar</button>
			</div>
			<div class="ap-card" style="padding:0"><div class="ap-table-wrap" style="border:none;border-radius:0"><table class="ap-table">
				<thead><tr><th>Codigo</th><th>Data</th><th>Vencimento</th><th>Descricao</th><th>Categoria</th><th>Fornecedor</th><th>Valor</th><th>Pgto</th><th>Status</th><th style="width:48px"></th></tr></thead>
				<tbody>${rows}</tbody>
			</table></div></div>
			${pagination(state.expensePage, d.total, 'expense')}`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-error-state"><span class="ap-error-state-icon">${ic('cancel')}</span>Erro: ${escape(e.message)}</div>`; });
	};

	/* ── CLIENTS MODULE ────────────────────────────── */
	const renderClients = () => {
		layout(`<div class="ap-inner-tabs">
			<button class="ap-inner-tab${state.clientTab === 'list' ? ' active' : ''}" data-client-tab="list">${ic('user')} Clientes</button>
			<button class="ap-inner-tab${state.clientTab === 'inactive' ? ' active' : ''}" data-client-tab="inactive">${ic('clock')} Clientes Inativos</button>
			<button class="ap-inner-tab${state.clientTab === 'detail' ? ' active' : ''}" data-client-tab="detail" ${!state.clientDetailId ? 'disabled' : ''}>${ic('edit')} Detalhe do Cliente</button>
		</div><div data-client-content></div>`, state.clientTab === 'list' ? `<button class="ap-btn ap-btn-primary ap-btn-icon" data-new-client title="Novo Cliente">${ic('add')}</button>` : '');
		if (state.clientTab === 'detail' && state.clientDetailId) renderClientDetail();
		else if (state.clientTab === 'inactive') renderInactiveClients();
		else renderClientList();
	};

	const renderClientList = () => {
		const wrap = root.querySelector('[data-client-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		request('/clients').then(clients => {
			state.clients = clients;
			const matching = clients.filter(c => `${c.name} ${c.whatsapp}`.toLowerCase().includes(state.clientSearch.toLowerCase()));
			const total = Math.max(1, Math.ceil(matching.length / 10));
			if (state.clientPage > total) state.clientPage = total;
			const rows = matching.length ? matching.slice((state.clientPage - 1) * 10, state.clientPage * 10).map(c => `<tr>
				<td style="color:var(--ap-text-tertiary)">#${c.id}</td>
				<td><div class="ap-avatar ap-avatar--sm" style="display:inline-flex;vertical-align:middle;margin-right:8px;background:var(--ap-primary-bg);color:var(--ap-primary);font-weight:var(--ap-weight-bold)">${escape((c.name||'?').charAt(0).toUpperCase())}</div><strong>${escape(c.name)}</strong></td>
				<td>${escape(maskPhone(c.whatsapp))}</td>
				<td>${c.last_appointment ? fmtDate(c.last_appointment) : '<span style="color:var(--ap-text-tertiary)">—</span>'}</td>
				<td style="font-weight:var(--ap-weight-semibold)">${Number(c.appointments_count || 0)}</td>
				<td>
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-view-client="${c.id}">Detalhes</button>
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-edit-client="${c.id}">${ic('edit')}</button>
					<button class="ap-btn ap-btn-sm ap-btn-outline" data-book-client="${c.id}">${ic('clock')}</button>
				</td>
			</tr>`).join('') : `<tr><td colspan="6" class="ap-table-empty"><div class="ap-empty-icon">${ic('user')}</div><div class="ap-empty-title">Nenhum cliente</div><div class="ap-empty-text">Nenhum cliente encontrado.</div></td></tr>`;
			wrap.innerHTML = `<div class="ap-grid-filters">
				<input class="ap-input" placeholder="Buscar cliente..." value="${escape(state.clientSearch)}" data-client-search style="width:300px">
			</div>
			${gridCard({
				title: 'Clientes',
				icon: 'user',
				count: matching.length,
				table: `<thead><tr><th>ID</th><th>Cliente</th><th>Telefone</th><th>Última Consulta</th><th>Total</th><th style="width:180px">Ações</th></tr></thead>
				<tbody>${rows}</tbody>`,
				footer: `<div class="ap-pagination">
					<span class="ap-pagination-info">${matching.length} registros &middot; Pág. ${state.clientPage} de ${total}</span>
					<div class="ap-pagination-btns">
						<button class="ap-btn ap-btn-sm ap-btn-outline" data-page-kind="client" data-page-number="${state.clientPage - 1}" ${state.clientPage <= 1 ? 'disabled' : ''}>Anterior</button>
						<button class="ap-btn ap-btn-sm ap-btn-outline" data-page-kind="client" data-page-number="${state.clientPage + 1}" ${state.clientPage >= total ? 'disabled' : ''}>Próxima</button>
					</div>
				</div>`
			})}`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-error-state"><span class="ap-error-state-icon">${ic('cancel')}</span>Erro: ${escape(e.message)}</div>`; });
	};

	const renderInactiveClients = () => {
		const wrap = root.querySelector('[data-client-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		const q = state.inactiveSearch ? '?search=' + encodeURIComponent(state.inactiveSearch) : '';
		request('/reports/inactive-clients' + q).then(clients => {
			state.inactiveClients = clients;
			const rows = clients.length ? clients.map(c => `<tr>
				<td><input type="checkbox" data-inactive-id="${c.id}" ${state.selectedInactive.has(String(c.id))?'checked':''}></td>
				<td><strong>${escape(c.name)}</strong></td><td>${escape(maskPhone(c.whatsapp))}</td>
				<td>${fmtDate(c.last_appointment)}</td>
				<td><button class="ap-btn ap-btn-sm ap-btn-outline" data-view-client="${c.id}">Detalhes</button>
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-notify-client="${c.id}">${ic('phone')} Notificar</button></td>
			</tr>`).join('') : `<tr><td colspan="5" class="ap-table-empty"><div class="ap-empty-icon">${ic('check')}</div><div class="ap-empty-title">Nenhum cliente inativo</div><div class="ap-empty-text">Todos os seus clientes estão ativos.</div></td></tr>`;
			wrap.innerHTML = `<div class="ap-grid-filters">
				<input class="ap-input" placeholder="Buscar cliente..." value="${escape(state.inactiveSearch)}" data-inactive-search style="width:300px">
				<button class="ap-btn ap-btn-primary ap-btn-sm" data-notify-selected ${state.selectedInactive.size === 0 ? 'disabled' : ''}>${ic('phone')} Notificar Selecionados (${state.selectedInactive.size})</button>
			</div>
			${gridCard({
				title: 'Clientes Inativos',
				icon: 'clock',
				count: clients.length,
				table: `<thead><tr><th style="width:40px"><input type="checkbox" data-select-all-inactive></th><th>Cliente</th><th>Telefone</th><th>Última Consulta</th><th style="width:180px">Ações</th></tr></thead>
				<tbody>${rows}</tbody>`
			})}`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-error-state"><span class="ap-error-state-icon">${ic('cancel')}</span>Erro: ${escape(e.message)}</div>`; });
	};

	/* Client Detail */
	const renderClientDetail = () => {
		const wrap = root.querySelector('[data-client-content]');
		if (!wrap || !state.clientDetailId) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		const id = state.clientDetailId;
		Promise.all([
			request('/clients/' + id),
			request('/clients/' + id + '/history'),
			request('/clients/' + id + '/appointments'),
			request('/clients/' + id + '/financial'),
			request('/clients/' + id + '/packages'),
			request('/clients/' + id + '/anamnesis')
		]).then(([client, history, appointments, financial, packages, anamnesis]) => {
			const safeAnamnesis = anamnesis && typeof anamnesis === 'object' ? anamnesis : { sections: [], values: [] };
			wrap.innerHTML = `<div class="ap-client-detail">
				<div class="ap-client-sidebar">
					<div class="ap-client-avatar">${escape((client.name || '?').charAt(0).toUpperCase())}</div>
					<h2 class="ap-client-name">${escape(client.name)}</h2>
					<div class="ap-client-meta">${ic('phone')} ${escape(maskPhone(client.whatsapp))}</div>
					${client.email ? `<div class="ap-client-meta">${escape(client.email)}</div>` : ''}
					<div class="ap-client-nav">
						<button class="ap-client-nav-btn${state.clientTab === 'detail' ? ' active' : ''}" data-client-sub="history">${ic('clock')} Histórico</button>
						<button class="ap-client-nav-btn" data-client-sub="appointments">${ic('calendar')} Agendamentos</button>
						<button class="ap-client-nav-btn" data-client-sub="financial">${ic('money')} Financeiro</button>
						<button class="ap-client-nav-btn" data-client-sub="packages">${ic('package')} Pacotes</button>
						<button class="ap-client-nav-btn" data-client-sub="anamnesis">${ic('list')} Ficha de Anamnese</button>
					</div>
				</div>
				<div class="ap-client-content" data-client-detail-content>
					${renderClientHistoryContent(history)}
				</div>
			</div>`;
			state._clientData = { client, history, appointments, financial, packages, anamnesis: safeAnamnesis };
		}).catch(e => { wrap.innerHTML = `<div class="ap-empty">Erro: ${escape(e.message)}</div>`; });
	};

	const renderClientHistoryContent = history => {
		const events = history || [];
		return `<h3 style="margin:0 0 16px;color:#17233c">Histórico do Cliente</h3>
		<div class="ap-timeline">${events.length ? events.map(e => `<div class="ap-timeline-item">
			<div class="ap-timeline-time">${new Date(e.created_at).toLocaleString('pt-BR')}</div>
			<span class="ap-timeline-type" style="background:#eff6ff;color:#2563eb">${escape(e.event_type)}</span>
			<div class="ap-timeline-desc">${escape(e.description)}</div>
		</div>`).join('') : '<p class="ap-muted">Nenhum histórico registrado.</p>'}</div>`;
	};

	const renderClientApptsContent = appointments => {
		const items = appointments || [];
		return `<h3 style="margin:0 0 16px;color:#17233c">Agendamentos</h3>
		<div class="ap-table-wrap"><table class="ap-table">
			<thead><tr><th>Data</th><th>Horário</th><th>Serviço</th><th>Profissional</th><th>Status</th></tr></thead>
			<tbody>${items.map(a => `<tr>
				<td>${fmtDate(a.appointment_date)}</td><td>${normalizeTime(a.start_time)}</td>
				<td>${escape(a.service_name)}</td><td>${escape(a.professional_name)}</td>
				<td><span class="ap-badge ap-badge-${a.status}">${statusMap[a.status] || a.status}</span></td>
			</tr>`).join('') || '<tr><td colspan="5">Nenhum agendamento.</td></tr>'}</tbody>
		</table></div>`;
	};

	const renderClientFinancialContent = financial => {
		const items = financial || [];
		return `<h3 style="margin:0 0 16px;color:#17233c">Financeiro</h3>
		<div class="ap-table-wrap"><table class="ap-table">
			<thead><tr><th>Agendamento</th><th>Data</th><th>Valor</th><th>Status</th></tr></thead>
			<tbody>${items.map(f => `<tr>
				<td>#${f.appointment_id}</td><td>${fmtDate(f.appointment_date)}</td>
				<td>${money(f.amount)}</td>
				<td><span class="ap-badge ap-badge-${f.status}">${finStatusMap[f.status] || f.status}</span></td>
			</tr>`).join('') || '<tr><td colspan="4">Nenhum registro financeiro.</td></tr>'}</tbody>
		</table></div>`;
	};

	const renderClientPkgContent = packages => {
		const items = packages || [];
		return `<h3 style="margin:0 0 16px;color:#17233c">Pacotes</h3>
		<div class="ap-table-wrap"><table class="ap-table">
			<thead><tr><th>Pacote</th><th>Data Compra</th><th>Validade</th><th>Sessões</th><th>Status</th></tr></thead>
			<tbody>${items.map(cp => `<tr>
				<td>${escape(cp.package_name)}</td><td>${fmtDate(cp.purchase_date)}</td>
				<td>${fmtDate(cp.expiry_date)}</td><td>${cp.used_sessions}/${cp.package_sessions}</td>
				<td><span class="ap-badge ap-badge-${cp.status}">${cp.status === 'active' ? 'Ativo' : cp.status}</span></td>
			</tr>`).join('') || '<tr><td colspan="5">Nenhum pacote.</td></tr>'}</tbody>
		</table></div>`;
	};

	const renderClientAnamnesisContent = anamnesis => {
		const data = anamnesis && typeof anamnesis === 'object' ? anamnesis : {};
		const sections = Array.isArray(data.sections) ? data.sections : [];
		const values = Array.isArray(data.values) ? data.values : [];
		const valueMap = {};
		values.forEach(v => { valueMap[v.field_id] = v.field_value; });
		return `<h3 style="margin:0 0 16px;color:#17233c">Ficha de Anamnese</h3>
		<form data-form="anamnesis" data-client="${state.clientDetailId}">
			${sections.map(s => `<div class="ap-anamnesis-section">
				<h3>${escape(s.name)}</h3>
				${(s.fields || []).map(f => renderAnamnesisField(f, valueMap[f.id] || f.default_value || '')).join('')}
			</div>`).join('') || '<p class="ap-muted">Nenhum campo configurado. Configure a ficha em Configurações.</p>'}
			${sections.length ? '<button class="ap-btn ap-btn-primary" type="submit">' + ic('save') + ' Salvar Ficha</button>' : ''}
		</form>`;
	};

	const renderAnamnesisField = (field, currentValue) => {
		const val = escape(currentValue);
		switch (field.field_type) {
		case 'textarea': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><textarea class="ap-field-input ap-textarea" name="anamnesis_${field.id}" rows="3">${val}</textarea>${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'number': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><input class="ap-field-input" name="anamnesis_${field.id}" type="number" value="${val}" placeholder="${escape(field.placeholder)}">${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'date': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><input class="ap-field-input" name="anamnesis_${field.id}" type="date" value="${val}">${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'time': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><input class="ap-field-input" name="anamnesis_${field.id}" type="time" value="${val}">${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'yesno': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><select class="ap-field-input" name="anamnesis_${field.id}"><option value="">Selecione...</option><option value="Sim" ${val === 'Sim' ? 'selected' : ''}>Sim</option><option value="Não" ${val === 'Não' ? 'selected' : ''}>Não</option></select>${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'select': {
			const opts = (field.options || '').split('\n').filter(Boolean);
			return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><select class="ap-field-input" name="anamnesis_${field.id}"><option value="">Selecione...</option>${opts.map(o => `<option value="${escape(o)}" ${val === o ? 'selected' : ''}>${escape(o)}</option>`).join('')}</select>${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		}
		case 'radio': {
			const opts = (field.options || '').split('\n').filter(Boolean);
			return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><div class="ap-options-inline">${opts.map(o => `<label><input type="radio" name="anamnesis_${field.id}" value="${escape(o)}" ${val === o ? 'checked' : ''}> ${escape(o)}</label>`).join('')}</div>${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		}
		case 'checkbox': return `<div class="ap-anamnesis-field"><label><input type="checkbox" name="anamnesis_${field.id}" value="1" ${val === '1' ? 'checked' : ''}> ${escape(field.label)}${field.required ? ' *' : ''}</label>${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'phone': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><input class="ap-field-input" name="anamnesis_${field.id}" type="tel" value="${val}" placeholder="${escape(field.placeholder)}">${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		case 'email': return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><input class="ap-field-input" name="anamnesis_${field.id}" type="email" value="${val}" placeholder="${escape(field.placeholder)}">${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		default: return `<div class="ap-anamnesis-field"><label>${escape(field.label)}${field.required ? ' *' : ''}</label><input class="ap-field-input" name="anamnesis_${field.id}" type="text" value="${val}" placeholder="${escape(field.placeholder)}">${field.description ? `<div class="ap-help">${escape(field.description)}</div>` : ''}</div>`;
		}
	};

	/* ── SETTINGS ───────────────────────────────────── */
	const renderSettings = () => {
		layout(`<div class="ap-inner-tabs">
			<button class="ap-inner-tab${state.settingsTab === 'general' ? ' active' : ''}" data-settings-tab="general">${ic('settings')} Geral</button>
			<button class="ap-inner-tab${state.settingsTab === 'anamnesis' ? ' active' : ''}" data-settings-tab="anamnesis">${ic('list')} Ficha de Anamnese</button>
			<button class="ap-inner-tab${state.settingsTab === 'kb' ? ' active' : ''}" data-settings-tab="kb">${ic('edit')} Base de Conhecimento</button>
		</div><div data-settings-content></div>`);
		if (state.settingsTab === 'general') renderGeneralSettings();
		else if (state.settingsTab === 'anamnesis') renderAnamnesisSettings();
		else renderKBSettings();
	};

	const renderGeneralSettings = () => {
		const wrap = root.querySelector('[data-settings-content]');
		if (!wrap) return;
		wrap.innerHTML = `<div class="ap-card"><form class="ap-form" data-form="settings">
			<div class="ap-field"><label class="ap-label">Nome da clínica</label><input class="ap-field-input" name="company_name" value="${escape(state.settings.company_name)}"></div>
			<div class="ap-field"><label class="ap-label">URL do logo</label><input class="ap-field-input" name="logo_url" value="${escape(state.settings.logo_url)}"></div>
			<div class="ap-field"><label class="ap-label">Intervalo entre agendamentos (min)</label><input class="ap-field-input" name="interval_minutes" type="number" min="0" value="${escape(state.settings.interval_minutes || 30)}"></div>
			<div class="ap-field"><label class="ap-label">Webhook de saída</label><input class="ap-field-input" name="webhook_url" value="${escape(state.settings.webhook_url)}"></div>
			<div class="ap-field"><label class="ap-label">Segredo do webhook</label><div class="ap-code-block">${escape(state.settings.webhook_secret)}</div></div>
			<div class="ap-settings-section"><h2 class="ap-card-title">Design do formulário</h2>
				<div class="ap-grid-2">
					<div class="ap-field"><label class="ap-label">Cor dos botões</label><input class="ap-field-input ap-color-input" name="form_button_color" type="color" value="${escape(state.settings.form_button_color || '#93BBFF')}"></div>
					<div class="ap-field"><label class="ap-label">Texto dos botões</label><input class="ap-field-input ap-color-input" name="form_button_text_color" type="color" value="${escape(state.settings.form_button_text_color || '#FFFFFF')}"></div>
					<div class="ap-field"><label class="ap-label">Cor dos títulos</label><input class="ap-field-input ap-color-input" name="form_text_color" type="color" value="${escape(state.settings.form_text_color || '#071F41')}"></div>
					<div class="ap-field"><label class="ap-label">Cor dos textos auxiliares</label><input class="ap-field-input ap-color-input" name="form_muted_color" type="color" value="${escape(state.settings.form_muted_color || '#52617A')}"></div>
				</div>
			</div>
			<button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar configurações</button>
		</form></div>`;
	};

	const renderAnamnesisSettings = () => {
		const wrap = root.querySelector('[data-settings-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		request('/anamnesis/sections').then(sections => {
			state.anamnesisSections = sections;
			wrap.innerHTML = sections.map(s => `<div class="ap-card" style="margin-bottom:14px">
				<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
					<h3 style="margin:0;color:#17233c">${escape(s.name)}</h3>
					<div>
						<button class="ap-btn ap-btn-sm ap-btn-outline" data-edit-section="${s.id}">${ic('edit')} Editar</button>
						<button class="ap-btn ap-btn-sm ap-btn-outline" data-add-field="${s.id}">${ic('add')} Add Campo</button>
						<button class="ap-btn ap-btn-sm ap-btn-danger" data-delete-section="${s.id}">${ic('trash')}</button>
					</div>
				</div>
				${(s.fields || []).length ? (s.fields || []).map(f => `<div class="ap-field-card">
					<div class="ap-field-card-head">
						<span class="ap-field-card-name">${escape(f.label)}</span>
						<div>
							<span class="ap-field-card-type">${escape(f.field_type)}</span>
							<button class="ap-btn ap-btn-sm ap-btn-outline" data-edit-field="${f.id}">${ic('edit')}</button>
							<button class="ap-btn ap-btn-sm ap-btn-danger" data-delete-field="${f.id}">${ic('trash')}</button>
						</div>
					</div>
					${f.required ? '<span style="color:#ef4444;font-size:11px">Obrigatório</span> ' : ''}
					<span style="color:#64748b;font-size:11px">${escape(f.description || '')}</span>
				</div>`).join('') : '<p class="ap-muted">Nenhum campo nesta seção.</p>'}
			</div>`).join('') + `<div style="margin-top:14px"><button class="ap-btn ap-btn-primary" data-new-section>${ic('add')} Nova Seção</button></div>`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-empty">Erro: ${escape(e.message)}</div>`; });
	};

	const renderKBSettings = () => {
		const wrap = root.querySelector('[data-settings-content]');
		if (!wrap) return;
		wrap.innerHTML = '<div class="ap-spinner"></div>';
		request('/knowledge-base').then(items => {
			state.kbItems = items;
			wrap.innerHTML = items.map(kb => `<div class="ap-field-card">
				<div class="ap-field-card-head">
					<div><strong>${escape(kb.title)}</strong><br><span style="color:#64748b;font-size:11px">${escape(kb.category || 'Sem categoria')}</span></div>
					<div>
						<button class="ap-btn ap-btn-sm ap-btn-outline" data-edit-kb="${kb.id}">${ic('edit')}</button>
						<button class="ap-btn ap-btn-sm ap-btn-danger" data-delete-kb="${kb.id}">${ic('trash')}</button>
					</div>
				</div>
				<div style="color:#475569;font-size:12px;margin-top:6px">${escape((kb.content || '').substring(0, 200))}...</div>
			</div>`).join('') + `<div style="margin-top:14px"><button class="ap-btn ap-btn-primary" data-new-kb>${ic('add')} Novo Artigo</button></div>`;
		}).catch(e => { wrap.innerHTML = `<div class="ap-empty">Erro: ${escape(e.message)}</div>`; });
	};

	/* ── MODALS ─────────────────────────────────────── */
	const openModal = (title, html) => {
		const overlay = document.createElement('div');
		overlay.className = 'ap-modal-overlay';
		overlay.innerHTML = `<div class="ap-modal"><div class="ap-modal-header"><h2 class="ap-modal-title">${title}</h2><button class="ap-close" type="button" data-close>×</button></div><div class="ap-modal-body">${html}</div></div>`;
		document.body.appendChild(overlay);
	};

	const closeModal = () => {
		const m = document.querySelectorAll('.ap-modal-overlay');
		if (m.length) m[m.length - 1].remove();
	};

	const closeAllPopovers = () => {
		document.querySelectorAll('.ap-popover.show, .ap-overflow-menu.show').forEach(el => el.classList.remove('show'));
		state.overflows = {};
	};

	/* ── FORMS ──────────────────────────────────────── */
	const renderClientSearchResults = (prefix, rawQuery) => {
		const results = document.querySelector('[data-' + prefix + '-client-results]');
		if (!results) return;
		const q = rawQuery.toLowerCase().trim();
		if (!q) { results.style.display = 'none'; return; }
		const matches = state.clients.filter(c => (c.name || '').toLowerCase().includes(q) || (c.whatsapp || '').includes(q.replace(/\D/g, ''))).slice(0, 8);
		results.style.display = 'block';
		results.innerHTML = matches.length
			? matches.map(c => `<div data-${prefix}-select-client="${c.id}" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--ap-border-light);font-size:var(--ap-font-size-md);transition:background var(--ap-transition-fast);display:flex;align-items:center;gap:8px" onmouseover="this.style.background='var(--ap-primary-bg)'" onmouseout="this.style.background=''">
				<span class="ap-avatar ap-avatar--sm" style="background:var(--ap-primary-bg);color:var(--ap-primary);font-weight:var(--ap-weight-bold)">${escape((c.name || '?').charAt(0).toUpperCase())}</span>
				<div><strong>${escape(c.name)}</strong><br><span style="color:var(--ap-text-secondary);font-size:var(--ap-font-size-xs)">${escape(maskPhone(c.whatsapp))}</span></div>
			</div>`).join('')
			: '<div style="padding:12px;color:var(--ap-text-tertiary);text-align:center;font-size:var(--ap-font-size-sm)">Nenhum cliente encontrado.</div>';
	};

	const selectClient = (prefix, id) => {
		const c = state.clients.find(x => String(x.id) === String(id));
		if (!c) return;
		const search = document.querySelector('[data-' + prefix + '-client-search]');
		const form = search ? search.closest('form') : null;
		const results = form ? form.querySelector('[data-' + prefix + '-client-results]') : null;
		if (search) search.value = c.name;
		if (results) results.style.display = 'none';
		const set = (name, val) => { const el = form ? form.querySelector('[name="' + name + '"]') : null; if (el) el.value = val; };
		set('client_id', c.id);
		set('client_name', c.name);
		set('whatsapp', c.whatsapp || '');
		const hint = form ? form.querySelector('[data-' + prefix + '-client-selected]') : null;
		if (hint) hint.textContent = 'Selecionado: ' + c.name + (c.whatsapp ? ' · ' + maskPhone(c.whatsapp) : '');
		const submit = form ? form.querySelector('[data-' + prefix + '-submit]') : null;
		if (submit) submit.disabled = false;
	};

	const clientSearchField = (prefix, prefill = {}) => `
		<div class="ap-field">
			<label class="ap-label">Cliente *</label>
			<input class="ap-input" data-${prefix}-client-search placeholder="Buscar por nome ou WhatsApp..." autocomplete="off" value="${escape(prefill.client_name || '')}">
			<div data-${prefix}-client-results style="max-height:180px;overflow-y:auto;display:none;border:1px solid var(--ap-border);border-radius:var(--ap-radius);margin-top:4px"></div>
			<input type="hidden" name="client_id" value="${escape(prefill.client_id || '')}">
			<input type="hidden" name="client_name" value="${escape(prefill.client_name || '')}">
			<input type="hidden" name="whatsapp" value="${escape(prefill.whatsapp || '')}">
			<div class="ap-hint" data-${prefix}-client-selected>${prefill.client_name ? 'Selecionado: ' + escape(prefill.client_name) + (prefill.whatsapp ? ' · ' + escape(maskPhone(prefill.whatsapp)) : '') : ''}</div>
			<button class="ap-btn ap-btn-sm ap-btn-outline" type="button" data-${prefix}-new-client style="margin-top:var(--ap-space-2)">${ic('add')} Cadastrar novo cliente</button>
		</div>`;

	const loadApptSlots = async () => {
		const form = document.querySelector('[data-form="appointment"]');
		if (!form) return;
		const wrap = form.querySelector('[data-appt-slots]');
		if (!wrap) return;
		const sid = form.querySelector('[name="service_id"]').value;
		const pid = form.querySelector('[name="professional_id"]').value;
		const date = form.querySelector('[name="appointment_date"]').value;
		if (!sid || !pid || !date) {
			wrap.innerHTML = '<div class="ap-slots-loading">Selecione serviço, profissional e data para ver os horários disponíveis.</div>';
			return;
		}
		wrap.innerHTML = '<div class="ap-slots-loading">Carregando horários disponíveis...</div>';
		try {
			const d = await request('/availability?service_id=' + encodeURIComponent(sid) + '&professional_id=' + encodeURIComponent(pid) + '&date=' + encodeURIComponent(date));
			const row = (d.dates && d.dates[0]) || {};
			const pro = (row.professionals && row.professionals[0]) || {};
			const slots = pro.slots || [];
			if (!slots.length) {
				const hidden = form.querySelector('[name="start_time"]');
				if (hidden) hidden.value = '';
				wrap.innerHTML = '<div class="ap-slots-loading">Nenhum horário disponível para esta data.</div>';
				return;
			}
			let current = form.querySelector('[name="start_time"]').value;
			if (current && !slots.some(s => s.start === current)) {
				current = '';
				const hidden = form.querySelector('[name="start_time"]');
				if (hidden) hidden.value = '';
			}
			wrap.innerHTML = '<div class="ap-slot-group"><div class="ap-slot-group-title">Horários disponíveis</div><div class="ap-time-grid">' +
				slots.map(s => `<button type="button" class="ap-time-chip${current === s.start ? ' selected' : ''}" data-appt-slot="${s.start}">${s.start}</button>`).join('') +
				'</div></div>';
		} catch (e) {
			wrap.innerHTML = '<div class="ap-slots-loading">Erro ao carregar horários.</div>';
		}
	};

	const openAppointmentFromPackage = async (id) => {
		let cp;
		try { cp = await request('/client-packages/' + id); } catch (e) { toast(e.message, 'error'); return; }
		if (!cp) return;
		let service_id = '', professional_id = '', package_services = [], package_professionals = [];
		if (cp.package_id) {
			try {
				const pkg = await request('/packages/' + cp.package_id);
				package_services = pkg.services || [];
				package_professionals = pkg.professionals || [];
				if (package_services.length) service_id = package_services[0].id;
				if (package_professionals.length) professional_id = package_professionals[0].id;
			} catch (e) {}
		}
		closeModal();
		openModal('Novo Agendamento (Pacote ' + escape(cp.package_name || '') + ')', appointmentForm({
			client_id: cp.client_id,
			client_name: cp.client_name,
			whatsapp: cp.client_whatsapp,
			package_id: cp.id,
			service_id: service_id,
			professional_id: professional_id,
			package_services: package_services,
			package_professionals: package_professionals
		}));
		loadApptSlots();
	};

	const appointmentForm = (prefill = {}) => {
		const allowedServices = (prefill.package_services && prefill.package_services.length) ? prefill.package_services : state.services;
		const allowedProfessionals = (prefill.package_professionals && prefill.package_professionals.length) ? prefill.package_professionals : state.professionals;
		return `<form class="ap-form" data-form="appointment" data-id="${prefill.id || ''}">
			<div class="ap-form-section">
				<h3 class="ap-form-section-title">Informações do Cliente</h3>
				${clientSearchField('appt', prefill)}
			</div>
			<div class="ap-form-section">
				<h3 class="ap-form-section-title">Detalhes do Agendamento</h3>
				<div class="ap-grid-2">
					<div class="ap-field"><label class="ap-label">Serviço *</label><select class="ap-select" name="service_id" data-appt-service required><option value="">Selecione...</option>${allowedServices.map(s => `<option value="${s.id}" ${prefill.service_id == s.id ? 'selected' : ''}>${escape(s.name)}</option>`).join('')}</select></div>
					<div class="ap-field"><label class="ap-label">Profissional *</label><select class="ap-select" name="professional_id" data-appt-professional required><option value="">Selecione...</option>${allowedProfessionals.map(p => `<option value="${p.id}" ${prefill.professional_id == p.id ? 'selected' : ''}>${escape(p.name)}</option>`).join('')}</select></div>
				</div>
				<div class="ap-field"><label class="ap-label">Data *</label><input class="ap-input" name="appointment_date" type="date" data-appt-date value="${prefill.appointment_date || todayIso()}" required></div>
				<div class="ap-field">
					<label class="ap-label">Horário *</label>
					<input type="hidden" name="start_time" value="${prefill.start_time || ''}">
					<div data-appt-slots></div>
				</div>
				<div class="ap-field"><label class="ap-label">Status</label><select class="ap-select" name="status"><option value="pending" ${prefill.status === 'pending' ? 'selected' : ''}>Pendente</option><option value="confirmed" ${prefill.status !== 'pending' && prefill.status !== 'cancelled' ? 'selected' : ''}>Confirmado</option><option value="cancelled" ${prefill.status === 'cancelled' ? 'selected' : ''}>Cancelado</option></select></div>
			</div>
			${prefill.package_id ? `<input type="hidden" name="package_id" value="${prefill.package_id}">` : ''}
			<div class="ap-field"><label class="ap-label">Observações</label><textarea class="ap-input" name="notes" style="min-height:70px">${escape(prefill.notes || '')}</textarea></div>
			<div class="ap-modal-footer"><button class="ap-btn ap-btn-outline" data-close type="button">Cancelar</button><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
		</form>`;
	};

	const openAppointmentModal = (a) => {
		if (!a) return;
		openModal('Editar Agendamento', appointmentForm({ id: a.id, client_id: a.client_id, client_name: a.client_name, whatsapp: a.client_whatsapp, service_id: a.service_id, professional_id: a.professional_id, appointment_date: a.appointment_date, start_time: a.start_time, notes: a.notes, status: a.status, package_id: a.package_id }));
		loadApptSlots();
	};

	const packageForm = (pkg = {}) => {
		const svcIds = (pkg.services || []).map(s => Number(s.id));
		const proIds = (pkg.professionals || []).map(p => Number(p.id));
		return `<form class="ap-form" data-form="package" data-id="${pkg.id || ''}">
			<div class="ap-form-section">
				<h3 class="ap-form-section-title">Informações do Pacote</h3>
				<div class="ap-field"><label class="ap-label">Nome *</label><input class="ap-field-input" name="name" value="${escape(pkg.name)}" required></div>
				<div class="ap-field"><label class="ap-label">Descrição</label><textarea class="ap-field-input ap-textarea" name="description">${escape(pkg.description)}</textarea></div>
				<div class="ap-grid-3">
					<div class="ap-field"><label class="ap-label">Sessões *</label><input class="ap-field-input" name="sessions" type="number" min="1" value="${escape(pkg.sessions || 1)}" required></div>
					<div class="ap-field"><label class="ap-label">Valor</label><input class="ap-field-input" name="price" type="number" step="0.01" value="${escape(pkg.price || 0)}"></div>
					<div class="ap-field"><label class="ap-label">Validade (dias)</label><input class="ap-field-input" name="validity_days" type="number" min="1" value="${escape(pkg.validity_days || 365)}"></div>
				</div>
			</div>
			<div class="ap-form-section">
				<h3 class="ap-form-section-title">Serviços e Profissionais</h3>
				<div class="ap-field"><label class="ap-label">Serviços permitidos</label>${state.services.map(s => `<label class="ap-tag"><input type="checkbox" name="service_ids" value="${s.id}" ${svcIds.includes(Number(s.id)) ? 'checked' : ''}> ${escape(s.name)}</label>`).join('')}</div>
				<div class="ap-field"><label class="ap-label">Profissionais (opcional)</label>${state.professionals.map(p => `<label class="ap-tag"><input type="checkbox" name="professional_ids" value="${p.id}" ${proIds.includes(Number(p.id)) ? 'checked' : ''}> ${escape(p.name)}</label>`).join('')}</div>
				<label><input name="active" type="checkbox" ${Number(pkg.active ?? 1) ? 'checked' : ''}> Ativo</label>
			</div>
			<div class="ap-modal-footer"><button class="ap-btn ap-btn-outline" data-close type="button">Cancelar</button><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
		</form>`;
	};

	const clientPackageForm = () => {
		const activePackages = state.packages.filter(p => Number(p.active));
		if (!activePackages.length) return '<div class="ap-empty"><div class="ap-empty-icon">' + ic('package') + '</div><div class="ap-empty-title">Nenhum pacote disponível</div><div class="ap-empty-text">Crie pacotes em Gerenciamento de Pacotes antes de vincular.</div></div>';
		return `<form class="ap-form" data-form="clientPackage">
			<div class="ap-form-section">
				<h3 class="ap-form-section-title">Informações do Cliente</h3>
				${clientSearchField('cp')}
			</div>
			<div class="ap-form-section">
				<h3 class="ap-form-section-title">Informações do Pacote</h3>
				<div class="ap-field"><label class="ap-label">Pacote *</label><select class="ap-select" name="package_id" required><option value="">Selecione...</option>${activePackages.map(p => `<option value="${p.id}">${escape(p.name)} (${p.sessions} sessões - ${money(p.price)})</option>`).join('')}</select></div>
			</div>
			<div class="ap-modal-footer"><button class="ap-btn ap-btn-outline" data-close type="button">Cancelar</button><button class="ap-btn ap-btn-primary" type="submit" data-cp-submit disabled>${ic('save')} Vincular Pacote</button></div>
		</form>`;
	};

	const clientPackageDetail = (cp) => {
		const used = Number(cp.used_sessions || 0);
		const total = Number(cp.package_sessions || 0);
		const statusLabel = cp.status === 'active' ? 'Ativo' : cp.status === 'cancelled' ? 'Cancelado' : 'Expirado';
		return `<div class="ap-financial-detail">
			<dl><dt>Cliente</dt><dd>${escape(cp.client_name)}</dd></dl>
			<dl><dt>Pacote</dt><dd>${escape(cp.package_name)}</dd></dl>
			<dl><dt>Data da Compra</dt><dd>${fmtDate(cp.purchase_date)}</dd></dl>
			<dl><dt>Validade</dt><dd>${fmtDate(cp.expiry_date)}</dd></dl>
			<dl><dt>Sessões Utilizadas</dt><dd>${used} de ${total}</dd></dl>
			<dl><dt>Status</dt><dd><span class="ap-badge ap-badge-${cp.status}">${statusLabel}</span></dd></dl>
			<div style="display:flex;gap:var(--ap-space-2);flex-wrap:wrap;margin-top:var(--ap-space-4);padding-top:var(--ap-space-4);border-top:1px solid var(--ap-border-light)">
				<button class="ap-btn ap-btn-primary ap-btn-sm" data-book-package="${cp.id}">${ic('calendar')} Novo Agendamento</button>
				<button class="ap-btn ap-btn-danger ap-btn-sm" data-delete-client-package="${cp.id}">${ic('trash')} Excluir Pacote</button>
			</div>
		</div>`;
	};

	const openClientPackageModal = async (id) => {
		const cp = (state.clientPkgs.items || []).find(x => String(x.id) === String(id)) || await request('/client-packages/' + id);
		if (!cp) return;
		openModal('Pacote do Cliente #' + id, clientPackageDetail(cp));
	};

	const expenseForm = (exp = {}) => `<form class="ap-form" data-form="expense" data-id="${exp.id || ''}">
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Código</label><input class="ap-field-input" name="code" value="${escape(exp.code)}"></div>
			<div class="ap-field"><label class="ap-label">Categoria</label><input class="ap-field-input" name="category" value="${escape(exp.category)}"></div>
		</div>
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Data *</label><input class="ap-field-input" name="expense_date" type="date" value="${exp.expense_date || todayIso()}" required></div>
			<div class="ap-field"><label class="ap-label">Vencimento *</label><input class="ap-field-input" name="due_date" type="date" value="${exp.due_date || todayIso()}" required></div>
		</div>
		<div class="ap-field"><label class="ap-label">Descrição *</label><input class="ap-field-input" name="description" value="${escape(exp.description)}" required></div>
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Fornecedor</label><input class="ap-field-input" name="supplier" value="${escape(exp.supplier)}"></div>
			<div class="ap-field"><label class="ap-label">Valor *</label><input class="ap-field-input" name="amount" type="number" step="0.01" value="${escape(exp.amount || 0)}" required></div>
		</div>
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Forma de pagamento</label><select class="ap-field-input" name="payment_method"><option value="">Selecione...</option>${['Dinheiro','Cartão de Crédito','Cartão de Débito','Pix','Transferência','Boleto'].map(m => `<option value="${m}" ${(exp.payment_method||'') === m ? 'selected' : ''}>${m}</option>`).join('')}</select></div>
			<div class="ap-field"><label class="ap-label">Status</label><select class="ap-field-input" name="status">${Object.entries(finStatusMap).map(([v,l]) => `<option value="${v}" ${(exp.status||'pending') === v ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
		</div>
		<div class="ap-field"><label class="ap-label">Observações</label><textarea class="ap-field-input ap-textarea" name="notes">${escape(exp.notes)}</textarea></div>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
	</form>`;

	const newClientForm = () => `<form class="ap-form" data-form="client" data-id="">
		<div class="ap-form-section">
			<h3 class="ap-form-section-title">Informações do Cliente</h3>
			<div class="ap-grid-2">
				<div class="ap-field"><label class="ap-label">Nome *</label><input class="ap-input" name="name" required placeholder="Nome completo"></div>
				<div class="ap-field"><label class="ap-label">WhatsApp *</label><input class="ap-input ap-phone-mask" name="whatsapp" required placeholder="(00) 00000-0000"></div>
			</div>
			<div class="ap-field"><label class="ap-label">E-mail</label><input class="ap-input" name="email" type="email" placeholder="email@exemplo.com"></div>
			<div class="ap-field"><label class="ap-label">Data de nascimento</label><input class="ap-input" name="birth_date" type="date"></div>
			<div class="ap-field"><label class="ap-label">Endereço</label><textarea class="ap-input" name="address" style="min-height:60px" placeholder="Rua, número, bairro..."></textarea></div>
			<div class="ap-field"><label class="ap-label">Observações</label><textarea class="ap-input" name="notes" style="min-height:60px"></textarea></div>
		</div>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-outline" data-close type="button">Cancelar</button><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Criar Cliente</button></div>
	</form>`;

	const clientForm = (c = {}) => `<form class="ap-form" data-form="client" data-id="${c.id || ''}">
		<div class="ap-field"><label class="ap-label">Celular</label><input class="ap-field-input" value="${escape(maskPhone(c.whatsapp))}" disabled></div>
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Nome</label><input class="ap-field-input" name="name" value="${escape(c.name)}" required></div>
			<div class="ap-field"><label class="ap-label">E-mail</label><input class="ap-field-input" name="email" type="email" value="${escape(c.email)}"></div>
		</div>
		<div class="ap-field"><label class="ap-label">Data de nascimento</label><input class="ap-field-input" name="birth_date" type="date" value="${escape(c.birth_date || '')}"></div>
		<div class="ap-field"><label class="ap-label">Endereço</label><textarea class="ap-field-input ap-textarea" name="address">${escape(c.address)}</textarea></div>
		<div class="ap-field"><label class="ap-label">Observações</label><textarea class="ap-field-input ap-textarea" name="notes">${escape(c.notes)}</textarea></div>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
	</form>`;

	const serviceForm = (svc = {}) => `<form class="ap-form" data-form="service" data-id="${svc.id || ''}">
		<div class="ap-field"><label class="ap-label">Nome</label><input class="ap-field-input" name="name" value="${escape(svc.name)}" required></div>
		<div class="ap-field"><label class="ap-label">Descrição</label><textarea class="ap-field-input ap-textarea" name="description">${escape(svc.description)}</textarea></div>
		<div class="ap-grid-3">
			<div class="ap-field"><label class="ap-label">Duração (min)</label><input class="ap-field-input" name="duration" type="number" min="5" value="${escape(svc.duration || 60)}"></div>
			<div class="ap-field"><label class="ap-label">Preço</label><input class="ap-field-input" name="price" type="number" step="0.01" value="${escape(svc.price || 0)}"></div>
			<div class="ap-field"><label class="ap-label">Cor</label><input class="ap-field-input ap-color-input" name="color" type="color" value="${escape(svc.color || '#4F46E5')}"></div>
		</div>
		<label><input name="active" type="checkbox" ${Number(svc.active ?? 1) ? 'checked' : ''}> Ativo</label>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
	</form>`;

	const professionalForm = (pro = {}) => {
		const svcIds = (pro.services || []).map(s => Number(s.id));
		const hours = pro.hours && pro.hours.length ? pro.hours : defaultHours();
		return `<form class="ap-form" data-form="professional" data-id="${pro.id || ''}">
			<div class="ap-field"><label class="ap-label">Nome</label><input class="ap-field-input" name="name" value="${escape(pro.name)}" required></div>
			<div class="ap-grid-2">
				<div class="ap-field"><label class="ap-label">E-mail</label><input class="ap-field-input" name="email" type="email" value="${escape(pro.email)}"></div>
				<div class="ap-field"><label class="ap-label">WhatsApp</label><input class="ap-field-input ap-phone-mask" name="whatsapp" value="${escape(maskPhone(pro.whatsapp))}"></div>
			</div>
			<div class="ap-field"><label class="ap-label">Especialidades</label><input class="ap-field-input" name="specialties" value="${escape(pro.specialties)}"></div>
			<div class="ap-field"><label class="ap-label">URL da foto</label><input class="ap-field-input" name="photo_url" type="url" placeholder="https://..." value="${escape(pro.photo_url)}"></div>
			<div class="ap-field"><label class="ap-label">Serviços</label>${state.services.map(s => `<label class="ap-tag"><input type="checkbox" name="service_ids" value="${s.id}" ${svcIds.includes(Number(s.id)) ? 'checked' : ''}> ${escape(s.name)}</label>`).join('')}</div>
			<div class="ap-field"><label class="ap-label">Horários</label><div class="ap-hours-table" data-hours>${hours.map(h => hourRow(h)).join('')}</div><button type="button" class="ap-btn ap-btn-outline ap-btn-sm" data-add-hour>${ic('add')} Adicionar</button></div>
			<label><input name="active" type="checkbox" ${Number(pro.active ?? 1) ? 'checked' : ''}> Ativo</label>
			<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
		</form>`;
	};

	const defaultHours = () => [1, 2, 3, 4, 5].flatMap(d => [
		{ day_of_week: d, start_time: '08:00', end_time: '12:00', interval_minutes: 0, active: 1 },
		{ day_of_week: d, start_time: '13:30', end_time: '19:00', interval_minutes: 0, active: 1 }
	]);

	const hourRow = (h = {}) => {
		const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
		return `<div class="ap-hours-row" data-hour-row>
			<select class="ap-field-input" name="hour_day">${days.map((l, i) => `<option value="${i}" ${(Number(h.day_of_week ?? 1) === i) ? 'selected' : ''}>${l}</option>`).join('')}</select>
			<input class="ap-field-input" name="hour_start" type="time" value="${normalizeTime(h.start_time || '08:00')}">
			<input class="ap-field-input" name="hour_end" type="time" value="${normalizeTime(h.end_time || '12:00')}">
			<input class="ap-field-input" name="hour_interval" type="number" min="0" value="${h.interval_minutes || 0}" title="Intervalo min">
			<button type="button" class="ap-icon-btn ap-danger-soft" data-remove-hour>${ic('trash')}</button>
		</div>`;
	};

	const kbForm = (kb = {}) => `<form class="ap-form" data-form="kb" data-id="${kb.id || ''}">
		<div class="ap-field"><label class="ap-label">Título</label><input class="ap-field-input" name="title" value="${escape(kb.title)}" required></div>
		<div class="ap-field"><label class="ap-label">Categoria</label><input class="ap-field-input" name="category" value="${escape(kb.category)}"></div>
		<div class="ap-field"><label class="ap-label">Conteúdo</label><div class="ap-kb-editor"><textarea name="content" rows="12">${escape(kb.content)}</textarea></div></div>
		<label><input name="active" type="checkbox" ${Number(kb.active ?? 1) ? 'checked' : ''}> Ativo</label>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
	</form>`;

	const sectionForm = (s = {}) => `<form class="ap-form" data-form="section" data-id="${s.id || ''}">
		<div class="ap-field"><label class="ap-label">Nome da Seção</label><input class="ap-field-input" name="name" value="${escape(s.name)}" required></div>
		<div class="ap-field"><label class="ap-label">Descrição</label><input class="ap-field-input" name="description" value="${escape(s.description)}"></div>
		<div class="ap-field"><label class="ap-label">Ordem</label><input class="ap-field-input" name="sort_order" type="number" value="${s.sort_order || 0}"></div>
		<label><input name="active" type="checkbox" ${Number(s.active ?? 1) ? 'checked' : ''}> Ativo</label>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
	</form>`;

	const fieldForm = (f = {}) => `<form class="ap-form" data-form="field" data-id="${f.id || ''}">
		<input type="hidden" name="section_id" value="${f.section_id || ''}">
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Nome do campo</label><input class="ap-field-input" name="name" value="${escape(f.name)}" required></div>
			<div class="ap-field"><label class="ap-label">Label</label><input class="ap-field-input" name="label" value="${escape(f.label)}" required></div>
		</div>
		<div class="ap-field"><label class="ap-label">Descrição / Ajuda</label><input class="ap-field-input" name="description" value="${escape(f.description)}"></div>
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Tipo</label><select class="ap-field-input" name="field_type"><option value="text" ${f.field_type === 'text' ? 'selected' : ''}>Texto</option><option value="textarea" ${f.field_type === 'textarea' ? 'selected' : ''}>Textarea</option><option value="number" ${f.field_type === 'number' ? 'selected' : ''}>Número</option><option value="date" ${f.field_type === 'date' ? 'selected' : ''}>Data</option><option value="time" ${f.field_type === 'time' ? 'selected' : ''}>Hora</option><option value="checkbox" ${f.field_type === 'checkbox' ? 'selected' : ''}>Checkbox</option><option value="radio" ${f.field_type === 'radio' ? 'selected' : ''}>Radio</option><option value="select" ${f.field_type === 'select' ? 'selected' : ''}>Select</option><option value="yesno" ${f.field_type === 'yesno' ? 'selected' : ''}>Sim/Não</option><option value="phone" ${f.field_type === 'phone' ? 'selected' : ''}>Telefone</option><option value="email" ${f.field_type === 'email' ? 'selected' : ''}>E-mail</option></select></div>
			<div class="ap-field"><label class="ap-label">Ordem</label><input class="ap-field-input" name="sort_order" type="number" value="${f.sort_order || 0}"></div>
		</div>
		<div class="ap-field"><label class="ap-label">Placeholder</label><input class="ap-field-input" name="placeholder" value="${escape(f.placeholder)}"></div>
		<div class="ap-field"><label class="ap-label">Valor padrão</label><input class="ap-field-input" name="default_value" value="${escape(f.default_value)}"></div>
		<div class="ap-field"><label class="ap-label">Opções (uma por linha, para Select/Radio)</label><textarea class="ap-field-input ap-textarea" name="options" rows="3">${escape(f.options)}</textarea></div>
		<label><input name="required" type="checkbox" ${Number(f.required) ? 'checked' : ''}> Obrigatório</label>
		<label><input name="active" type="checkbox" ${Number(f.active ?? 1) ? 'checked' : ''}> Ativo</label>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('save')} Salvar</button></div>
	</form>`;

	const payForm = (type, item) => `<form class="ap-form" data-form="pay-${type}" data-id="${item.id}">
		<div class="ap-financial-detail"><dl><dt>Valor</dt><dd>${money(item.amount)}</dd></dl></div>
		<div class="ap-grid-2">
			<div class="ap-field"><label class="ap-label">Data do pagamento</label><input class="ap-field-input" name="payment_date" type="date" value="${todayIso()}" required></div>
			<div class="ap-field"><label class="ap-label">Forma de pagamento</label><select class="ap-field-input" name="payment_method">${['Dinheiro','Cartão de Crédito','Cartão de Débito','Pix','Transferência','Boleto'].map(m => `<option value="${m}">${m}</option>`).join('')}</select></div>
		</div>
		<div class="ap-field"><label class="ap-label">Valor recebido</label><input class="ap-field-input" name="amount_received" type="number" step="0.01" value="${item.amount}"></div>
		<div class="ap-modal-footer"><button class="ap-btn ap-btn-primary" type="submit">${ic('money')} Registrar Pagamento</button></div>
	</form>`;

	const receivableDetail = (r) => `<div class="ap-financial-detail">
		<dl><dt>Cliente</dt><dd>${escape(r.client_name)}</dd></dl>
		<dl><dt>Serviço</dt><dd>${escape(r.service_name)}</dd></dl>
		<dl><dt>Profissional</dt><dd>${escape(r.professional_name)}</dd></dl>
		<dl><dt>Data do Agendamento</dt><dd>${fmtDate(r.appointment_date)}</dd></dl>
		<dl><dt>Valor</dt><dd>${money(r.amount)}</dd></dl>
		<dl><dt>Forma de Pagamento</dt><dd>${r.payment_method || '-'}</dd></dl>
		<dl><dt>Status</dt><dd><span class="ap-badge ap-badge-${r.status}">${finStatusMap[r.status]}</span></dd></dl>
		<dl><dt>Vencimento</dt><dd>${fmtDate(r.due_date)}</dd></dl>
		<dl><dt>Data Pagamento</dt><dd>${r.payment_date ? fmtDate(r.payment_date) : '-'}</dd></dl>
		<dl><dt>Valor Recebido</dt><dd>${r.amount_received ? money(r.amount_received) : '-'}</dd></dl>
		<dl><dt>Observações</dt><dd>${r.notes || '-'}</dd></dl>
		${r.status !== 'paid' ? `<div style="display:flex;gap:var(--ap-space-2);flex-wrap:wrap;margin-top:var(--ap-space-4);padding-top:var(--ap-space-4);border-top:1px solid var(--ap-border-light)">
			<button class="ap-btn ap-btn-success ap-btn-sm" data-pay-receivable="${r.id}">${ic('money')} Registrar Recebimento</button>
			<div style="display:flex;align-items:center;gap:4px">
				<select class="ap-select" data-rec-status-change="${r.id}" style="width:130px;padding:5px 8px;font-size:var(--ap-font-size-sm)">
					<option value="">Alterar status...</option>
					<option value="cancelled">Cancelado</option>
					<option value="overdue">Vencido</option>
				</select>
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-rec-status-apply="${r.id}">Aplicar</button>
			</div>
		</div>` : ''}
	</div>`;

	/* ── ROUTER ─────────────────────────────────────── */
	const render = () => {
		switch (page) {
		case 'agendapro-appointments': renderAppointments(); break;
		case 'agendapro-services': renderServices(); break;
		case 'agendapro-professionals': renderProfessionals(); break;
		case 'agendapro-clients': renderClients(); break;
		case 'agendapro-financial': renderFinancial(); break;
		case 'agendapro-settings': renderSettings(); break;
		default: renderDashboard();
		}
	};

	const refresh = async (quiet = false) => {
		if (!quiet && !document.querySelector('.ap-modal-overlay')) layout('<div class="ap-spinner"></div>');
		try {
			await loadAll();
			if (!document.querySelector('.ap-modal-overlay')) render();
		} catch (e) { toast(e.message, 'error'); }
	};

	/* Services & Professionals pages */
	const renderServices = () => {
		const cards = state.services.length ? state.services.map(s => `<article class="ap-management-card">
			<div class="ap-management-card-head">
				<div style="display:flex;align-items:center;gap:8px">
					<span class="ap-svc-dot" style="background:${escape(s.color || '#7C3AED')};width:14px;height:14px"></span>
					<h2 style="margin:0">${escape(s.name)}</h2>
				</div>
				<span class="ap-badge ap-badge-${Number(s.active) ? 'active' : 'inactive'}">${Number(s.active) ? 'Ativo' : 'Inativo'}</span>
			</div>
			<p>${escape(s.description || 'Nenhuma descrição.')}</p>
			<div class="ap-management-meta">
				<span>${ic('clock')} ${s.duration} min</span>
				<span>${ic('money')} ${money(s.price)}</span>
			</div>
			<div class="ap-management-actions">
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-edit-service="${s.id}">${ic('edit')} Editar</button>
				<button class="ap-btn ap-btn-sm ap-btn-danger" data-delete-service="${s.id}">${ic('trash')} Excluir</button>
			</div>
		</article>`).join('') : `<div class="ap-management-grid" style="display:block"><div class="ap-empty"><div class="ap-empty-icon">${ic('list')}</div><div class="ap-empty-title">Nenhum serviço</div><div class="ap-empty-text">Cadastre seu primeiro serviço para começar.</div><button class="ap-btn ap-btn-primary" data-new-service>${ic('add')} Novo Serviço</button></div></div>`;
		layout(`<div class="ap-management-grid">${cards}</div>`, `<button class="ap-btn ap-btn-primary ap-btn-icon" data-new-service title="Novo Serviço">${ic('add')}</button>`);
	};

	const renderProfessionals = () => {
		const cards = state.professionals.length ? state.professionals.map(p => `<article class="ap-management-card ap-professional-card">
			<div class="ap-management-card-head">
				<div class="ap-avatar" style="width:48px;height:48px">${photo(p.photo_url, p.name)}</div>
				<span class="ap-badge ap-badge-${Number(p.active) ? 'active' : 'inactive'}">${Number(p.active) ? 'Ativo' : 'Inativo'}</span>
			</div>
			<h2>${escape(p.name)}</h2>
			<p>${escape(p.specialties || 'Profissional')}</p>
			<div class="ap-management-meta">
				${p.whatsapp ? `<span>${ic('phone')} ${escape(maskPhone(p.whatsapp))}</span>` : ''}
				${p.email ? `<span>${escape(p.email)}</span>` : ''}
			</div>
			<div class="ap-card-tags">${(p.services || []).length ? (p.services || []).map(s => `<span class="ap-tag">${escape(s.name)}</span>`).join('') : '<span class="ap-tag" style="background:var(--ap-bg-alt);color:var(--ap-text-tertiary)">Nenhum serviço</span>'}</div>
			<div class="ap-management-actions">
				<button class="ap-btn ap-btn-sm ap-btn-outline" data-edit-professional="${p.id}">${ic('edit')} Editar</button>
				<button class="ap-btn ap-btn-sm ap-btn-danger" data-delete-professional="${p.id}">${ic('trash')} Excluir</button>
			</div>
		</article>`).join('') : `<div class="ap-management-grid" style="display:block"><div class="ap-empty"><div class="ap-empty-icon">${ic('user')}</div><div class="ap-empty-title">Nenhum profissional</div><div class="ap-empty-text">Cadastre os profissionais da sua equipe.</div><button class="ap-btn ap-btn-primary" data-new-professional>${ic('add')} Novo Profissional</button></div></div>`;
		layout(`<div class="ap-management-grid">${cards}</div>`, `<button class="ap-btn ap-btn-primary ap-btn-icon" data-new-professional title="Novo Profissional">${ic('add')}</button>`);
	};

	/* ── EVENT HANDLERS ─────────────────────────────── */
	document.addEventListener('input', e => {
		if (e.target.classList.contains('ap-phone-mask')) e.target.value = maskPhone(e.target.value);
		if (e.target.matches('[data-client-search]')) { state.clientSearch = e.target.value; state.clientPage = 1; renderClientList(); }
		if (e.target.matches('[data-inactive-search]')) { state.inactiveSearch = e.target.value; renderInactiveClients(); }
		if (e.target.matches('[data-cp-client-search]')) { renderClientSearchResults('cp', e.target.value); }
		if (e.target.matches('[data-appt-client-search]')) { renderClientSearchResults('appt', e.target.value); }
	});

	document.addEventListener('change', e => {
		if (e.target.matches('[data-appt-service], [data-appt-professional], [data-appt-date]')) {
			loadApptSlots();
		}
		if (e.target.matches('[data-appt-status-change]')) {
			const id = e.target.dataset.apptStatusChange;
			const status = e.target.value;
			request('/appointments/' + id + '/status', { method: 'PUT', body: { status } })
				.then(() => { toast('Status atualizado.'); renderApptGrid(root.querySelector('[data-ap-content]')); })
				.catch(err => toast(err.message, 'error'));
			return;
		}
		if (e.target.matches('[data-inactive-id]')) {
			e.target.checked ? state.selectedInactive.add(e.target.dataset.inactiveId) : state.selectedInactive.delete(e.target.dataset.inactiveId);
		}
		if (e.target.matches('[data-select-all-inactive]')) {
			state.inactiveClients.forEach(c => e.target.checked ? state.selectedInactive.add(String(c.id)) : state.selectedInactive.delete(String(c.id)));
			render();
		}
	});

	document.addEventListener('click', async e => {
		const row = e.target.closest('[data-appt-open]');
		if (row && !e.target.closest('button, select, .ap-overflow-wrap')) {
			openAppointmentModal((state.appointments.items || []).find(x => String(x.id) === row.dataset.apptOpen));
			return;
		}
		const cpRow = e.target.closest('[data-client-package-open]');
		if (cpRow && !e.target.closest('button, select, .ap-overflow-wrap')) {
			openClientPackageModal(cpRow.dataset.clientPackageOpen);
			return;
		}
		const clientPick = e.target.closest('[data-cp-select-client], [data-appt-select-client]');
		if (clientPick) {
			const prefix = clientPick.dataset.cpSelectClient !== undefined ? 'cp' : 'appt';
			const id = prefix === 'cp' ? clientPick.dataset.cpSelectClient : clientPick.dataset.apptSelectClient;
			selectClient(prefix, id);
			return;
		}
		const btn = e.target.closest('button');
		closeAllPopovers();
		if (!btn) return;

		if (btn.dataset.apptSlot) {
			const form = btn.closest('.ap-form');
			const hidden = form ? form.querySelector('[name="start_time"]') : null;
			if (hidden) hidden.value = btn.dataset.apptSlot;
			if (form) form.querySelectorAll('.ap-time-chip').forEach(c => c.classList.toggle('selected', c === btn));
			return;
		}

		if (btn.dataset.overflow) {
			e.stopPropagation();
			const id = btn.dataset.overflow;
			state.overflows[id] = !state.overflows[id];
			const menu = btn.parentElement.querySelector('[data-overflow-menu]');
			if (menu) { if (state.overflows[id]) menu.classList.add('show'); else menu.classList.remove('show'); }
			return;
		}

		if (btn.dataset.close !== undefined) { closeModal(); return; }

		if (btn.dataset.pageKind) {
			state[btn.dataset.pageKind + 'Page'] = Number(btn.dataset.pageNumber);
			if (btn.dataset.pageKind === 'appt') renderApptGrid(root.querySelector('[data-ap-content]'));
			else if (btn.dataset.pageKind === 'clientPkg') renderClientPkgGrid(root.querySelector('[data-ap-content]'));
			else if (btn.dataset.pageKind === 'receivable') renderReceivables();
			else if (btn.dataset.pageKind === 'expense') renderExpenses();
			else if (btn.dataset.pageKind === 'client') renderClientList();
			else render();
			return;
		}

		if (btn.dataset.apTab) { state.tab = btn.dataset.apTab; renderAppointments(); return; }
		if (btn.dataset.finTab) { state.finTab = btn.dataset.finTab; renderFinancial(); return; }
		if (btn.dataset.settingsTab) { state.settingsTab = btn.dataset.settingsTab; renderSettings(); return; }
		if (btn.dataset.clientTab) { state.clientTab = btn.dataset.clientTab; renderClients(); return; }

		if (btn.dataset.loadDashboard !== undefined) {
			const from = root.querySelector('[data-period-from]').value || monthStart();
			const to = root.querySelector('[data-period-to]').value || todayIso();
			state.periodFrom = from; state.periodTo = to;
			await loadDashboard(from, to);
			return;
		}

		if (btn.dataset.dashView !== undefined) {
			state.dashViewMode = btn.dataset.dashView;
			state.dashApptPage = 1;
			if (state.dashViewMode === 'calendar') { state.dashCalDate = todayIso(); }
			loadDashAppts();
			return;
		}

		if (btn.dataset.dashApptPage !== undefined) {
			state.dashApptPage = Number(btn.dataset.dashApptPage);
			loadDashAppts();
			return;
		}

		if (btn.dataset.apptFilter !== undefined) {
			state.apptSearch = root.querySelector('[data-appt-search]')?.value || '';
			state.apptStatus = root.querySelector('[data-appt-status]')?.value || '';
			state.apptDateFrom = root.querySelector('[data-appt-from]')?.value || '';
			state.apptDateTo = root.querySelector('[data-appt-to]')?.value || '';
			state.apptPage = 1;
			renderApptGrid(root.querySelector('[data-ap-content]'));
			return;
		}

		if (btn.dataset.apptSort !== undefined) {
			const newOrderby = btn.dataset.apptSort;
			if (state.apptOrderby === newOrderby) state.apptOrder = state.apptOrder === 'ASC' ? 'DESC' : 'ASC';
			else { state.apptOrderby = newOrderby; state.apptOrder = 'ASC'; }
			renderApptGrid(root.querySelector('[data-ap-content]'));
			return;
		}

		if (btn.dataset.cpFilter !== undefined) {
			state.clientPkgSearch = root.querySelector('[data-cp-search]')?.value || '';
			state.clientPkgStatus = root.querySelector('[data-cp-status]')?.value || '';
			state.clientPkgDateFrom = root.querySelector('[data-cp-from]')?.value || '';
			state.clientPkgDateTo = root.querySelector('[data-cp-to]')?.value || '';
			state.clientPkgPage = 1;
			renderClientPkgGrid(root.querySelector('[data-ap-content]'));
			return;
		}

		if (btn.dataset.recFilter !== undefined) {
			state.receivableSearch = root.querySelector('[data-rec-search]')?.value || '';
			state.receivableStatus = root.querySelector('[data-rec-status]')?.value || '';
			state.receivableDateFrom = root.querySelector('[data-rec-from]')?.value || '';
			state.receivableDateTo = root.querySelector('[data-rec-to]')?.value || '';
			state.receivablePage = 1;
			renderReceivables();
			return;
		}

		if (btn.dataset.expFilter !== undefined) {
			state.expenseSearch = root.querySelector('[data-exp-search]')?.value || '';
			state.expenseStatus = root.querySelector('[data-exp-status]')?.value || '';
			state.expenseDateFrom = root.querySelector('[data-exp-from]')?.value || '';
			state.expenseDateTo = root.querySelector('[data-exp-to]')?.value || '';
			state.expensePage = 1;
			renderExpenses();
			return;
		}

		if (btn.dataset.clientSub) {
			const data = state._clientData || {};
			const sub = btn.dataset.clientSub;
			const content = root.querySelector('[data-client-detail-content]');
			if (!content) return;
			if (sub === 'history') content.innerHTML = renderClientHistoryContent(data.history);
			else if (sub === 'appointments') content.innerHTML = renderClientApptsContent(data.appointments);
			else if (sub === 'financial') content.innerHTML = renderClientFinancialContent(data.financial);
			else if (sub === 'packages') content.innerHTML = renderClientPkgContent(data.packages);
			else if (sub === 'anamnesis') content.innerHTML = renderClientAnamnesisContent(data.anamnesis);
			return;
		}

		if (btn.dataset.viewClient) { state.clientDetailId = btn.dataset.viewClient; state.clientTab = 'detail'; renderClients(); return; }
		if (btn.dataset.newClient !== undefined) { openModal('Novo Cliente', newClientForm()); return; }
		if (btn.dataset.editClient) { const c = state.clients.find(x => String(x.id) === btn.dataset.editClient); if (c) openModal('Editar Cliente', clientForm(c)); return; }
		if (btn.dataset.bookClient) { const c = state.clients.find(x => String(x.id) === btn.dataset.bookClient); if (c) { openModal('Novo Agendamento', appointmentForm({ client_id: c.id, client_name: c.name, whatsapp: c.whatsapp })); loadApptSlots(); } return; }

		if (btn.dataset.pkgAppointments) {
			e.stopPropagation();
			const id = btn.dataset.pkgAppointments;
			const cp = (state.clientPkgs.items || []).find(x => String(x.id) === id) || await request('/client-packages/' + id);
			const data = await request('/client-packages/' + id + '/appointments');
			const html = `<div class="ap-popover show" style="position:static;display:block;max-width:none"><h4>Agendamentos do Pacote</h4>${(data.appointments || []).map(a => `<div class="ap-popover-item">${fmtDate(a.appointment_date)} ${normalizeTime(a.start_time)} - ${escape(a.service_name)} (${escape(a.professional_name)})</div>`).join('') || '<p class="ap-muted">Nenhum agendamento.</p>'}</div>`;
			openModal('Agendamentos do Pacote #' + id, html);
			return;
		}

		if (btn.dataset.pkgProfessionals) {
			const id = btn.dataset.pkgProfessionals;
			const data = await request('/client-packages/' + id + '/appointments');
			const html = `<div style="position:static;display:block"><h4>Profissionais</h4>${(data.professionals || []).map(p => `<div>${escape(p.name)}</div>`).join('') || '<p class="ap-muted">Nenhum profissional.</p>'}</div>`;
			openModal('Profissionais - Pacote #' + id, html);
			return;
		}

		if (btn.dataset.receivableDetail) {
			const id = btn.dataset.receivableDetail;
			const r = (state.receivables.items || []).find(x => String(x.id) === id);
			if (r) openModal('Conta a Receber #' + id, receivableDetail(r));
			return;
		}

		if (btn.dataset.payReceivable) { const r = (state.receivables.items || []).find(x => String(x.id) === btn.dataset.payReceivable) || await request('/financial/receivables/' + btn.dataset.payReceivable); openModal('Registrar Recebimento', payForm('receivable', r)); return; }
		if (btn.dataset.recStatusApply) {
			const id = btn.dataset.recStatusApply;
			const select = document.querySelector('[data-rec-status-change="' + id + '"]');
			const status = select ? select.value : '';
			if (!status) { toast('Selecione um status.', 'warning'); return; }
			await request('/financial/receivables/' + id, { method: 'PUT', body: { status, amount: 0, payment_method: '', notes: '' } });
			toast('Status atualizado.');
			closeModal();
			await refresh(true);
			return;
		}

		if (btn.dataset.newAppointment !== undefined) { openModal('Novo Agendamento', appointmentForm()); loadApptSlots(); return; }
		if (btn.dataset.newClientPackage !== undefined) { openModal('Vincular Pacote ao Cliente', clientPackageForm()); return; }
		if (btn.dataset.cpNewClient !== undefined) {
			openModal('Novo Cliente', newClientForm());
			return;
		}
		if (btn.dataset.apptNewClient !== undefined) {
			openModal('Novo Cliente', newClientForm());
			return;
		}
		if (btn.dataset.editAppointment) { openAppointmentModal((state.appointments.items || []).find(x => String(x.id) === btn.dataset.editAppointment)); return; }
		if (btn.dataset.deleteAppointment && confirm('Excluir este agendamento?')) { await request('/appointments/' + btn.dataset.deleteAppointment, { method: 'DELETE' }); toast('Agendamento excluído.'); await refresh(true); return; }

		if (btn.dataset.bookPackage) { await openAppointmentFromPackage(btn.dataset.bookPackage); return; }
		if (btn.dataset.deleteClientPackage && confirm('Excluir este pacote?')) { await request('/client-packages/' + btn.dataset.deleteClientPackage, { method: 'DELETE' }); toast('Pacote excluído.'); closeModal(); await refresh(true); return; }

		if (btn.dataset.newPackage !== undefined) { openModal('Novo Pacote', packageForm()); return; }
		if (btn.dataset.editPackage) { const pkg = state.packages.find(x => String(x.id) === btn.dataset.editPackage); if (pkg) openModal('Editar Pacote', packageForm(pkg)); return; }
		if (btn.dataset.deletePackage && confirm('Excluir este pacote?')) { await request('/packages/' + btn.dataset.deletePackage, { method: 'DELETE' }); toast('Pacote excluído.'); await refresh(true); return; }

		if (btn.dataset.newExpense !== undefined) { openModal('Nova Despesa', expenseForm()); return; }
		if (btn.dataset.editExpense) { const e = (state.expenses.items || []).find(x => String(x.id) === btn.dataset.editExpense); if (e) openModal('Editar Despesa', expenseForm(e)); return; }
		if (btn.dataset.payExpense) { const e = (state.expenses.items || []).find(x => String(x.id) === btn.dataset.payExpense) || await request('/financial/expenses/' + btn.dataset.payExpense); openModal('Registrar Pagamento', payForm('expense', e)); return; }
		if (btn.dataset.deleteExpense && confirm('Excluir esta despesa?')) { await request('/financial/expenses/' + btn.dataset.deleteExpense, { method: 'DELETE' }); toast('Despesa excluída.'); refresh(true); return; }

		if (btn.dataset.newService !== undefined) { openModal('Novo Serviço', serviceForm()); return; }
		if (btn.dataset.editService) { const s = state.services.find(x => Number(x.id) === Number(btn.dataset.editService)); if (s) openModal('Editar Serviço', serviceForm(s)); return; }
		if (btn.dataset.deleteService && confirm('Excluir este serviço?')) { await request('/services/' + btn.dataset.deleteService, { method: 'DELETE' }); toast('Serviço excluído.'); await refresh(true); return; }

		if (btn.dataset.newProfessional !== undefined) { openModal('Novo Profissional', professionalForm()); return; }
		if (btn.dataset.editProfessional) { const p = state.professionals.find(x => Number(x.id) === Number(btn.dataset.editProfessional)); if (p) openModal('Editar Profissional', professionalForm(p)); return; }
		if (btn.dataset.deleteProfessional && confirm('Excluir este profissional?')) { await request('/professionals/' + btn.dataset.deleteProfessional, { method: 'DELETE' }); toast('Profissional excluído.'); await refresh(true); return; }
		if (btn.dataset.addHour !== undefined) { const table = document.querySelector('[data-hours]'); if (table) table.insertAdjacentHTML('beforeend', hourRow({})); return; }
		if (btn.dataset.removeHour !== undefined) { const row = btn.closest('[data-hour-row]'); if (row) row.remove(); return; }

		if (btn.dataset.notifyClient) { await request('/notifications/rescue', { method: 'POST', body: { client_ids: [btn.dataset.notifyClient] } }); toast('Notificação disparada.'); return; }
		if (btn.dataset.notifySelected !== undefined) { await request('/notifications/rescue', { method: 'POST', body: { client_ids: Array.from(state.selectedInactive) } }); toast('Notificações disparadas.'); state.selectedInactive.clear(); render(); return; }

		if (btn.dataset.newSection !== undefined) { openModal('Nova Seção', sectionForm()); return; }
		if (btn.dataset.editSection) { const s = state.anamnesisSections.find(x => String(x.id) === btn.dataset.editSection); if (s) openModal('Editar Seção', sectionForm(s)); return; }
		if (btn.dataset.deleteSection && confirm('Excluir esta seção e seus campos?')) { await request('/anamnesis/sections/' + btn.dataset.deleteSection, { method: 'DELETE' }); toast('Seção excluída.'); renderAnamnesisSettings(); return; }

		if (btn.dataset.addField !== undefined) { openModal('Novo Campo', fieldForm({ section_id: btn.dataset.addField })); return; }
		if (btn.dataset.editField) { await request('/anamnesis/fields?section_id=0').then(fields => { const f = (fields || []).find(x => String(x.id) === btn.dataset.editField); if (f) openModal('Editar Campo', fieldForm(f)); }); return; }
		if (btn.dataset.deleteField && confirm('Excluir este campo?')) { await request('/anamnesis/fields/' + btn.dataset.deleteField, { method: 'DELETE' }); toast('Campo excluído.'); renderAnamnesisSettings(); return; }

		if (btn.dataset.newKb !== undefined) { openModal('Novo Artigo', kbForm()); return; }
		if (btn.dataset.editKb) { const kb = state.kbItems.find(x => String(x.id) === btn.dataset.editKb); if (kb) openModal('Editar Artigo', kbForm(kb)); return; }
		if (btn.dataset.deleteKb && confirm('Excluir este artigo?')) { await request('/knowledge-base/' + btn.dataset.deleteKb, { method: 'DELETE' }); toast('Artigo excluído.'); renderKBSettings(); return; }
	});

	document.addEventListener('submit', async e => {
		const form = e.target.closest('.ap-form');
		if (!form) return;
		e.preventDefault();
		const data = new FormData(form);
		const type = form.dataset.form;
		const id = form.dataset.id;

		try {
			if (type === 'service') {
				const p = Object.fromEntries(data.entries()); p.active = data.has('active') ? 1 : 0;
				await request(id ? '/services/' + id : '/services', { method: id ? 'PUT' : 'POST', body: p });
				toast('Serviço salvo.');
			} else if (type === 'professional') {
				const p = Object.fromEntries(data.entries()); p.active = data.has('active') ? 1 : 0;
				p.service_ids = data.getAll('service_ids');
				p.hours = Array.from(form.querySelectorAll('[data-hour-row]')).map(r => ({
					day_of_week: r.querySelector('[name="hour_day"]').value,
					start_time: r.querySelector('[name="hour_start"]').value,
					end_time: r.querySelector('[name="hour_end"]').value,
					interval_minutes: r.querySelector('[name="hour_interval"]').value,
					active: 1
				}));
				await request(id ? '/professionals/' + id : '/professionals', { method: id ? 'PUT' : 'POST', body: p });
				toast('Profissional salvo.');
			} else if (type === 'appointment') {
				const p = Object.fromEntries(data.entries()); p.appointment_date = toIso(p.appointment_date);
				if (!id && (!p.client_name || !p.whatsapp)) { toast('Selecione ou cadastre um cliente.', 'warning'); return; }
				if (!p.start_time) { toast('Selecione um horário disponível.', 'warning'); return; }
				await request(id ? '/appointments/' + id : '/appointments', { method: id ? 'PUT' : 'POST', body: p });
				toast(id ? 'Agendamento atualizado.' : 'Agendamento criado.');
			} else if (type === 'package') {
				const p = Object.fromEntries(data.entries()); p.active = data.has('active') ? 1 : 0;
				p.service_ids = data.getAll('service_ids'); p.professional_ids = data.getAll('professional_ids');
				await request(id ? '/packages/' + id : '/packages', { method: id ? 'PUT' : 'POST', body: p });
				toast('Pacote salvo.');
			} else if (type === 'expense') {
				const p = Object.fromEntries(data.entries());
				await request(id ? '/financial/expenses/' + id : '/financial/expenses', { method: id ? 'PUT' : 'POST', body: p });
				toast('Despesa salva.');
			} else if (type === 'client') {
				const p = Object.fromEntries(data.entries()); p.birth_date = toIso(p.birth_date);
				if (id) {
					await request('/clients/' + id, { method: 'PUT', body: p });
					toast('Cliente atualizado.');
				} else {
					const resp = await request('/clients', { method: 'POST', body: p });
					toast('Cliente criado.');
					if (resp && resp.id) {
						state.clients.push({ id: resp.id, name: resp.name || '', whatsapp: resp.whatsapp || '', email: resp.email || '' });
						const prefix = document.querySelector('[data-appt-client-search]') ? 'appt' : (document.querySelector('[data-cp-client-search]') ? 'cp' : '');
						if (prefix) selectClient(prefix, resp.id);
					}
				}
			} else if (type === 'settings') {
				await request('/settings', { method: 'PUT', body: Object.fromEntries(data.entries()) });
				toast('Configurações salvas.');
			} else if (type === 'section') {
				const p = Object.fromEntries(data.entries()); p.active = data.has('active') ? 1 : 0;
				await request(id ? '/anamnesis/sections/' + id : '/anamnesis/sections', { method: id ? 'PUT' : 'POST', body: p });
				toast('Seção salva.');
			} else if (type === 'field') {
				const p = Object.fromEntries(data.entries()); p.required = data.has('required') ? 1 : 0; p.active = data.has('active') ? 1 : 0;
				await request(id ? '/anamnesis/fields/' + id : '/anamnesis/fields', { method: id ? 'PUT' : 'POST', body: p });
				toast('Campo salvo.');
			} else if (type === 'kb') {
				const p = Object.fromEntries(data.entries()); p.active = data.has('active') ? 1 : 0;
				await request(id ? '/knowledge-base/' + id : '/knowledge-base', { method: id ? 'PUT' : 'POST', body: p });
				toast('Artigo salvo.');
			} else if (type === 'clientPackage') {
				const p = Object.fromEntries(data.entries());
				await request('/client-packages', { method: 'POST', body: p });
				toast('Pacote vinculado ao cliente.');
			} else if (type === 'pay-receivable') {
				await request('/financial/receivables/' + id + '/pay', { method: 'POST', body: Object.fromEntries(data.entries()) });
				toast('Recebimento registrado.');
			} else if (type === 'pay-expense') {
				await request('/financial/expenses/' + id + '/pay', { method: 'POST', body: Object.fromEntries(data.entries()) });
				toast('Pagamento registrado.');
			} else if (type === 'anamnesis') {
				const fields = {};
				Array.from(form.elements).forEach(el => {
					const m = el.name.match(/^anamnesis_(\d+)$/);
					if (m) fields[m[1]] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
				});
				await request('/clients/' + form.dataset.client + '/anamnesis', { method: 'POST', body: { fields } });
				toast('Ficha de anamnese salva.');
			}
			closeModal();
			await refresh(true);
		} catch (err) { toast(err.message, 'error'); }
	});

	document.addEventListener('click', e => {
		if (!e.target.closest('.ap-overflow-wrap') && !e.target.closest('[data-pkg-appointments]') && !e.target.closest('[data-pkg-professionals]')) {
			closeAllPopovers();
		}
	});

	/* ── INIT ────────────────────────────────────────── */
	refresh();
})();
