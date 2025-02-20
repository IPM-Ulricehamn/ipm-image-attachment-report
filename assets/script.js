document.addEventListener("DOMContentLoaded", function () {
	const filterForm = document.getElementById("filter-form");

	// Auto-reload when post type checkboxes change
	document.querySelectorAll("input[name='post_types[]']").forEach(checkbox => {
		checkbox.addEventListener("change", function () {
			filterForm.submit();
		});
	});

	// Auto-reload when the low-resolution threshold changes
	const lowresThresholdInput = document.querySelector("input[name='lowres_threshold']");
	if (lowresThresholdInput) {
		lowresThresholdInput.addEventListener("input", function () {
			clearTimeout(this.dataset.timer);
			this.dataset.timer = setTimeout(() => {
				filterForm.submit();
			}, 500); // Delay for 500ms to prevent excessive reloads
		});
	}

	// Auto-reload when "Show only low-resolution images" checkbox changes
	const lowresFilterCheckbox = document.querySelector("input[name='lowres']");
	if (lowresFilterCheckbox) {
		lowresFilterCheckbox.addEventListener("change", function () {
			filterForm.submit();
		});
	}

	// Toggle thumbnails on/off
	const toggleThumbnailsCheckbox = document.getElementById("toggle-thumbnails");
	if (toggleThumbnailsCheckbox) {
		toggleThumbnailsCheckbox.addEventListener("change", function () {
			let show = this.checked;
			document.querySelectorAll(".toggle-thumbnail").forEach(el => {
				el.style.display = show ? "table-cell" : "none";
			});
		});
	}

	// Toggle row numbers on/off
	const toggleRowNumbersCheckbox = document.getElementById("toggle-row-numbers");
	if (toggleRowNumbersCheckbox) {
		toggleRowNumbersCheckbox.addEventListener("change", function () {
			let show = this.checked;
			document.querySelectorAll(".row-number").forEach(el => {
				el.style.display = show ? "table-cell" : "none";
			});
		});
	}

	// Export to CSV
	const exportCsvButton = document.getElementById("iar-export-csv");
	if (exportCsvButton) {
		exportCsvButton.addEventListener("click", function () {
			let csvContent = "data:text/csv;charset=utf-8," +
				Array.from(document.querySelectorAll("#image-report-table tr"))
					.map(row => Array.from(row.querySelectorAll("th:not(.toggle-thumbnail):not(.row-number), td:not(.toggle-thumbnail):not(.row-number)"))
						.map(cell => `"${cell.innerText.replace(/"/g, '""')}"`)
						.join(","))
					.join("\n");

			let encodedUri = encodeURI(csvContent);
			let link = document.createElement("a");
			link.href = encodedUri;
			link.download = "image_report.csv";
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		});
	}
});
