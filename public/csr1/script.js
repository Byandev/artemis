const loader = document.getElementById("loader");
const mainContent = document.getElementById("main-content");
const leaderboard = document.getElementById("leaderboard");
const tabs = document.querySelectorAll(".tab");

const WORKSPACE_SLUG = "developers-workspace";
const ENDPOINT_BASE = `/api/public/workspaces/${WORKSPACE_SLUG}/csrs/performance`;
const ENDPOINT_CSR_LIST = `/api/public/workspaces/${WORKSPACE_SLUG}/csrs`;

function setLoading(isLoading) {
    loader.style.display = isLoading ? "flex" : "none";
    mainContent.style.display = "block";
}

function currency(value) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
    }).format(Number(value || 0));
}

function avatarFromName(name) {
    const encoded = encodeURIComponent(name || "CSR");
    return `https://ui-avatars.com/api/?name=${encoded}&background=ffffff&color=3b006a&bold=true`;
}

function groupedLatest(rows) {
    const grouped = rows.reduce((acc, row) => {
        const key = row.period_start || "";
        if (!acc[key]) acc[key] = [];
        acc[key].push(row);
        return acc;
    }, {});

    const starts = Object.keys(grouped).sort();
    const latest = starts[starts.length - 1];
    if (!latest) return [];

    return grouped[latest].slice().sort((a, b) => a.rank - b.rank);
}

function mergeWithAllCsrs(sortedLatest, csrList) {
    const merged = sortedLatest.map((item) => ({ ...item }));
    const seenIds = new Set(merged.map((item) => Number(item.csr_id)));
    let nextRank = merged.length + 1;

    csrList.forEach((csr) => {
        const id = Number(csr.csr_id);
        if (seenIds.has(id)) return;

        merged.push({
            csr_id: id,
            rank: nextRank,
            name: csr.name || "-",
            total_orders: 0,
            total_sales: 0,
        });

        seenIds.add(id);
        nextRank++;
    });

    return merged;
}

function fillCard(card, item) {
    const avatarEl = card.querySelector(".avatar");
    const nameEl = card.querySelector(".csr-name");
    const ordersEl = card.querySelector(".orders");
    const salesEl = card.querySelector(".sales");

    if (!item) {
        avatarEl.src = avatarFromName("No Data");
        nameEl.textContent = "No Data";
        ordersEl.textContent = "Orders: 0";
        salesEl.textContent = currency(0);
        return;
    }

    avatarEl.src = avatarFromName(item.name);
    nameEl.textContent = item.name || "-";
    ordersEl.textContent = `Orders: ${item.total_orders}`;
    salesEl.textContent = currency(item.total_sales);
}

function updatePodium(sorted) {
    const cards = document.querySelectorAll(".podium .card");

    const first = sorted.find((x) => x.rank === 1);
    const second = sorted.find((x) => x.rank === 2);
    const third = sorted.find((x) => x.rank === 3);

    fillCard(cards[1], first);
    fillCard(cards[0], second);
    fillCard(cards[2], third);
}

function renderLeaderboard(sorted) {
    leaderboard.innerHTML = "";

    const others = sorted.filter((x) => x.rank > 3);

    if (!others.length) {
        leaderboard.innerHTML = '<div class="empty">No additional CSR data.</div>';
        return;
    }

    others.forEach((item) => {
        const row = document.createElement("div");
        row.classList.add("row");

        row.innerHTML = `
            <span>${item.rank}</span>
            <span class="name">
                <img src="${avatarFromName(item.name)}" alt="${item.name}">
                ${item.name || "-"}
            </span>
            <span>${item.total_orders}</span>
            <span>${currency(item.total_sales)}</span>
        `;

        leaderboard.appendChild(row);
    });
}

async function loadPeriod(period) {
    setLoading(true);

    try {
        const [performanceResponse, csrResponse] = await Promise.all([
            fetch(`${ENDPOINT_BASE}?period=${encodeURIComponent(period)}&sort_by=rank&sort_dir=desc`),
            fetch(ENDPOINT_CSR_LIST),
        ]);

        const payload = await performanceResponse.json();
        const csrPayload = await csrResponse.json();

        if (!performanceResponse.ok) {
            throw new Error(payload.message || "Failed to load leaderboard");
        }

        if (!csrResponse.ok) {
            throw new Error(csrPayload.message || "Failed to load CSR list");
        }

        const rows = Array.isArray(payload.data) ? payload.data : [];
        const csrList = Array.isArray(csrPayload.data) ? csrPayload.data : [];
        const sortedLatest = groupedLatest(rows);
        const allRows = mergeWithAllCsrs(sortedLatest, csrList);

        updatePodium(allRows);
        renderLeaderboard(allRows);
    } catch (error) {
        leaderboard.innerHTML = `<div class="empty">${error.message}</div>`;
    } finally {
        setLoading(false);
    }
}

tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        loadPeriod(tab.dataset.period);
    });
});

loadPeriod("daily");
