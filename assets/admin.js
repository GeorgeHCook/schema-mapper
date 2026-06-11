(function () {
	'use strict';

	// Initial pass: set data-source on every field row so the CSS shows the right input.
	function syncRow(row) {
		var sel = row.querySelector('.schema-mapper-source-select');
		if (!sel) return;
		row.setAttribute('data-source', sel.value || '');
		var keySelect = row.querySelector('.schema-mapper-acf-key');
		if (keySelect) {
			Array.prototype.forEach.call(keySelect.options, function (opt) {
				if (!opt.dataset || !opt.dataset.source || !opt.value) return;
				opt.disabled = (sel.value && opt.dataset.source !== sel.value);
			});
			// If currently selected option is now disabled, reset to placeholder.
			if (keySelect.selectedOptions[0] && keySelect.selectedOptions[0].disabled) {
				keySelect.value = '';
			}
		}
	}

	// Gate value control: when the chosen condition field has discrete choices
	// (ACF select/radio/true_false), render a <select> of those values; otherwise
	// render a free-text input. Both controls have the same name="...[value]"
	// when active so whichever is visible drives what gets saved.
	function initGate(table) {
		var pt = table.getAttribute('data-sm-gate');
		if (!pt) return;
		var script = document.querySelector('script[data-sm-gate-choices="' + pt + '"]');
		if (!script) return;
		var meta;
		try { meta = JSON.parse(script.textContent.trim() || '{}'); } catch (e) { meta = {}; }

		var sourceSel = table.querySelector('[data-sm-gate-source]');
		var keySel = table.querySelector('[data-sm-gate-key]');
		var valueWrap = table.querySelector('[data-sm-gate-value]');
		var valueSelect = table.querySelector('[data-sm-gate-value-select]');
		var valueInput = table.querySelector('[data-sm-gate-value-input]');
		if (!sourceSel || !keySel || !valueWrap || !valueSelect || !valueInput) return;

		// Canonical submit name is stored on the wrapper so we don't lose it
		// when toggling name="" on whichever control is hidden.
		var VALUE_NAME = valueWrap.getAttribute('data-sm-value-name') || '';

		function getChoices() {
			if (sourceSel.value !== 'acf') return null;
			var key = keySel.value;
			if (!key) return null;
			var m = meta[key];
			if (!m || !m.choices) return null;
			var keys = Object.keys(m.choices);
			return keys.length ? m.choices : null;
		}

		function render() {
			var choices = getChoices();
			if (choices) {
				// Build select options. Preserve current value if it's still valid.
				var current = valueSelect.value || valueInput.value || '';
				valueSelect.innerHTML = '';
				var placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent = '— Choose a value —';
				valueSelect.appendChild(placeholder);
				Object.keys(choices).forEach(function (cv) {
					var o = document.createElement('option');
					o.value = cv;
					o.textContent = choices[cv] + ' (' + cv + ')';
					if (String(current) === String(cv)) o.selected = true;
					valueSelect.appendChild(o);
				});
				valueSelect.style.display = '';
				valueSelect.setAttribute('name', VALUE_NAME);
				valueInput.style.display = 'none';
				valueInput.setAttribute('name', '');
			} else {
				// Free-text fallback. Carry over whatever value was in the select.
				if (valueSelect.value && !valueInput.value) {
					valueInput.value = valueSelect.value;
				}
				valueSelect.style.display = 'none';
				valueSelect.setAttribute('name', '');
				valueInput.style.display = '';
				valueInput.setAttribute('name', VALUE_NAME);
			}
		}

		sourceSel.addEventListener('change', render);
		keySel.addEventListener('change', render);
		render();
	}

	function init() {
		var rows = document.querySelectorAll('.schema-mapper-field-row');
		Array.prototype.forEach.call(rows, function (row) {
			var sel = row.querySelector('.schema-mapper-source-select');
			if (!sel) return;
			sel.addEventListener('change', function () { syncRow(row); });
			syncRow(row);
		});

		var gates = document.querySelectorAll('[data-sm-gate]');
		Array.prototype.forEach.call(gates, initGate);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
