<?php
declare(strict_types=1);

header('Content-Type: application/json');

const DATA_DIR = __DIR__ . '/data';
const SAVE_FILE = DATA_DIR . '/savegame.json';

function readJson(string $file): array {
    if (!file_exists($file)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function writeJson(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function getRequestData(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}

function clampInt(int $value, int $min, int $max): int {
    return max($min, min($max, $value));
}

function response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function distance(array $from, array $to): int {
    $dx = abs((int) $from['x'] - (int) $to['x']);
    $dy = abs((int) $from['y'] - (int) $to['y']);
    return (int) ceil(sqrt(($dx * $dx) + ($dy * $dy)) / 35);
}

function cargoUnits(array $cargo): int {
    return array_sum(array_map('intval', $cargo));
}

function normalizeState(array $state): array {
    $state['cargo'] = is_array($state['cargo'] ?? null) ? $state['cargo'] : [];
    $state['turn'] = (int) ($state['turn'] ?? 1);
    $state['cash'] = (int) ($state['cash'] ?? 2200);
    $state['fuel'] = (int) ($state['fuel'] ?? 35);
    $state['maxFuel'] = (int) ($state['maxFuel'] ?? 35);
    $state['fuelPrice'] = (int) ($state['fuelPrice'] ?? 9);
    $state['cargoCapacity'] = (int) ($state['cargoCapacity'] ?? 28);
    $state['location'] = (string) ($state['location'] ?? 'terra');
    $state['hull'] = (int) ($state['hull'] ?? 100);
    $state['maxHull'] = (int) ($state['maxHull'] ?? 100);
    $state['weaponLevel'] = (int) ($state['weaponLevel'] ?? 1);
    $state['shieldLevel'] = (int) ($state['shieldLevel'] ?? 1);
    $state['combat'] = is_array($state['combat'] ?? null) ? $state['combat'] : null;
    $state['lastEvent'] = (string) ($state['lastEvent'] ?? '');

    return $state;
}

function marketForPlanet(array $planet, array $goods): array {
    $market = [];
    foreach ($goods as $good) {
        $tech = (int) $planet['tech'];
        $basePrice = (float) $good['basePrice'];
        $volatility = (float) $good['volatility'];
        $produceBias = 1 + (($tech - (int) $good['minTechProduce']) * -0.05);
        $useBias = 1 + (((int) $good['minTechUse'] - $tech) * 0.05);
        $noise = mt_rand(-100, 100) / 100;
        $price = $basePrice * max(0.5, $produceBias) * max(0.55, $useBias) * (1 + ($noise * $volatility));
        $market[$good['id']] = clampInt((int) round($price), 5, 10000);
    }

    return $market;
}

function buildCombat(string $enemyType, int $turn): array {
    $templates = [
        'pirate' => ['name' => 'Pirate Raider', 'hull' => 60, 'attack' => 13, 'shield' => 5, 'reward' => 350],
        'trader' => ['name' => 'Rogue Trader', 'hull' => 75, 'attack' => 10, 'shield' => 7, 'reward' => 420],
        'military' => ['name' => 'Patrol Frigate', 'hull' => 95, 'attack' => 15, 'shield' => 10, 'reward' => 520],
    ];

    $t = $templates[$enemyType];
    $scale = 1 + min(0.9, $turn / 120);

    return [
        'active' => true,
        'enemyType' => $enemyType,
        'enemyName' => $t['name'],
        'enemyHull' => (int) round($t['hull'] * $scale),
        'enemyMaxHull' => (int) round($t['hull'] * $scale),
        'enemyAttack' => (int) round($t['attack'] * $scale),
        'enemyShield' => (int) round($t['shield'] * $scale),
        'reward' => (int) round($t['reward'] * $scale),
        'log' => "Encountered {$t['name']}!",
    ];
}

function reachablePlanets(array $current, array $planets, int $fuel): array {
    $out = [];
    foreach ($planets as $planet) {
        if ($planet['id'] === $current['id']) {
            continue;
        }
        $cost = distance($current, $planet);
        if ($cost <= $fuel) {
            $out[] = ['id' => $planet['id'], 'fuelCost' => $cost];
        }
    }
    return $out;
}

$planets = readJson(DATA_DIR . '/planets.json');
$goods = readJson(DATA_DIR . '/goods.json');
$upgrades = readJson(DATA_DIR . '/upgrades.json');
$defaultState = normalizeState(readJson(DATA_DIR . '/default_state.json'));
$rawState = readJson(SAVE_FILE);
$state = empty($rawState) ? $defaultState : normalizeState($rawState);

$planetsById = [];
foreach ($planets as $planet) {
    $planetsById[$planet['id']] = $planet;
}

$goodsById = [];
foreach ($goods as $good) {
    $goodsById[$good['id']] = $good;
}

$action = $_GET['action'] ?? 'status';
$data = getRequestData();

if ($action === 'reset') {
    $state = $defaultState;
    writeJson(SAVE_FILE, $state);
}

if (!isset($planetsById[$state['location']])) {
    $state['location'] = $defaultState['location'];
}
$currentPlanet = $planetsById[$state['location']];
$market = marketForPlanet($currentPlanet, $goods);

if (in_array($action, ['buy', 'sell', 'travel', 'refuel', 'buy_upgrade'], true) && is_array($state['combat'])) {
    response(['error' => 'Combat is active. Resolve combat before other actions.'], 400);
}

if ($action === 'buy') {
    $goodId = (string) ($data['goodId'] ?? '');
    $qty = max(1, (int) ($data['quantity'] ?? 1));
    if (!isset($goodsById[$goodId])) {
        response(['error' => 'Unknown good.'], 400);
    }
    $unitPrice = $market[$goodId];
    $cost = $unitPrice * $qty;
    $remainingCapacity = $state['cargoCapacity'] - cargoUnits($state['cargo']);
    if ($remainingCapacity < $qty) {
        response(['error' => 'Not enough cargo space.'], 400);
    }
    if ($state['cash'] < $cost) {
        response(['error' => 'Not enough cash.'], 400);
    }
    $state['cash'] -= $cost;
    $state['cargo'][$goodId] = (int) ($state['cargo'][$goodId] ?? 0) + $qty;
}

if ($action === 'sell') {
    $goodId = (string) ($data['goodId'] ?? '');
    $qty = max(1, (int) ($data['quantity'] ?? 1));
    if (!isset($goodsById[$goodId])) {
        response(['error' => 'Unknown good.'], 400);
    }
    $owned = (int) ($state['cargo'][$goodId] ?? 0);
    if ($owned < $qty) {
        response(['error' => 'Not enough cargo to sell.'], 400);
    }
    $unitPrice = $market[$goodId];
    $state['cargo'][$goodId] = $owned - $qty;
    if ($state['cargo'][$goodId] <= 0) {
        unset($state['cargo'][$goodId]);
    }
    $state['cash'] += $unitPrice * $qty;
}

if ($action === 'buy_upgrade') {
    $type = (string) ($data['type'] ?? '');
    if (!in_array($type, ['weapons', 'shields'], true)) {
        response(['error' => 'Unknown upgrade type.'], 400);
    }

    $stateKey = $type === 'weapons' ? 'weaponLevel' : 'shieldLevel';
    $nextLevel = $state[$stateKey] + 1;
    $next = null;
    foreach ($upgrades[$type] as $item) {
        if ((int) $item['level'] === $nextLevel) {
            $next = $item;
        }
    }

    if ($next === null) {
        response(['error' => 'Already at maximum level.'], 400);
    }
    if ($state['cash'] < (int) $next['price']) {
        response(['error' => 'Not enough cash for upgrade.'], 400);
    }

    $state['cash'] -= (int) $next['price'];
    $state[$stateKey] = $nextLevel;
    $state['lastEvent'] = 'Installed ' . $next['name'] . '.';
}

if ($action === 'refuel') {
    $amount = max(1, (int) ($data['amount'] ?? 1));
    $space = $state['maxFuel'] - $state['fuel'];
    if ($space <= 0) {
        response(['error' => 'Fuel tank already full.'], 400);
    }
    $amount = min($amount, $space);
    $cost = $amount * $state['fuelPrice'];
    if ($state['cash'] < $cost) {
        response(['error' => 'Not enough cash to refuel.'], 400);
    }
    $state['cash'] -= $cost;
    $state['fuel'] += $amount;
}

if ($action === 'travel') {
    $destination = (string) ($data['destination'] ?? '');
    if (!isset($planetsById[$destination])) {
        response(['error' => 'Unknown destination.'], 400);
    }
    if ($destination === $state['location']) {
        response(['error' => 'Already at destination.'], 400);
    }

    $target = $planetsById[$destination];
    $requiredFuel = distance($currentPlanet, $target);
    if ($state['fuel'] < $requiredFuel) {
        response(['error' => 'Insufficient fuel for jump.'], 400);
    }

    $state['fuel'] -= $requiredFuel;
    $state['turn'] += 1;
    $state['location'] = $destination;
    $currentPlanet = $target;
    $market = marketForPlanet($currentPlanet, $goods);
    $state['lastEvent'] = "Arrived at {$target['name']}.";

    if (mt_rand(1, 100) <= 35) {
        $types = ['pirate', 'trader', 'military'];
        $state['combat'] = buildCombat($types[array_rand($types)], $state['turn']);
        $state['lastEvent'] = $state['combat']['log'];
    }
}

if ($action === 'combat_turn') {
    if (!is_array($state['combat'])) {
        response(['error' => 'No active combat.'], 400);
    }

    $playerAction = (string) ($data['playerAction'] ?? 'attack');
    $weapon = $upgrades['weapons'][$state['weaponLevel'] - 1];
    $shield = $upgrades['shields'][$state['shieldLevel'] - 1];

    if ($playerAction === 'flee') {
        if (mt_rand(1, 100) <= 45) {
            $state['combat'] = null;
            $state['lastEvent'] = 'You escaped the engagement.';
            writeJson(SAVE_FILE, $state);
            response(['state' => $state, 'escaped' => true]);
        }
        $state['combat']['log'] = 'Escape attempt failed.';
    } else {
        $playerDamage = clampInt((int) round(($weapon['attack'] + mt_rand(1, 6)) - $state['combat']['enemyShield'] * 0.5), 3, 40);
        if ($playerAction === 'brace') {
            $playerDamage = (int) floor($playerDamage * 0.75);
            $state['combat']['log'] = 'You brace and fire cautiously.';
        } else {
            $state['combat']['log'] = 'Direct hit on enemy hull!';
        }
        $state['combat']['enemyHull'] -= $playerDamage;
    }

    if ($state['combat']['enemyHull'] <= 0) {
        $reward = (int) $state['combat']['reward'];
        $state['cash'] += $reward;
        $state['combat'] = null;
        $state['lastEvent'] = "Enemy destroyed. Salvaged {$reward} credits.";
        writeJson(SAVE_FILE, $state);
        response(['state' => $state, 'victory' => true]);
    }

    $enemyDamage = clampInt((int) round($state['combat']['enemyAttack'] + mt_rand(0, 5) - $shield['defense'] * 0.7), 2, 35);
    if ($playerAction === 'brace') {
        $enemyDamage = (int) floor($enemyDamage * 0.6);
    }

    $state['hull'] -= $enemyDamage;
    $state['combat']['log'] .= " Enemy hits you for {$enemyDamage}.";

    if ($state['hull'] <= 0) {
        $state['hull'] = 0;
        $penalty = min(900, (int) floor($state['cash'] * 0.35));
        $state['cash'] -= $penalty;
        $state['hull'] = (int) floor($state['maxHull'] * 0.6);
        $state['combat'] = null;
        $state['lastEvent'] = "You were disabled and towed. Repair fees: {$penalty} credits.";
    }
}

writeJson(SAVE_FILE, $state);

$currentPlanet = $planetsById[$state['location']];
$reachable = reachablePlanets($currentPlanet, $planets, $state['fuel']);

response([
    'state' => $state,
    'currentPlanet' => $currentPlanet,
    'planets' => $planets,
    'goods' => $goods,
    'upgrades' => $upgrades,
    'market' => $market,
    'cargoUsed' => cargoUnits($state['cargo']),
    'reachablePlanets' => $reachable,
]);
