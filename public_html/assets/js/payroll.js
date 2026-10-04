let currentEmploymentFilter = "";

function toggleEmploymentMenu() {
    const menu     = document.getElementById("employmentMenu");
    const sortMenu = document.getElementById("sortMenu");
    const filterMenu = document.getElementById("filterMenu");
    const chevron  = document.getElementById("employmentChevron");
    if (!menu) return;

    const willOpen = menu.classList.contains("hidden");
    menu.classList.toggle("hidden");
    if (sortMenu) sortMenu.classList.add("hidden");
    if (filterMenu) filterMenu.classList.add("hidden");
    if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";

    if (willOpen) {
        setTimeout(() => document.addEventListener("click", closeEmploymentOnOutside, { once: true }), 0);
    }
}

function closeEmploymentOnOutside(e) {
    const wrap = document.getElementById("employmentWrap");
    const menu = document.getElementById("employmentMenu");
    const chevron = document.getElementById("employmentChevron");
    if (wrap && menu && !wrap.contains(e.target)) {
        menu.classList.add("hidden");
        if (chevron) chevron.style.transform = "";
    } else if (menu && !menu.classList.contains("hidden")) {
        document.addEventListener("click", closeEmploymentOnOutside, { once: true });
    }
}

function setEmploymentFilter(value) {
    currentEmploymentFilter = value || "";

    const labels = { "": "Employment", "full-time": "Full-time", "part-time": "Part-time" };
    document.getElementById("employmentLabel").textContent =
        labels[currentEmploymentFilter] || "Employment";

    document.querySelectorAll(".employment-check").forEach(c => {
        c.style.opacity = c.dataset.employment === currentEmploymentFilter ? "1" : "0";
    });

    const badge = document.getElementById("employmentCount");
    if (badge) {
        if (currentEmploymentFilter) {
            badge.textContent = "1";
            badge.classList.remove("hidden");
        } else {
            badge.classList.add("hidden");
        }
    }

    document.getElementById("employmentMenu")?.classList.add("hidden");
    const chevron = document.getElementById("employmentChevron");
    if (chevron) chevron.style.transform = "";

    filterPayroll();
}

// ============================================
// Filter + sort
// ============================================
function filterPayroll() {
    const search     = (document.getElementById("searchInput")?.value || "").toLowerCase();
    const category   = document.getElementById("filterCategory")?.value || "";
    const employment = currentEmploymentFilter;
    const sub        = (document.getElementById("filterSubCategory")?.value || "").toLowerCase();
    const year       = (document.getElementById("filterYearLevel")?.value || "").toLowerCase();

    const rows = document.querySelectorAll(".payroll-row");
    let visible = 0;

    rows.forEach(row => {
        const name       = row.dataset.name || "";
        const rCategory  = row.dataset.category || "";
        const rEmp       = row.dataset.employment || "";
        const rSub       = (row.dataset.sub || "").toLowerCase();
        const rYear      = (row.dataset.year || "").toLowerCase();

        const matches =
            (!search     || name.includes(search)) &&
            (!category   || rCategory === category) &&
            (!employment || rEmp === employment) &&
            (!sub        || rSub === sub) &&
            (!year       || rYear === year);

        row.classList.toggle("hidden", !matches);
        if (matches) visible++;
    });

    // Filter badge
    const active = [category, employment, sub, year].filter(Boolean).length;
    const badge = document.getElementById("filterCount");
    if (badge) {
        if (active > 0) {
            badge.textContent = active;
            badge.classList.remove("hidden");
        } else {
            badge.classList.add("hidden");
        }
    }

    // Optional: show/hide an empty state if you have one
    document.getElementById("payrollEmpty")?.classList.toggle("hidden", visible > 0);
}

function sortPayroll() {
    const rows = Array.from(document.querySelectorAll(".payroll-row"));
    if (!rows.length) return;

    const compare = (a, b) => {
        switch (currentSort) {
            case "saved-desc":
                return parseFloat(b.dataset.saved || 0) - parseFloat(a.dataset.saved || 0);
            case "convertible-desc":
                return parseFloat(b.dataset.convertible || 0) - parseFloat(a.dataset.convertible || 0);
            case "name-asc":
                return (a.dataset.name || "").localeCompare(b.dataset.name || "");
            case "name-desc":
                return (b.dataset.name || "").localeCompare(a.dataset.name || "");
            default:
                return 0;
        }
    };

    const parent = rows[0].parentElement;
    rows.sort(compare).forEach(r => parent.appendChild(r));
}

document.addEventListener("keydown", function (e) {
  if (
    e.key === "/" &&
    document.activeElement.tagName !== "INPUT" &&
    document.activeElement.tagName !== "TEXTAREA"
  ) {
    e.preventDefault();
    document.getElementById("searchInput")?.focus();
  }
});

// ============================================
// Charts
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();
  if (typeof Chart === "undefined") return;

  const data = window.PAYROLL_CHART_DATA || {};
  const COLORS = {
    green: "#0F5E3D",
    amber: "#f59e0b",
    slate: "#64748b",
    text: "#2C3E50",
    textMuted: "rgba(44, 62, 80, 0.5)",
    divider: "#E0E0E0",
  };

  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 11;
  Chart.defaults.color = COLORS.textMuted;

  const pesoFmt = (v) =>
    "₱" +
    Number(v).toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

  // Bar: saved by department
  const deptCanvas = document.getElementById("deptChart");
  if (deptCanvas && data.dept) {
    new Chart(deptCanvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: data.dept.labels,
        datasets: [
          {
            data: data.dept.values,
            backgroundColor: COLORS.green,
            borderRadius: 6,
            borderSkipped: false,
            barThickness: 22,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: COLORS.text,
            titleColor: "#fff",
            bodyColor: "#fff",
            padding: 8,
            cornerRadius: 8,
            displayColors: false,
            callbacks: { label: (ctx) => pesoFmt(ctx.parsed.x) },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: COLORS.divider },
            border: { display: false },
            ticks: {
              color: COLORS.textMuted,
              font: { size: 10 },
              callback: (v) => "₱" + Number(v).toLocaleString("en-PH"),
            },
          },
          y: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: COLORS.text, font: { size: 11, weight: "500" } },
          },
        },
      },
    });
  }

  // Donut: convertible by category
  const catCanvas = document.getElementById("catChart");
  if (catCanvas && data.category) {
    const faculty = data.category.faculty || 0;
    const staff = data.category.staff || 0;
    const total = faculty + staff;

    new Chart(catCanvas.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: ["Faculty", "Staff"],
        datasets: [
          {
            data: [faculty, staff],
            backgroundColor: [COLORS.green, COLORS.slate],
            borderColor: "#ffffff",
            borderWidth: 3,
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "68%",
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: COLORS.text,
            titleColor: "#fff",
            bodyColor: "#fff",
            padding: 8,
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed || 0;
                const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                return `₱${Number(v).toLocaleString("en-PH")} (${pct}%)`;
              },
            },
          },
        },
      },
      plugins: [
        {
          id: "centerText",
          afterDraw(chart) {
            const { ctx, chartArea } = chart;
            const cx = (chartArea.left + chartArea.right) / 2;
            const cy = (chartArea.top + chartArea.bottom) / 2;

            ctx.save();
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillStyle = COLORS.text;
            ctx.font = "bold 13px Inter, sans-serif";
            ctx.fillText(
              "₱" + Math.round(total).toLocaleString("en-PH"),
              cx,
              cy - 4
            );
            ctx.fillStyle = COLORS.textMuted;
            ctx.font = "9px Inter, sans-serif";
            ctx.fillText("total", cx, cy + 12);
            ctx.restore();
          },
        },
      ],
    });
  }
});

// ============================================
// Constants — mirror the users page
// ============================================
const SUB_CATEGORIES = {
  faculty: ["Elementary", "High School", "College"],
  staff: [
    "Registrar's Office",
    "Accounting Section",
    "HR",
    "Library & Guidance",
  ],
};

const YEAR_LEVELS_OR_DEPARTMENTS = {
  Elementary: [
    "Grade 1",
    "Grade 2",
    "Grade 3",
    "Grade 4",
    "Grade 5",
    "Grade 6",
  ],
  "High School": [
    "Grade 7",
    "Grade 8",
    "Grade 9",
    "Grade 10",
    "Grade 11",
    "Grade 12",
  ],
  College: [
    "College of Education",
    "College of Arts and Sciences",
    "College of Business Administration",
    "College of Computer Studies",
    "College of Engineering",
    "College of Nursing",
  ],
};

let currentSort = "saved-desc";

// ============================================
// Sort menu
// ============================================
function toggleSortMenu() {
  const menu = document.getElementById("sortMenu");
  const filterMenu = document.getElementById("filterMenu");
  const chevron = document.getElementById("sortChevron");
  if (!menu) return;

  const willOpen = menu.classList.contains("hidden");
  menu.classList.toggle("hidden");
  if (filterMenu) filterMenu.classList.add("hidden");
  if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";

  if (willOpen) {
    setTimeout(
      () =>
        document.addEventListener("click", closeSortOnOutside, { once: true }),
      0
    );
  }
}

function closeSortOnOutside(e) {
  const wrap = document.getElementById("sortWrap");
  const menu = document.getElementById("sortMenu");
  const chevron = document.getElementById("sortChevron");
  if (wrap && menu && !wrap.contains(e.target)) {
    menu.classList.add("hidden");
    if (chevron) chevron.style.transform = "";
  } else if (menu && !menu.classList.contains("hidden")) {
    document.addEventListener("click", closeSortOnOutside, { once: true });
  }
}

function applySort(type) {
  currentSort = type;

  const labels = {
    "saved-desc": "Most Saved",
    "convertible-desc": "Most Convertible",
    "name-asc": "Name (A → Z)",
    "name-desc": "Name (Z → A)",
    default: "Sort by",
  };
  document.getElementById("sortLabel").textContent = labels[type] || "Sort by";

  document.querySelectorAll(".sort-check").forEach((c) => {
    c.style.opacity = c.dataset.sort === type ? "1" : "0";
  });

  document.getElementById("sortMenu")?.classList.add("hidden");
  const chevron = document.getElementById("sortChevron");
  if (chevron) chevron.style.transform = "";

  sortPayroll();
}

// ============================================
// Filter menu
// ============================================
function toggleFilterMenu() {
  const menu = document.getElementById("filterMenu");
  const sortMenu = document.getElementById("sortMenu");
  const chevron = document.getElementById("filterChevron");
  if (!menu) return;

  const willOpen = menu.classList.contains("hidden");
  menu.classList.toggle("hidden");
  if (sortMenu) sortMenu.classList.add("hidden");
  if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";

  if (willOpen) {
    setTimeout(
      () =>
        document.addEventListener("click", closeFilterOnOutside, {
          once: true,
        }),
      0
    );
  }
}

function closeFilterOnOutside(e) {
  const wrap = document.getElementById("filterWrap");
  const menu = document.getElementById("filterMenu");
  const chevron = document.getElementById("filterChevron");
  if (wrap && menu && !wrap.contains(e.target)) {
    menu.classList.add("hidden");
    if (chevron) chevron.style.transform = "";
  } else if (menu && !menu.classList.contains("hidden")) {
    document.addEventListener("click", closeFilterOnOutside, { once: true });
  }
}

// ============================================
// Cascading dropdowns for the filter
// ============================================
function updateFilterSubCategories() {
  const catEl = document.getElementById("filterCategory");
  const subEl = document.getElementById("filterSubCategory");
  const yearEl = document.getElementById("filterYearLevel");
  if (!subEl) return;

  const cat = catEl?.value || "";
  const options = SUB_CATEGORIES[cat] || [];

  subEl.innerHTML = '<option value="">All Departments</option>';

  if (!cat) {
    const allSubs = new Set();
    Object.values(SUB_CATEGORIES).forEach((arr) =>
      arr.forEach((s) => allSubs.add(s))
    );
    [...allSubs].sort().forEach((s) => {
      const o = document.createElement("option");
      o.value = s.toLowerCase();
      o.textContent = s;
      subEl.appendChild(o);
    });
  } else {
    options.forEach((s) => {
      const o = document.createElement("option");
      o.value = s.toLowerCase();
      o.textContent = s;
      subEl.appendChild(o);
    });
  }

  // Reset year level whenever sub-category list changes
  if (yearEl) yearEl.innerHTML = '<option value="">All Year Levels</option>';
}

function updateFilterYearLevels() {
  const subEl = document.getElementById("filterSubCategory");
  const yearEl = document.getElementById("filterYearLevel");
  if (!yearEl) return;

  const sub = subEl?.value || "";
  yearEl.innerHTML = '<option value="">All Year Levels</option>';

  if (!sub) {
    const allYears = new Set();
    Object.values(YEAR_LEVELS_OR_DEPARTMENTS).forEach((arr) =>
      arr.forEach((y) => allYears.add(y))
    );
    [...allYears].sort().forEach((y) => {
      const o = document.createElement("option");
      o.value = y.toLowerCase();
      o.textContent = y;
      yearEl.appendChild(o);
    });
  } else {
    const matchKey = Object.keys(YEAR_LEVELS_OR_DEPARTMENTS).find(
      (k) => k.toLowerCase() === sub
    );
    const options = matchKey ? YEAR_LEVELS_OR_DEPARTMENTS[matchKey] : [];
    options.forEach((y) => {
      const o = document.createElement("option");
      o.value = y.toLowerCase();
      o.textContent = y;
      yearEl.appendChild(o);
    });
  }
}

function clearAllFilters() {
  [
    "filterCategory",
    "filterEmployment",
    "filterSubCategory",
    "filterYearLevel",
    "searchInput",
  ].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.value = "";
  });

  updateFilterSubCategories();
  updateFilterYearLevels();
  setEmploymentFilter("");

  filterPayroll();
  document.getElementById("filterMenu")?.classList.add("hidden");
  const chevron = document.getElementById("filterChevron");
  if (chevron) chevron.style.transform = "";
}

// ============================================
// Initialize on load
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  updateFilterSubCategories();
  updateFilterYearLevels();
  setEmploymentFilter("");
});
