document.addEventListener("DOMContentLoaded", () => {
    const initializeTable = (container) => {
        const table = container.querySelector("[data-dashboard-table]");
        const searchInput = container.querySelector("[data-table-search]");
        const resultStatus = container.querySelector("[data-table-results]");
        const rows = table
            ? Array.from(table.querySelectorAll("[data-table-row]"))
            : [];
        const originalRows = rows.slice();
        const noResultsRow = container.querySelector("[data-table-no-results]");
        const tableBody = table ? table.querySelector("tbody") : null;
        const sortButtons = Array.from(container.querySelectorAll("[data-table-sort]"));
        const sortSelect = container.querySelector("[data-table-sort-select]");

        if (!table || !searchInput || !resultStatus || rows.length === 0
            || !noResultsRow || !tableBody || sortButtons.length === 0) {
            return;
        }

        const singularLabel = table.dataset.itemLabel || "entry";
        const pluralLabel = table.dataset.itemLabelPlural || `${singularLabel}s`;
        const initialSort = sortButtons.find((button) => (
            button.closest("th").getAttribute("aria-sort") === "ascending"
            || button.closest("th").getAttribute("aria-sort") === "descending"
        ));
        let sortField = initialSort ? Number(initialSort.dataset.tableSort) : null;
        let sortDirection = initialSort
            && initialSort.closest("th").getAttribute("aria-sort") === "descending"
            ? -1
            : 1;

        const updateResults = () => {
            const searchTerm = searchInput.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            rows.forEach((row) => {
                const searchableText = Array.from(row.querySelectorAll("[data-search-cell]"))
                    .map((cell) => cell.textContent)
                    .join(" ")
                    .toLocaleLowerCase();
                const matches = searchableText.includes(searchTerm);
                row.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });

            noResultsRow.hidden = visibleCount > 0;
            if (searchTerm === "") {
                resultStatus.textContent = `Showing all ${rows.length} ${rows.length === 1 ? singularLabel : pluralLabel}.`;
            } else {
                resultStatus.textContent = visibleCount === 0
                    ? `No ${pluralLabel} match your search.`
                    : `Showing ${visibleCount} of ${rows.length} ${pluralLabel}.`;
            }
        };

        const updateSortIndicators = () => {
            sortButtons.forEach((button) => {
                const columnIndex = Number(button.dataset.tableSort);
                const isActive = columnIndex === sortField;
                const heading = button.closest("th");
                heading.setAttribute(
                    "aria-sort",
                    isActive ? (sortDirection === 1 ? "ascending" : "descending") : "none"
                );
                button.querySelector("[data-sort-indicator]").textContent = isActive
                    ? (sortDirection === 1 ? "↑" : "↓")
                    : "↕";
            });
        };

        const applySort = (columnIndex, direction) => {
            sortField = columnIndex;
            sortDirection = direction;
            rows.sort((left, right) => (
                left.cells[columnIndex].textContent.trim().localeCompare(
                    right.cells[columnIndex].textContent.trim(),
                    undefined,
                    { sensitivity: "base", numeric: true }
                ) * direction
            ));
            rows.forEach((row) => tableBody.insertBefore(row, noResultsRow));
            updateSortIndicators();

            if (sortSelect) {
                sortSelect.value = `${columnIndex}-${direction === 1 ? "asc" : "desc"}`;
            }
        };

        const restoreOriginalOrder = () => {
            sortField = null;
            sortDirection = 1;
            rows.splice(0, rows.length, ...originalRows);
            rows.forEach((row) => tableBody.insertBefore(row, noResultsRow));
            updateSortIndicators();
        };

        searchInput.addEventListener("input", updateResults);
        sortButtons.forEach((button) => {
            button.addEventListener("click", () => {
                const columnIndex = Number(button.dataset.tableSort);
                applySort(columnIndex, columnIndex === sortField ? -sortDirection : 1);
            });
        });
        if (sortSelect) {
            sortSelect.addEventListener("change", () => {
                if (sortSelect.value === "") {
                    restoreOriginalOrder();
                    return;
                }

                const [columnIndex, order] = sortSelect.value.split("-");
                applySort(Number(columnIndex), order === "desc" ? -1 : 1);
            });
        }
        updateResults();
        if (sortSelect && sortField !== null) {
            sortSelect.value = `${sortField}-${sortDirection === 1 ? "asc" : "desc"}`;
        }
    };

    document.querySelectorAll("[data-table-container]").forEach(initializeTable);
});
