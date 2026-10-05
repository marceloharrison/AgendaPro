(function ($) {
	'use strict';

	const api = (root, path, options = {}) => {
		const base = root.data('api') || (window.agendaproPublic && agendaproPublic.apiUrl);
		const nonce = root.data('nonce') || (window.agendaproPublic && agendaproPublic.nonce);

		return fetch(base.replace(/\/$/, '') + path, {
			method: options.method || 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce
			},
			body: options.body ? JSON.stringify(options.body) : undefined
		}).then(async (response) => {
			const data = await response.json().catch(() => ({}));

			if (!response.ok) {
				throw new Error(data.message || 'Nao foi possivel concluir a operacao.');
			}

			return data;
		});
	};

	const money = (value) => Number(value || 0).toLocaleString('pt-BR', {
		style: 'currency',
		currency: 'BRL'
	});

	const formatDate = (value) => {
		if (!value) {
			return '';
		}

		const [year, month, day] = value.split('-');
		return `${day}-${month}-${year}`;
	};

	const toIsoDate = (value) => {
		if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
			return value;
		}

		const clean = String(value || '').replace(/\D/g, '');
		if (clean.length === 8) {
			return `${clean.slice(4, 8)}-${clean.slice(2, 4)}-${clean.slice(0, 2)}`;
		}
		return value;
	};

	const maskPhone = (value) => {
		const numbers = String(value || '').replace(/\D/g, '').slice(0, 11);
		if (numbers.length <= 2) {
			return numbers ? `(${numbers}` : '';
		}
		if (numbers.length <= 7) {
			return `(${numbers.slice(0, 2)}) ${numbers.slice(2)}`;
		}
		return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7)}`;
	};

	const maskDate = (value) => {
		const numbers = String(value || '').replace(/\D/g, '').slice(0, 8);
		if (numbers.length <= 2) {
			return numbers;
		}
		if (numbers.length <= 4) {
			return `${numbers.slice(0, 2)}-${numbers.slice(2)}`;
		}
		return `${numbers.slice(0, 2)}-${numbers.slice(2, 4)}-${numbers.slice(4)}`;
	};

	const statusLabel = (status) => ({
		pending: 'Pendente',
		confirmed: 'Confirmado',
		cancelled: 'Cancelado',
		completed: 'Realizado',
		no_show: 'Não compareceu'
	}[status] || status);

	const groupSlots = (slots) => {
		const available = (slots || []).filter((slot) => slot.available !== false);
		const morning = available.filter((slot) => Number(String(slot.start).slice(0, 2)) < 12);
		const afternoon = available.filter((slot) => Number(String(slot.start).slice(0, 2)) >= 12);
		const group = (label, rows) => {
			if (!rows.length) {
				return '';
			}

			return '<div class="ap-slots-group"><div class="ap-slots-label">' + label + '</div><div class="ap-slots"></div></div>';
		};

		return { morning, afternoon, html: group('MANHÃ', morning) + group('TARDE', afternoon) };
	};

	const showAlert = (root, message) => {
		root.find('#ap-alert, #ap-lookup-alert').first().text(message).show();
	};

	const clearAlert = (root) => {
		root.find('#ap-alert, #ap-lookup-alert').hide().text('');
	};

	const initBooking = () => {
		const root = $('#agendapro-booking-form');

		if (!root.length) {
			return;
		}

		const state = {
			services: [],
			professionals: [],
			service: null,
			professional: null,
			date: '',
			slot: null,
			calendarMonth: null,
			availabilityDates: {}
		};

		const pad = (value) => String(value).padStart(2, '0');
		const today = new Date();
		const minDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
		const maxDate = new Date(today.getFullYear(), today.getMonth() + 6, today.getDate());

		state.calendarMonth = new Date(minDate.getFullYear(), minDate.getMonth(), 1);

		const isoFromDate = (date) => date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
		const parseIsoDate = (value) => {
			const [year, month, day] = String(value || '').split('-').map(Number);
			return new Date(year, month - 1, day);
		};
		const monthTitle = (date) => date
			.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
			.replace(/(^|\s)(\S)/g, (match) => match.toUpperCase());
		const monthKey = (date) => date.getFullYear() + '-' + pad(date.getMonth() + 1);
		const sameMonth = (left, right) => left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth();
		const extractSlots = (response, date = state.date, professionalId = state.professional && state.professional.id) => {
			if (Array.isArray(response)) {
				return response;
			}

			const dateRow = (response.dates || []).find((row) => row.date === date);
			const professional = dateRow && (dateRow.professionals || []).find((item) => Number(item.id) === Number(professionalId));
			return professional ? professional.slots || [] : [];
		};

		const hydrateCalendarAvailability = () => {
			if (!state.professional || !state.service) {
				return;
			}

			const currentKey = monthKey(state.calendarMonth);
			const dates = root.find('.ap-calendar-day[data-date]').map(function () {
				const date = $(this).data('date');
				return date && date.slice(0, 7) === currentKey && !$(this).prop('disabled') ? date : null;
			}).get();

			dates.forEach((date) => {
				const cacheKey = state.professional.id + ':' + state.service.id + ':' + date;
				if (Object.prototype.hasOwnProperty.call(state.availabilityDates, cacheKey)) {
					root.find('.ap-calendar-day[data-date="' + date + '"]').toggleClass('has-availability', state.availabilityDates[cacheKey]);
					return;
				}

				api(root, '/availability?professional_id=' + state.professional.id + '&service_id=' + state.service.id + '&date=' + encodeURIComponent(date))
					.then((response) => {
						const slots = extractSlots(response, date, state.professional.id);
						state.availabilityDates[cacheKey] = (slots || []).some((slot) => slot.available !== false);
						root.find('.ap-calendar-day[data-date="' + date + '"]').toggleClass('has-availability', state.availabilityDates[cacheKey]);
					})
					.catch(() => {
						state.availabilityDates[cacheKey] = false;
					});
			});
		};

		const renderCalendar = () => {
			const calendar = root.find('#ap-calendar');
			if (!calendar.length) {
				return;
			}

			const first = new Date(state.calendarMonth.getFullYear(), state.calendarMonth.getMonth(), 1);
			const start = new Date(first);
			start.setDate(first.getDate() - first.getDay());
			const prevMonth = new Date(first.getFullYear(), first.getMonth() - 1, 1);
			const nextMonth = new Date(first.getFullYear(), first.getMonth() + 1, 1);
			const canPrev = new Date(first.getFullYear(), first.getMonth(), 0) >= minDate;
			const canNext = nextMonth <= maxDate;
			const weekdays = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'];
			const cells = [];

			for (let index = 0; index < 42; index += 1) {
				const date = new Date(start);
				date.setDate(start.getDate() + index);
				const iso = isoFromDate(date);
				const disabled = date < minDate || date > maxDate;
				const muted = sameMonth(date, first) ? '' : ' muted';
				const selected = iso === state.date ? ' selected' : '';
				const availableKey = state.professional && state.service ? state.professional.id + ':' + state.service.id + ':' + iso : '';
				const hasAvailability = state.availabilityDates[availableKey] ? ' has-availability' : '';
				cells.push(
					'<button type="button" class="ap-calendar-day' + muted + selected + hasAvailability + '" data-date="' + iso + '" ' + (disabled ? 'disabled' : '') + '>' +
						'<span>' + date.getDate() + '</span>' +
					'</button>'
				);
			}

			calendar.html(
				'<div class="ap-calendar-head">' +
					'<button type="button" class="ap-calendar-nav" data-calendar-month="' + monthKey(prevMonth) + '" ' + (canPrev ? '' : 'disabled') + ' aria-label="Mes anterior">&lsaquo;</button>' +
					'<div class="ap-calendar-title">' + monthTitle(first) + '</div>' +
					'<button type="button" class="ap-calendar-nav" data-calendar-month="' + monthKey(nextMonth) + '" ' + (canNext ? '' : 'disabled') + ' aria-label="Proximo mes">&rsaquo;</button>' +
				'</div>' +
				'<div class="ap-calendar-weekdays">' + weekdays.map((day) => '<span>' + day + '</span>').join('') + '</div>' +
				'<div class="ap-calendar-grid">' + cells.join('') + '</div>'
			);

			hydrateCalendarAvailability();
		};

		const step = (number) => {
			root.find('.ap-step-content').hide();
			root.find('#ap-step-' + number).show();
			root.find('.ap-step').each(function () {
				const current = Number($(this).data('step'));
				$(this).toggleClass('active', current === number);
				$(this).toggleClass('done', current < number);
			});
			clearAlert(root);
		};

		const renderServices = () => {
			const list = root.find('#ap-services-list').removeClass('ap-pub-spinner').empty();
			const active = state.services.filter((service) => Number(service.active) === 1);

			if (!active.length) {
				list.html('<div class="ap-slots-empty">Nenhum serviço cadastrado no momento.</div>');
				return;
			}

			active.forEach((service) => {
				const card = $('<button type="button" class="ap-pub-card"></button>');
				card.html(
					'<span class="ap-pub-card__dot" style="background:' + (service.color || '#4F46E5') + '"></span>' +
					'<span class="ap-pub-card__body"><span class="ap-pub-card__name"></span><span class="ap-pub-card__sub"></span><span class="ap-pub-card__meta"><span class="ap-pub-card__time"></span><span class="ap-pub-card__price"></span></span></span>'
				);
				card.find('.ap-pub-card__name').text(service.name);
				card.find('.ap-pub-card__sub').text(service.description || 'Sessão');
				card.find('.ap-pub-card__time').text((service.duration || 60) + ' min');
				card.find('.ap-pub-card__price').text(money(service.price));
				card.on('click', () => {
					state.service = service;
					state.professional = null;
					state.date = '';
					state.slot = null;
					root.find('#ap-date').val('');
					root.find('#ap-slots-wrap').html('<p class="ap-slots-empty">Selecione uma data para ver os hor&aacute;rios dispon&iacute;veis.</p>');
					root.find('#ap-services-list .ap-pub-card').removeClass('selected');
					card.addClass('selected');
					root.find('#ap-next-1').prop('disabled', false).show();
				});
				list.append(card);
			});
		};

		const renderProfessionals = () => {
			const list = root.find('#ap-professionals-list').empty();
			const matches = state.professionals.filter((professional) => {
				if (Number(professional.active) !== 1) {
					return false;
				}

				return (professional.services || []).some((service) => Number(service.id) === Number(state.service.id));
			});

			if (!matches.length) {
				list.html('<div class="ap-slots-empty">Nenhum profissional atende este serviço.</div>');
				return;
			}

			matches.forEach((professional) => {
				const card = $('<button type="button" class="ap-pub-card"></button>');
				const photo = professional.photo_url
					? '<img src="' + professional.photo_url + '" alt="">'
					: '<span>' + (professional.name || '?').charAt(0).toUpperCase() + '</span>';
				card.html(
					'<span class="ap-pub-card__icon">' + photo + '</span>' +
					'<span class="ap-pub-card__body"><span class="ap-pub-card__name"></span><span class="ap-pub-card__sub"></span></span>'
				);
				card.find('.ap-pub-card__name').text(professional.name);
				card.find('.ap-pub-card__sub').text(professional.specialties || 'Profissional');
				card.on('click', () => {
					state.professional = professional;
					state.date = '';
					state.slot = null;
					root.find('#ap-date').val('');
					root.find('#ap-slots-wrap').html('<p class="ap-slots-empty">Selecione uma data para ver os hor&aacute;rios dispon&iacute;veis.</p>');
					root.find('#ap-professionals-list .ap-pub-card').removeClass('selected');
					card.addClass('selected');
					root.find('#ap-next-2').prop('disabled', false);
				});
				list.append(card);
			});
		};

		const loadSlots = () => {
			state.date = toIsoDate(root.find('#ap-date').val());
			state.slot = null;
			root.find('#ap-next-3').prop('disabled', true);

			if (!state.date || !state.professional || !state.service) {
				return;
			}

			const wrap = root.find('#ap-slots-wrap').html('<div class="ap-pub-spinner"></div>');
			api(root, '/availability?professional_id=' + state.professional.id + '&service_id=' + state.service.id + '&date=' + encodeURIComponent(state.date))
				.then((response) => {
					const slots = extractSlots(response);
					const grouped = groupSlots(slots);
					const rows = grouped.morning.concat(grouped.afternoon);

					if (!rows.length) {
						wrap.html('<div class="ap-slots-empty">Não há horários disponíveis para esta data.</div>');
						return;
					}

					wrap.html(grouped.html);
					rows.forEach((slot) => {
						const item = $('<button type="button" class="ap-slot"></button>').text(slot.start);
						item.on('click', () => {
							state.slot = slot;
							root.find('.ap-slot').removeClass('selected');
							item.addClass('selected');
							root.find('#ap-next-3').prop('disabled', false);
						});
						const target = Number(String(slot.start).slice(0, 2)) < 12
							? wrap.find('.ap-slots-group').first().find('.ap-slots')
							: wrap.find('.ap-slots-group').last().find('.ap-slots');
						target.append(item);
					});
				})
				.catch((error) => showAlert(root, error.message));
		};

		const renderSummary = () => {
			root.find('#ap-booking-summary').html(
				'<h4>Resumo</h4>' +
				'<div class="ap-pub-summary-row"><span>Serviço</span><span>' + state.service.name + '</span></div>' +
				'<div class="ap-pub-summary-row"><span>Profissional</span><span>' + state.professional.name + '</span></div>' +
				'<div class="ap-pub-summary-row"><span>Data</span><span>' + formatDate(state.date) + '</span></div>' +
				'<div class="ap-pub-summary-row"><span>Horario</span><span>' + state.slot.start + '</span></div>'
			);
		};

		root.on('click', '#ap-next-1', () => {
			renderProfessionals();
			step(2);
		});
		root.on('click', '#ap-prev-2', () => step(1));
		root.on('click', '#ap-next-2', () => {
			renderCalendar();
			step(3);
		});
		root.on('click', '#ap-prev-3', () => step(2));
		root.on('click', '#ap-next-3', () => {
			renderSummary();
			step(4);
		});
		root.on('click', '#ap-prev-4', () => step(3));
		root.on('change', '#ap-date', loadSlots);
		root.on('click', '[data-calendar-month]', function () {
			const [year, month] = String($(this).data('calendar-month')).split('-').map(Number);
			state.calendarMonth = new Date(year, month - 1, 1);
			renderCalendar();
		});
		root.on('click', '.ap-calendar-day', function () {
			const date = $(this).data('date');
			const parsed = parseIsoDate(date);

			state.date = date;
			root.find('#ap-date').val(date).trigger('change');

			if (!sameMonth(parsed, state.calendarMonth)) {
				state.calendarMonth = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
			}
			renderCalendar();
		});
		root.on('click', '#ap-new-booking', () => window.location.reload());
		root.on('click', '#ap-submit', () => {
			clearAlert(root);
			const payload = {
				service_id: state.service && state.service.id,
				professional_id: state.professional && state.professional.id,
				appointment_date: state.date,
				start_time: state.slot && state.slot.start,
				client_name: root.find('#ap-client-name').val(),
				whatsapp: root.find('#ap-client-whatsapp').val(),
				client_email: root.find('#ap-client-email').val(),
				notes: root.find('#ap-notes').val()
			};

			if (!payload.client_name || !payload.whatsapp) {
				showAlert(root, 'Informe seu nome e WhatsApp.');
				return;
			}

			root.find('#ap-submit').prop('disabled', true).text('Confirmando...');
			api(root, '/appointments', { method: 'POST', body: payload })
				.then((appointment) => {
					root.find('.ap-step-content, #ap-steps, #ap-alert').hide();
					root.find('#ap-success-details').html(
						'<div class="ap-pub-success-card">' +
						'<strong>' + appointment.service_name + '</strong><br>' +
						appointment.professional_name + '<br>' +
						formatDate(appointment.appointment_date) + ' às ' + appointment.start_time.slice(0, 5) +
						'</div>'
					);
					root.find('#ap-success').show();
				})
				.catch((error) => showAlert(root, error.message))
				.finally(() => root.find('#ap-submit').prop('disabled', false).text('Confirmar agendamento'));
		});

		Promise.all([api(root, '/services'), api(root, '/professionals')])
			.then(([services, professionals]) => {
				state.services = services;
				state.professionals = professionals;
				renderServices();
			})
			.catch((error) => showAlert(root, error.message));
	};

	const initLookup = () => {
		const root = $('#agendapro-my-appointments');

		if (!root.length) {
			return;
		}

		root.on('click', '#ap-lookup-submit', () => {
			clearAlert(root);
			const whatsapp = root.find('#ap-lookup-whatsapp').val();

			if (!whatsapp) {
				showAlert(root, 'Informe o WhatsApp usado no agendamento.');
				return;
			}

			root.find('#ap-appointments-result').html('<div class="ap-pub-spinner"></div>');
			api(root, '/clients/lookup?whatsapp=' + encodeURIComponent(whatsapp))
				.then((data) => {
					const result = root.find('#ap-appointments-result').empty();

					if (!data.appointments || !data.appointments.length) {
						result.html('<div class="ap-pub-no-results">Nenhum agendamento encontrado.</div>');
						return;
					}

					if (data.client) {
						result.append('<div class="ap-pub-client-info"><div class="ap-pub-client-avatar">' + data.client.name.charAt(0).toUpperCase() + '</div><div><div class="ap-pub-client-name">' + data.client.name + '</div><div class="ap-pub-client-phone">' + data.client.whatsapp + '</div></div></div>');
					}

					data.appointments.forEach((appointment) => {
						result.append(
							'<div class="ap-pub-appt">' +
							'<div class="ap-pub-appt__header"><div><div class="ap-pub-appt__title">' + appointment.service_name + '</div><div class="ap-pub-appt__sub">' + appointment.professional_name + '</div></div>' +
							'<span class="ap-pub-badge ap-pub-badge-' + appointment.status + '">' + statusLabel(appointment.status) + '</span></div>' +
							'<div class="ap-pub-appt__meta"><span>' + formatDate(appointment.appointment_date) + '</span><span>' + appointment.start_time.slice(0, 5) + '</span></div>' +
							'</div>'
						);
					});
				})
				.catch((error) => showAlert(root, error.message));
		});
	};

	$(function () {
		$(document).on('input', '.ap-phone-mask', function () {
			this.value = maskPhone(this.value);
		});
		$(document).on('input', '.ap-date-mask', function () {
			this.value = maskDate(this.value);
		});
		initBooking();
		initLookup();
	});
})(jQuery);
