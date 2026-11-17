const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const source = fs.readFileSync(
    path.join(__dirname, "..", "assets", "js", "dashboard-tables.js"),
    "utf8"
);
const listeners = {};
let domReady;
const headings = {
    name: {
        attributes: { "aria-sort": "ascending" },
        getAttribute(name) { return this.attributes[name]; },
        setAttribute(name, value) { this.attributes[name] = value; }
    },
    email: {
        attributes: { "aria-sort": "none" },
        getAttribute(name) { return this.attributes[name]; },
        setAttribute(name, value) { this.attributes[name] = value; }
    }
};
const searchInput = {
    value: "",
    addEventListener(name, callback) {
        listeners[name] = callback;
    }
};
const resultStatus = { textContent: "" };
const sortSelect = {
    value: "0-asc",
    addEventListener(name, callback) {
        listeners[`select:${name}`] = callback;
    }
};
const rows = [
    {
        cells: [{ textContent: "Alex Morgan" }, { textContent: "zoe@example.edu" }],
        querySelectorAll() { return this.cells; }
    },
    {
        cells: [{ textContent: "Jordan Lee" }, { textContent: "aaron@example.edu" }],
        querySelectorAll() { return this.cells; }
    }
];
const noResultsRow = { hidden: true };
const displayedRows = [...rows, noResultsRow];
const tableBody = {
    insertBefore(row, before) {
        const rowIndex = displayedRows.indexOf(row);
        if (rowIndex !== -1) {
            displayedRows.splice(rowIndex, 1);
        }
        displayedRows.splice(displayedRows.indexOf(before), 0, row);
    }
};
const sortButtons = ["name", "email"].map((field) => {
    const indicator = { textContent: field === "name" ? "↑" : "↕" };
    return {
        dataset: { tableSort: field === "email" ? "1" : "0" },
        addEventListener(name, callback) {
            listeners[`sort:${field}:${name}`] = callback;
        },
        closest() {
            return headings[field];
        },
        querySelector() {
            return indicator;
        },
        indicator
    };
});
const table = {
    dataset: { itemLabel: "player", itemLabelPlural: "players" },
    querySelector(selector) {
        return selector === "tbody" ? tableBody : null;
    },
    querySelectorAll(selector) {
        return selector === "[data-table-row]" ? rows : [];
    }
};
const container = {
    querySelector(selector) {
        if (selector === "[data-dashboard-table]") {
            return table;
        }
        if (selector === "[data-table-search]") {
            return searchInput;
        }
        if (selector === "[data-table-results]") {
            return resultStatus;
        }
        if (selector === "[data-table-no-results]") {
            return noResultsRow;
        }
        if (selector === "[data-table-sort-select]") {
            return sortSelect;
        }
        return null;
    },
    querySelectorAll(selector) {
        return selector === "[data-table-sort]" ? sortButtons : [];
    }
};
const document = {
    addEventListener(name, callback) {
        if (name === "DOMContentLoaded") {
            domReady = callback;
        }
    },
    querySelector(selector) {
        if (selector === "[data-roster-search]") {
            return searchInput;
        }
        if (selector === "[data-roster-results]") {
            return resultStatus;
        }
        if (selector === "[data-roster-no-results]") {
            return noResultsRow;
        }
        if (selector === "[data-roster-body]") {
            return tableBody;
        }
        return selector === "[data-roster-sort-select]" ? sortSelect : null;
    },
    querySelectorAll(selector) {
        return selector === "[data-table-container]" ? [container] : [];
    }
};

vm.runInNewContext(source, { document });
domReady();

assert.equal(resultStatus.textContent, "Showing all 2 players.");
searchInput.value = "AARON@EXAMPLE.EDU";
listeners.input();
assert.equal(rows[0].hidden, true, "non-matching roster rows should be hidden");
assert.equal(rows[1].hidden, false, "search should match case-insensitively");
assert.equal(noResultsRow.hidden, true);
assert.equal(resultStatus.textContent, "Showing 1 of 2 players.");

searchInput.value = "not found";
listeners.input();
assert.equal(noResultsRow.hidden, false, "an empty search result should be announced");
assert.equal(resultStatus.textContent, "No players match your search.");

searchInput.value = "";
listeners.input();
assert.equal(rows.every((row) => !row.hidden), true, "clearing search should restore all rows");
assert.equal(resultStatus.textContent, "Showing all 2 players.");

listeners["sort:email:click"]();
assert.equal(displayedRows[0], rows[1], "the email header should sort ascending");
assert.equal(headings.email.attributes["aria-sort"], "ascending");
assert.equal(headings.name.attributes["aria-sort"], "none");
assert.equal(sortSelect.value, "1-asc");

listeners["sort:email:click"]();
assert.equal(displayedRows[0], rows[0], "clicking the active email header should reverse order");
assert.equal(headings.email.attributes["aria-sort"], "descending");

sortSelect.value = "0-asc";
listeners["select:change"]();
assert.equal(displayedRows[0], rows[0], "the mobile sort control should sort by player name");
assert.equal(headings.name.attributes["aria-sort"], "ascending");
assert.equal(sortSelect.value, "0-asc");

console.log("Dashboard table filtering and sorting passed.");
