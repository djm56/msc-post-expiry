(function () {
	var actionSelect = document.getElementById('expiry_action');
	var categoryRow = document.getElementById('expiry-category-row');

	function toggleCategoryRow() {
		if (!actionSelect || !categoryRow) {
			return;
		}

		categoryRow.style.display = actionSelect.value === 'category' ? 'table-row' : 'none';
	}

	if (!actionSelect || !categoryRow) {
		return;
	}

	actionSelect.addEventListener('change', toggleCategoryRow);
	toggleCategoryRow();
})();
