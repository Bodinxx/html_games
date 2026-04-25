const state = { data: null, reachableMap: new Map() };

const elements = {
  stats: document.getElementById('stats'),
  marketRows: document.getElementById('market-rows'),
  message: document.getElementById('message'),
  fuelAmount: document.getElementById('fuel-amount'),
  refuelBtn: document.getElementById('refuel-btn'),
  resetBtn: document.getElementById('reset-btn'),
  upgradeList: document.getElementById('upgrade-list'),
  mapCanvas: document.getElementById('galaxy-map'),
  mapHint: document.getElementById('map-hint'),
  combatPanel: document.getElementById('combat-panel'),
  combatCanvas: document.getElementById('combat-canvas'),
  attackBtn: document.getElementById('attack-btn'),
  braceBtn: document.getElementById('brace-btn'),
  fleeBtn: document.getElementById('flee-btn'),
};

function setMessage(text, isError = false) {
  elements.message.textContent = text;
  elements.message.classList.toggle('error', isError);
}

async function api(action, payload = null) {
  const options = { method: payload ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json' } };
  if (payload) options.body = JSON.stringify(payload);
  const response = await fetch(`api.php?action=${action}`, options);
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Request failed.');
  return data;
}

function renderStats() {
  const { state: player, currentPlanet, cargoUsed } = state.data;
  elements.stats.innerHTML = `
    <article><strong>Turn</strong><span>${player.turn}</span></article>
    <article><strong>System</strong><span>${currentPlanet.name}</span></article>
    <article><strong>Cash</strong><span>${player.cash} cr</span></article>
    <article><strong>Fuel</strong><span>${player.fuel}/${player.maxFuel}</span></article>
    <article><strong>Hull</strong><span>${player.hull}/${player.maxHull}</span></article>
    <article><strong>Cargo</strong><span>${cargoUsed}/${player.cargoCapacity}</span></article>
    <article><strong>Weapon Lv</strong><span>${player.weaponLevel}</span></article>
    <article><strong>Shield Lv</strong><span>${player.shieldLevel}</span></article>
  `;
}

function renderMarket() {
  const disabled = state.data.state.combat ? 'disabled' : '';
  elements.marketRows.innerHTML = state.data.goods.map((good) => {
    const owned = state.data.state.cargo[good.id] || 0;
    return `<tr>
      <td>${good.name}</td><td>${state.data.market[good.id]} cr</td><td>${owned}</td>
      <td>
        <button ${disabled} data-buy="${good.id}">Buy</button>
        <button ${disabled} data-sell="${good.id}">Sell</button>
      </td>
    </tr>`;
  }).join('');

  elements.marketRows.querySelectorAll('[data-buy]').forEach((b) => b.onclick = () => trade('buy', b.dataset.buy));
  elements.marketRows.querySelectorAll('[data-sell]').forEach((b) => b.onclick = () => trade('sell', b.dataset.sell));
}

function nextUpgrade(type) {
  const playerLevel = type === 'weapons' ? state.data.state.weaponLevel : state.data.state.shieldLevel;
  return state.data.upgrades[type].find((item) => item.level === playerLevel + 1);
}

function renderUpgrades() {
  const weapon = nextUpgrade('weapons');
  const shield = nextUpgrade('shields');
  const disabled = state.data.state.combat ? 'disabled' : '';

  const weaponHtml = weapon
    ? `<div class="upgrade-item"><span>${weapon.name} (${weapon.price} cr)</span><button ${disabled} id="buy-weapon">Buy</button></div>`
    : '<div class="upgrade-item">Weapons maxed</div>';
  const shieldHtml = shield
    ? `<div class="upgrade-item"><span>${shield.name} (${shield.price} cr)</span><button ${disabled} id="buy-shield">Buy</button></div>`
    : '<div class="upgrade-item">Shields maxed</div>';

  elements.upgradeList.innerHTML = weaponHtml + shieldHtml;
  const wb = document.getElementById('buy-weapon');
  const sb = document.getElementById('buy-shield');
  if (wb) wb.onclick = () => buyUpgrade('weapons');
  if (sb) sb.onclick = () => buyUpgrade('shields');
}

function buildReachableMap() {
  state.reachableMap = new Map();
  state.data.reachablePlanets.forEach((item) => state.reachableMap.set(item.id, item.fuelCost));
}

function drawGalaxy() {
  const ctx = elements.mapCanvas.getContext('2d');
  const { planets, currentPlanet } = state.data;
  ctx.fillStyle = '#040b18';
  ctx.fillRect(0, 0, 1000, 1000);
  for (let i = 0; i < 260; i += 1) {
    ctx.fillStyle = `rgba(255,255,255,${Math.random() * 0.8})`;
    ctx.fillRect(Math.random() * 1000, Math.random() * 1000, 1.5, 1.5);
  }

  planets.forEach((planet) => {
    const reachable = state.reachableMap.has(planet.id);
    const isCurrent = planet.id === currentPlanet.id;
    ctx.beginPath();
    ctx.arc(planet.x, planet.y, isCurrent ? 8 : 4, 0, Math.PI * 2);
    ctx.fillStyle = isCurrent ? '#4cffd8' : reachable ? '#66a3ff' : '#45526f';
    ctx.fill();
  });
}

function bindMapClick() {
  elements.mapCanvas.onclick = async (event) => {
    if (state.data.state.combat) return;
    const rect = elements.mapCanvas.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 1000;
    const y = ((event.clientY - rect.top) / rect.height) * 1000;

    let selected = null;
    state.data.planets.forEach((planet) => {
      const d = Math.hypot(planet.x - x, planet.y - y);
      if (d < 10 && state.reachableMap.has(planet.id)) selected = planet;
    });

    if (!selected) {
      elements.mapHint.textContent = 'Select a highlighted system within range.';
      return;
    }

    const fuelCost = state.reachableMap.get(selected.id);
    try {
      state.data = await api('travel', { destination: selected.id });
      rerender();
      setMessage(`Jumped to ${selected.name} (-${fuelCost} fuel). ${state.data.state.lastEvent}`);
    } catch (error) {
      setMessage(error.message, true);
    }
  };
}

function drawCombat() {
  const ctx = elements.combatCanvas.getContext('2d');
  ctx.fillStyle = '#070f1e';
  ctx.fillRect(0, 0, 900, 300);

  const combat = state.data.state.combat;
  if (!combat) {
    elements.combatPanel.classList.add('hidden');
    return;
  }

  elements.combatPanel.classList.remove('hidden');
  ctx.fillStyle = '#52d7ff';
  ctx.beginPath();
  ctx.moveTo(120, 150); ctx.lineTo(260, 90); ctx.lineTo(250, 210); ctx.closePath(); ctx.fill();
  ctx.fillStyle = '#ff6b6b';
  ctx.beginPath();
  ctx.moveTo(760, 150); ctx.lineTo(620, 90); ctx.lineTo(630, 210); ctx.closePath(); ctx.fill();

  ctx.fillStyle = '#fff';
  ctx.font = '18px sans-serif';
  ctx.fillText(`Your Hull: ${state.data.state.hull}/${state.data.state.maxHull}`, 30, 30);
  ctx.fillText(`${combat.enemyName} Hull: ${combat.enemyHull}/${combat.enemyMaxHull}`, 560, 30);
  ctx.fillText(combat.log, 30, 275);
}

function rerender() {
  buildReachableMap();
  renderStats();
  renderMarket();
  renderUpgrades();
  drawGalaxy();
  drawCombat();
}

async function refresh(msg = '') {
  try {
    state.data = await api('status');
    rerender();
    if (msg) setMessage(msg);
    if (state.data.state.lastEvent && !msg) setMessage(state.data.state.lastEvent);
  } catch (error) {
    setMessage(error.message, true);
  }
}

async function trade(type, goodId) {
  try {
    state.data = await api(type, { goodId, quantity: 1 });
    rerender();
    setMessage(`${type === 'buy' ? 'Bought' : 'Sold'} ${goodId}.`);
  } catch (error) {
    setMessage(error.message, true);
  }
}

async function buyUpgrade(type) {
  try {
    state.data = await api('buy_upgrade', { type });
    rerender();
    setMessage(`Upgraded ${type}.`);
  } catch (error) {
    setMessage(error.message, true);
  }
}

async function refuel() {
  try {
    state.data = await api('refuel', { amount: parseInt(elements.fuelAmount.value, 10) || 1 });
    rerender();
    setMessage('Refueled ship.');
  } catch (error) {
    setMessage(error.message, true);
  }
}

async function combatTurn(playerAction) {
  try {
    const res = await api('combat_turn', { playerAction });
    if (res.state) {
      state.data.state = res.state;
      await refresh(res.state.lastEvent || 'Combat update.');
    } else {
      await refresh('Combat update.');
    }
  } catch (error) {
    setMessage(error.message, true);
  }
}

async function reset() {
  await api('reset', {});
  await refresh('Save reset.');
}

elements.refuelBtn.onclick = refuel;
elements.resetBtn.onclick = reset;
elements.attackBtn.onclick = () => combatTurn('attack');
elements.braceBtn.onclick = () => combatTurn('brace');
elements.fleeBtn.onclick = () => combatTurn('flee');

bindMapClick();
refresh();
