(function () {
	var sel = document.getElementById('trdsp_book_service');
	var hint = document.getElementById('trdsp_book_minutes_hint');
	var when = document.getElementById('trdsp_book_when');
	var windowHint = document.getElementById('trdsp_book_window_hint');
	var cfg = window.trdspBooking || {};

	function updateMinutes() {
		if (!sel || !hint) {
			return;
		}
		var opt = sel.options[sel.selectedIndex];
		var minutes = opt ? parseInt(opt.getAttribute('data-minutes') || '0', 10) : 0;
		var tpl = cfg.minutesLabel || 'Typical visit: about %d minutes';
		hint.textContent = minutes > 0 ? tpl.replace('%d', String(minutes)) : '';
	}

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function updateWindow() {
		if (!when || !windowHint) {
			return;
		}
		var raw = when.value;
		if (!raw) {
			windowHint.hidden = true;
			windowHint.textContent = '';
			return;
		}
		var dt = new Date(raw);
		if (isNaN(dt.getTime())) {
			windowHint.hidden = true;
			return;
		}
		var day = dt.getDay();
		var hm = pad(dt.getHours()) + ':' + pad(dt.getMinutes());
		var date = dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate());
		var days = Array.isArray(cfg.days) ? cfg.days.map(function (d) { return parseInt(d, 10); }) : [];
		var outside = false;
		if (days.length && days.indexOf(day) === -1) {
			outside = true;
		}
		if (cfg.open && hm < cfg.open) {
			outside = true;
		}
		if (cfg.close && hm > cfg.close) {
			outside = true;
		}
		var busy = false;
		(cfg.occupied || []).forEach(function (slot) {
			if (slot.date === date && slot.time === hm) {
				busy = true;
			}
		});
		if (outside) {
			windowHint.textContent = cfg.outsideHours || '';
			windowHint.hidden = !windowHint.textContent;
		} else if (busy) {
			windowHint.textContent = cfg.busyDay || '';
			windowHint.hidden = !windowHint.textContent;
		} else {
			windowHint.hidden = true;
			windowHint.textContent = '';
		}
	}

	if (sel) {
		sel.addEventListener('change', updateMinutes);
		updateMinutes();
	}
	if (when) {
		when.addEventListener('change', updateWindow);
		when.addEventListener('input', updateWindow);
		updateWindow();
	}
	document.addEventListener('click', function (event) {
		var btn = event.target.closest ? event.target.closest('.trdsp-slot') : null;
		if (!btn || !btn.getAttribute('data-value')) {
			return;
		}
		var wrap = btn.closest ? btn.closest('.trdsp-slots') : null;
		var targetId = wrap ? wrap.getAttribute('data-for') : '';
		var input = (targetId && document.getElementById(targetId)) || when;
		if (!input) {
			return;
		}
		input.value = btn.getAttribute('data-value');
		if (input === when) {
			updateWindow();
		}
	});
})();
