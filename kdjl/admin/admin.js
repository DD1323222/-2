(function () {
	var confirmations = document.querySelectorAll('[data-confirm]');
	for (var i = 0; i < confirmations.length; i++) {
		confirmations[i].onsubmit = function () {
			return window.confirm(this.getAttribute('data-confirm'));
		};
	}
	var selectAll = document.querySelectorAll('[data-select-all]');
	var updateBatch = function (group) {
		var master = document.querySelector('[data-select-all="' + group + '"]');
		var items = document.querySelectorAll('[data-select-item="' + group + '"]');
		var buttons = document.querySelectorAll('[data-batch-submit="' + group + '"]');
		var counters = document.querySelectorAll('[data-selected-count="' + group + '"]');
		var checked = 0;
		for (var k = 0; k < items.length; k++) if (items[k].checked) checked++;
		if (master) {
			master.checked = items.length > 0 && checked === items.length;
			master.indeterminate = checked > 0 && checked < items.length;
		}
		for (var b = 0; b < buttons.length; b++) buttons[b].disabled = checked === 0;
		for (var c = 0; c < counters.length; c++) counters[c].innerHTML = checked;
	};
	var updateScopeQuery = function () {
		var scopes = document.querySelectorAll('[data-scope-item]');
		var buttons = document.querySelectorAll('[data-scope-query]');
		var counters = document.querySelectorAll('[data-scope-selected-count]');
		var checked = 0;
		for (var i = 0; i < scopes.length; i++) if (scopes[i].checked) checked++;
		for (var j = 0; j < buttons.length; j++) buttons[j].disabled = checked === 0;
		for (var k = 0; k < counters.length; k++) counters[k].innerHTML = checked;
	};
	var updateDropActions = function () {
		var monsters = document.querySelectorAll('[data-select-item="drop-monsters"]');
		var prop = document.querySelector('[data-drop-prop]:checked');
		var selected = false;
		for (var i = 0; i < monsters.length; i++) if (monsters[i].checked) selected = true;
		var buttons = document.querySelectorAll('[data-drop-action]');
		for (var j = 0; j < buttons.length; j++) buttons[j].disabled = !selected || !prop;
	};
	var syncSelectedMonsters = function () {
		var fields = document.querySelectorAll('[data-selected-gpc-csv]');
		if (!fields.length) return;
		var monsters = document.querySelectorAll('[data-select-item="drop-monsters"]');
		var ids = [];
		for (var i = 0; i < monsters.length; i++) if (monsters[i].checked) ids.push(monsters[i].value);
		for (var j = 0; j < fields.length; j++) fields[j].value = ids.join(',');
	};
	var updateTaskPreviousList = function () {
		var sequence = document.querySelector('[data-task-sequence-select]');
		var previous = document.querySelector('[data-task-previous-select]');
		if (!sequence || !previous) return;
		var seq = parseInt(sequence.value, 10) || 0;
		var current = parseInt(previous.value, 10) || 0;
		var initial = parseInt(previous.getAttribute('data-task-initial-previous'), 10) || 0;
		if (current < 1 && initial > 0) current = initial;
		var selectedOk = false;
		for (var i = 0; i < previous.options.length; i++) {
			var option = previous.options[i];
			var value = parseInt(option.value, 10) || 0;
			var xulie = parseInt(option.getAttribute('data-task-xulie'), 10) || 0;
			var visible = value < 1 || seq < 1 || xulie === seq;
			option.disabled = !visible;
			option.style.display = visible ? '' : 'none';
			if (value === current && visible) {
				option.selected = true;
				selectedOk = true;
			}
		}
		if (!selectedOk) previous.value = '0';
	};
	var prepareDropSubmit = function () {
		syncSelectedMonsters();
		var monsters = document.querySelectorAll('[data-select-item="drop-monsters"]');
		for (var i = 0; i < monsters.length; i++) monsters[i].removeAttribute('name');
		return true;
	};
	for (var j = 0; j < selectAll.length; j++) {
		selectAll[j].onchange = function () {
			var group = this.getAttribute('data-select-all');
			var items = document.querySelectorAll('[data-select-item="' + group + '"]');
			for (var k = 0; k < items.length; k++) items[k].checked = this.checked;
			updateBatch(group);
			updateScopeQuery();
			updateDropActions();
			syncSelectedMonsters();
		};
		updateBatch(selectAll[j].getAttribute('data-select-all'));
	}
	var selectedItems = document.querySelectorAll('[data-select-item]');
	for (var m = 0; m < selectedItems.length; m++) {
		selectedItems[m].onchange = function () {
			updateBatch(this.getAttribute('data-select-item'));
			updateScopeQuery();
			updateDropActions();
			syncSelectedMonsters();
		};
	}
	var dropProps = document.querySelectorAll('[data-drop-prop]');
	for (var n = 0; n < dropProps.length; n++) dropProps[n].onchange = updateDropActions;
	var scopeFilters = document.querySelectorAll('[data-scope-filter]');
	for (var o = 0; o < scopeFilters.length; o++) {
		var filterScopes = function () {
			var term = this.value.toLowerCase().replace(/^\s+|\s+$/g, '');
			var options = document.querySelectorAll('[data-scope-option]');
			for (var i = 0; i < options.length; i++) {
				var text = (options[i].getAttribute('data-scope-search') || '').toLowerCase();
				options[i].style.display = term === '' || text.indexOf(term) !== -1 ? '' : 'none';
			}
		};
		scopeFilters[o].onkeyup = filterScopes;
		scopeFilters[o].oninput = filterScopes;
	}
	var confirmActions = document.querySelectorAll('[data-confirm-action]');
	for (var p = 0; p < confirmActions.length; p++) {
		confirmActions[p].onclick = function () { return window.confirm(this.getAttribute('data-confirm-action')); };
	}
	var taskSequence = document.querySelector('[data-task-sequence-select]');
	if (taskSequence) taskSequence.onchange = updateTaskPreviousList;
	var dropForms = document.querySelectorAll('[data-drop-form]');
	for (var q = 0; q < dropForms.length; q++) dropForms[q].onsubmit = prepareDropSubmit;
	updateScopeQuery();
	updateDropActions();
	syncSelectedMonsters();
	updateTaskPreviousList();
}());
