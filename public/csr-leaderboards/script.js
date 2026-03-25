const loader = document.getElementById("loader");
const mainContent = document.getElementById("main-content");
const leaderboard = document.getElementById("leaderboard");
const tabs = document.querySelectorAll(".tab");

const WORKSPACE_SLUG = "developers-workspace";
const ENDPOINT_BASE = `/api/public/workspaces/${WORKSPACE_SLUG}/csrs/performance`;
const ENDPOINT_CSR_LIST = `/api/public/workspaces/${WORKSPACE_SLUG}/csrs`;

function formatDateLocal(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

function currentPeriodRange(period) {
    const now = new Date();

    if (period === "daily") {
        const today = formatDateLocal(now);
        return { startDate: today, endDate: today };
    }

    if (period === "weekly") {
        const start = new Date(now);
        const day = (start.getDay() + 6) % 7;
        start.setDate(start.getDate() - day);

        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        return { startDate: formatDateLocal(start), endDate: formatDateLocal(end) };
    }

    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    return { startDate: formatDateLocal(start), endDate: formatDateLocal(end) };
}

function setLoading(isLoading) {
    if (isLoading) {
        loader.classList.remove("hide");
        loader.style.display = "flex";
    } else {
        loader.classList.add("hide");
        setTimeout(() => {
            loader.style.display = "none";
        }, 220);
    }
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

function preloadImage(src) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(true);
        img.onerror = () => resolve(false);
        img.src = src;
    });
}

async function preloadAvatars(items) {
    const urls = items.map((item) => avatarFromName(item.name));
    await Promise.all(urls.map((url) => preloadImage(url)));
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
    const podium = document.querySelector(".podium");

    podium.classList.remove("ready");
    void podium.offsetWidth;

    const first = sorted.find((x) => x.rank === 1);
    const second = sorted.find((x) => x.rank === 2);
    const third = sorted.find((x) => x.rank === 3);

    fillCard(cards[1], first);
    fillCard(cards[0], second);
    fillCard(cards[2], third);

    podium.classList.add("ready");
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
        row.style.animationDelay = `${Math.min((item.rank - 4) * 0.05, 0.5)}s`;
        const rankClass = item.rank <= 10 ? "top-highlight" : "";

        row.innerHTML = `
            <span class="rank-circle ${rankClass}">${item.rank}</span>
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
        const { startDate, endDate } = currentPeriodRange(period);
        const [performanceResponse, csrResponse] = await Promise.all([
            fetch(`${ENDPOINT_BASE}?period=${encodeURIComponent(period)}&sort_by=rank&sort_dir=desc&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`),
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

        await preloadAvatars(allRows);

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
        const period = tab.dataset.period || tab.textContent.trim().toLowerCase();
        loadPeriod(period);
    });
});

window.addEventListener("load", () => {
    loadPeriod("daily");
});
