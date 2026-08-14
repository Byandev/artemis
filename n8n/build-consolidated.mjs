/**
 * Assembles "Prod - Gencys Sync.json" from the three per-type exports.
 *
 * Written as a build step rather than hand-authored JSON so the Puppeteer scrape
 * code is carried across verbatim — transcribing ~1,500 lines of browser
 * automation by hand is how subtle selector bugs get introduced. Re-run it after
 * editing any source flow:
 *
 *   node n8n/build-consolidated.mjs
 *
 * What it does per branch: drops the source's Webhook, Login and final callback
 * node (those become shared), prefixes every remaining node name so the three
 * branches can't collide, rewrites $('Old Name') references to match, and wires
 * the branch between the Switch and the shared callback.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

/*
 * Each branch keeps its own payload shape — the three callback endpoints in
 * Artemis expect genuinely different bodies, so the shaping can't be shared.
 * What IS shared is the posting, which is what guarantees every type carries
 * sync_run_id and the execution id.
 *
 * `shape` replaces the jsonBody expression that used to live on each flow's own
 * HTTP Request node, and emits { body: … } for the shared Callback to post.
 */
const BRANCHES = [
    {
        type: 'transaction_history',
        prefix: 'TX',
        file: 'Prod - Fetch ERP Inventory Transaction.json',
        // The node the shared callback replaces, and the node Login fed into.
        callbackNode: 'HTTP Request',
        // TransactionHistoryController reads sync_run_id and the execution id
        // per entry, so the id is stamped onto each item rather than the root.
        shape: `const executionId = $execution.id;
const items = ($json.data ?? []).map((item) => ({
    ...item,
    n8n_execution_id: executionId,
}));

return [{ json: { body: { items } } }];`,
    },
    {
        type: 'daily_sales_tracker',
        prefix: 'DS',
        file: 'Prod - Fetch Daily Sales Tracker.json',
        callbackNode: 'HTTP Request',
        // This flow used to post an inventory_id / purchase_orders body copied
        // from the purchase-order flow, and never echoed sync_run_id — which is
        // why every daily-sales run sat pending until the sweeper failed it.
        shape: `const body = $('Webhook').first().json.body;

return [{
    json: {
        body: {
            workspace_id: body.workspace_id,
            api_key: body.workspace_api_key,
            sync_run_id: body.sync_run_id,
            n8n_execution_id: $execution.id,
            orders: $json.orders ?? [],
        },
    },
}];`,
    },
    {
        type: 'purchase_order',
        prefix: 'PO',
        file: 'Prod - Fetch Purchase Order.json',
        // This flow posts from two places; both become the shared callback.
        callbackNode: ['HTTP Request', 'HTTP Request1'],
        shape: `const executionId = $execution.id;
const data = ($json.data ?? []).map((entry) => ({
    ...entry,
    n8n_execution_id: executionId,
}));

return [{ json: { body: { data } } }];`,
    },
];

const read = (f) => JSON.parse(readFileSync(join(here, f), 'utf8'));

/** Every node feeding `target`, as [fromNode, outputIndex] pairs. */
function inboundEdges(connections, target) {
    const edges = [];
    for (const [from, spec] of Object.entries(connections)) {
        (spec.main ?? []).forEach((outputs, outputIndex) => {
            (outputs ?? []).forEach((c) => {
                if (c.node === target) edges.push([from, outputIndex]);
            });
        });
    }
    return edges;
}

/** Nodes `source` feeds, flattened. */
function outboundNodes(connections, source) {
    return ((connections[source]?.main ?? []).flat() ?? []).map((c) => c.node);
}

/**
 * Rewrite $('Name') / $node["Name"] references to their prefixed equivalents.
 * Webhook is deliberately left alone — it stays a single shared node.
 */
function rewriteRefs(value, renames) {
    if (typeof value === 'string') {
        let out = value;
        for (const [from, to] of Object.entries(renames)) {
            out = out
                .split(`$('${from}')`)
                .join(`$('${to}')`)
                .split(`$("${from}")`)
                .join(`$("${to}")`)
                .split(`$node["${from}"]`)
                .join(`$node["${to}"]`);
        }
        return out;
    }
    if (Array.isArray(value)) return value.map((v) => rewriteRefs(v, renames));
    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([k, v]) => [k, rewriteRefs(v, renames)]),
        );
    }
    return value;
}

const nodes = [];
const connections = {};
const addConnection = (from, to, outputIndex = 0) => {
    connections[from] ??= { main: [] };
    while (connections[from].main.length <= outputIndex)
        connections[from].main.push([]);
    connections[from].main[outputIndex].push({
        node: to,
        type: 'main',
        index: 0,
    });
};

// ── Shared head: one webhook, one login ──────────────────────────────────
const template = read(BRANCHES[0].file);
const sourceWebhook = template.nodes.find((n) => n.type.endsWith('.webhook'));
const sourceLogin = template.nodes.find((n) => n.name === 'Login');

nodes.push({
    ...sourceWebhook,
    name: 'Webhook',
    position: [-640, 0],
    // A single path for every type. Laravel picks the branch via body.type.
    parameters: { ...sourceWebhook.parameters, path: 'gencys-sync' },
});

nodes.push({ ...sourceLogin, name: 'Login', position: [-440, 0] });
addConnection('Webhook', 'Login');

// ── Switch on body.type ──────────────────────────────────────────────────
nodes.push({
    parameters: {
        rules: {
            values: BRANCHES.map((b) => ({
                conditions: {
                    options: {
                        caseSensitive: true,
                        leftValue: '',
                        typeValidation: 'strict',
                        version: 2,
                    },
                    conditions: [
                        {
                            leftValue:
                                "={{ $('Webhook').first().json.body.type }}",
                            rightValue: b.type,
                            operator: { type: 'string', operation: 'equals' },
                        },
                    ],
                    combinator: 'and',
                },
                renameOutput: true,
                outputKey: b.type,
            })),
        },
        options: {},
    },
    type: 'n8n-nodes-base.switch',
    typeVersion: 3.2,
    position: [-240, 0],
    id: 'switch-on-type',
    name: 'Route by type',
});
addConnection('Login', 'Route by type');

// ── Branches ─────────────────────────────────────────────────────────────
BRANCHES.forEach((branch, branchIndex) => {
    const wf = read(branch.file);
    const callbackNodes = [branch.callbackNode].flat();
    const drop = new Set(['Webhook', 'Login', ...callbackNodes]);

    const renames = {};
    for (const node of wf.nodes) {
        if (drop.has(node.name)) continue;
        renames[node.name] = `${branch.prefix} · ${node.name}`;
    }

    const yOffset = branchIndex * 700 - 700;

    for (const node of wf.nodes) {
        if (drop.has(node.name)) continue;
        nodes.push({
            ...rewriteRefs(node, renames),
            id: `${branch.prefix}-${node.id}`,
            name: renames[node.name],
            position: [
                (node.position?.[0] ?? 0) + 200,
                (node.position?.[1] ?? 0) + yOffset,
            ],
        });
    }

    // Re-create the branch's internal wiring under the new names.
    for (const [from, spec] of Object.entries(wf.connections)) {
        if (drop.has(from)) continue;
        (spec.main ?? []).forEach((outputs, outputIndex) => {
            (outputs ?? []).forEach((c) => {
                if (drop.has(c.node)) return;
                addConnection(renames[from], renames[c.node], outputIndex);
            });
        });
    }

    // Switch output N → whatever Login used to feed.
    for (const entry of outboundNodes(wf.connections, 'Login')) {
        if (renames[entry])
            addConnection('Route by type', renames[entry], branchIndex);
    }

    // Whatever fed the old callback now feeds a shaping node, which builds the
    // body that flow's endpoint expects and hands it to the shared Callback.
    let shapeIndex = 0;

    for (const cb of callbackNodes) {
        for (const [from, outputIndex] of inboundEdges(wf.connections, cb)) {
            if (!renames[from]) continue;

            const shapeName = `${branch.prefix} · Shape payload${shapeIndex > 0 ? ` ${shapeIndex}` : ''}`;
            shapeIndex++;

            nodes.push({
                parameters: { jsCode: branch.shape },
                type: 'n8n-nodes-base.code',
                typeVersion: 2,
                position: [1180, yOffset],
                id: `${branch.prefix}-shape-${shapeIndex}`,
                name: shapeName,
            });

            addConnection(renames[from], shapeName, outputIndex);
            addConnection(shapeName, 'Callback');
        }

        // The daily-sales loop feeds the callback's output back into a Wait node;
        // preserve that so the loop still advances after posting.
        for (const after of outboundNodes(wf.connections, cb)) {
            if (renames[after]) addConnection('Callback', renames[after]);
        }
    }
});

// ── Shared callback ──────────────────────────────────────────────────────
// One node posts every type's result. Because it's the only exit, no branch can
// forget sync_run_id or the execution id — the bug class that left daily sales,
// pages and intern records permanently unresolved.
nodes.push({
    parameters: {
        method: 'POST',
        url: "={{ $('Webhook').first().json.body.webhook_url }}",
        sendBody: true,
        specifyBody: 'json',
        // The preceding Shape payload node has already built the exact body this
        // type's endpoint expects, including sync_run_id and the execution id.
        jsonBody: '={{ JSON.stringify($json.body) }}',
        options: {},
    },
    type: 'n8n-nodes-base.httpRequest',
    typeVersion: 4.2,
    position: [1400, 0],
    id: 'shared-callback',
    name: 'Callback',
    // A failed post must not kill the loop that drives the remaining chunks.
    onError: 'continueRegularOutput',
});

writeFileSync(
    join(here, 'Prod - Gencys Sync.json'),
    JSON.stringify(
        {
            name: 'Prod - Gencys Sync',
            nodes,
            connections,
            settings: { executionOrder: 'v1' },
            pinData: {},
        },
        null,
        2,
    ),
);

console.log(`Wrote Prod - Gencys Sync.json — ${nodes.length} nodes`);
