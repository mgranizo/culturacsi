(function () {
	'use strict';

	if (window.__csiActivityTreeCascadeBound) {
		return;
	}

	window.__csiActivityTreeCascadeBound = true;

	function directCheckbox(listItem) {
		if (!listItem || !listItem.children) {
			return null;
		}

		for (var i = 0; i < listItem.children.length; i++) {
			var child = listItem.children[i];
			if (!child || child.tagName !== 'LABEL') {
				continue;
			}

			var checkbox = child.querySelector('input[type="checkbox"]');
			if (checkbox) {
				return checkbox;
			}
		}

		return null;
	}

	function parentItem(listItem) {
		var current = listItem ? listItem.parentElement : null;
		while (current) {
			if (current.classList && current.classList.contains('csi-activity-tree-item')) {
				return current;
			}
			current = current.parentElement;
		}

		return null;
	}

	function directSettore2Checkboxes(listItem) {
		var boxes = [];
		if (!listItem || !listItem.children) {
			return boxes;
		}

		for (var i = 0; i < listItem.children.length; i++) {
			var child = listItem.children[i];
			if (!child || child.tagName !== 'UL' || !child.classList || !child.classList.contains('level-settore2')) {
				continue;
			}

			for (var j = 0; j < child.children.length; j++) {
				var node = child.children[j];
				if (!node || !node.classList || !node.classList.contains('csi-activity-tree-item')) {
					continue;
				}

				var checkbox = directCheckbox(node);
				if (checkbox) {
					boxes.push(checkbox);
				}
			}
		}

		return boxes;
	}

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!target || target.tagName !== 'INPUT' || target.type !== 'checkbox') {
			return;
		}

		if (!target.closest('.csi-activity-tree')) {
			return;
		}

		var listItem = target.closest('li.csi-activity-tree-item');
		if (!listItem) {
			return;
		}

		if (target.checked) {
			var ancestor = parentItem(listItem);
			while (ancestor) {
				var ancestorCheckbox = directCheckbox(ancestor);
				if (ancestorCheckbox && !ancestorCheckbox.checked) {
					ancestorCheckbox.checked = true;
				}
				ancestor = parentItem(ancestor);
			}

			if (listItem.classList && listItem.classList.contains('level-settore')) {
				var settore2Checkboxes = directSettore2Checkboxes(listItem);
				if (settore2Checkboxes.length === 1 && !settore2Checkboxes[0].checked) {
					settore2Checkboxes[0].checked = true;
				}
			}

			return;
		}

		if (listItem.classList && (listItem.classList.contains('level-macro') || listItem.classList.contains('level-settore'))) {
			var descendants = listItem.querySelectorAll('li.csi-activity-tree-item input[type="checkbox"]');
			for (var i = 0; i < descendants.length; i++) {
				descendants[i].checked = false;
			}
		}
	});
}());
