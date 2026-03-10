# Void Merchant — HTML5 Canvas Game Specification Document

**Version:** 1.0  
**Date:** 2026-03-09  
**Platform Target:** Web Browser (HTML5 / Canvas)  
**Genre:** Space Trading & Combat Simulation  
**Document Type:** Full Game Specification

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [Game Architecture](#3-game-architecture)
4. [Canvas & Rendering System](#4-canvas--rendering-system)
5. [Game State & Data Model](#5-game-state--data-model)
6. [Universe & Solar Systems](#6-universe--solar-systems)
7. [Planets & Ports](#7-planets--ports)
8. [Trade Goods & Economy](#8-trade-goods--economy)
9. [Ships & Ship Upgrades](#9-ships--ship-upgrades)
10. [Equipment](#10-equipment)
11. [Combat System](#11-combat-system)
12. [Player Character & Skills](#12-player-character--skills)
13. [NPC & Encounter System](#13-npc--encounter-system)
14. [Colonist Transport System](#14-colonist-transport-system)
15. [Travel & Navigation](#15-travel--navigation)
16. [Wormholes](#16-wormholes)
17. [Banking & Finance](#17-banking--finance)
18. [Special Events & Quests](#18-special-events--quests)
19. [Police, Bounty & Reputation](#19-police-bounty--reputation)
20. [Difficulty Settings](#20-difficulty-settings)
21. [UI Screens & Panels](#21-ui-screens--panels)
22. [Input Handling](#22-input-handling)
23. [Sound & Music](#23-sound--music)
24. [Save & Load System](#24-save--load-system)
25. [Settings & Options](#25-settings--options)
26. [Scoring & Win/Loss Conditions](#26-scoring--winloss-conditions)
27. [Localisation](#27-localisation)
28. [File & Folder Structure](#28-file--folder-structure)
29. [Constants Reference](#29-constants-reference)
30. [Known Limitations & Future Work](#30-known-limitations--future-work)

---

## 1. Project Overview

### 1.1 Summary

Void Merchant is a single-player space trading and combat simulation. The player begins with a small amount of credits and a basic ship, then trades goods between solar systems to accumulate wealth. Combat, piracy, police encounters, random events, and special quests add depth and tension to each journey. The ultimate goal is to earn enough money to retire in luxury (the "Retirement" win condition) or to complete specific quest lines.

### 1.2 Core Pillars

| Pillar | Description |
|---|---|
| **Trade** | Buy low, sell high across a procedurally arranged universe of solar systems |
| **Travel** | Navigate a star map, spending fuel and managing ship resources |
| **Combat** | Turn-based/round combat with pirates, police, and traders |
| **Progression** | Upgrade ships, equipment, and character skills over time |
| **Risk vs. Reward** | Carry contraband or take dangerous routes for higher profit |

### 1.3 Scope of Conversion

The HTML5 version implements all game mechanics with the following additions:

- Rendered entirely on an HTML5 `<canvas>` element (no DOM-driven game UI panels)
- Mouse, touch, and keyboard input
- Responsive scaling to common browser window sizes
- LocalStorage-based save system
- Modular ES6+ JavaScript codebase

---

## 2. Technology Stack

### 2.1 Core Technologies

| Layer | Technology |
|---|---|
| Markup | HTML5 (single `index.html`) |
| Styling | CSS3 (minimal; primarily for page chrome and canvas centering) |
| Rendering | HTML5 Canvas 2D API |
| Logic | Vanilla JavaScript (ES6 modules, no frameworks) |
| Storage | `localStorage` (JSON serialisation) |
| Audio | Web Audio API + `<audio>` elements |
| Font Rendering | Canvas `fillText` with embedded/web-safe fonts |

### 2.2 No External Dependencies (Default)

The game is designed to run with zero CDN dependencies. All assets (sprites, fonts, audio) are either procedurally drawn or bundled. An optional build step may be added later to bundle assets.

### 2.3 Browser Support

| Browser | Min Version |
|---|---|
| Chrome / Edge | 90+ |
| Firefox | 88+ |
| Safari | 14+ |
| Mobile Chrome (Android) | 90+ |
| Mobile Safari (iOS) | 14+ |

---

## 3. Game Architecture

### 3.1 Module Breakdown

```
main.js               — Entry point; bootstraps engine, loads save, starts loop
engine/
  GameLoop.js         — requestAnimationFrame loop, delta time, FPS cap
  Renderer.js         — Canvas context wrapper, layer management
  InputManager.js     — Mouse, touch, keyboard unification
  AudioManager.js     — Sound effects and music playback
  EventBus.js         — Simple pub/sub event system
  SaveManager.js      — Serialise/deserialise to localStorage
  SceneManager.js     — Screen/scene switching (menu, travel, combat, etc.)
game/
  Universe.js         — Galaxy generation, solar systems, wormholes
  SolarSystem.js      — Planet data, tech level, govt, economy
  Planet.js           — Port inventory, prices, services
  Player.js           — Credits, skills, ship reference, police record
  Ship.js             — Hull, cargo, weapons, shields, gadgets
  TradeEngine.js      — Buy/sell logic, price calculation
  CombatEngine.js     — Round resolution, flee mechanics
  EncounterEngine.js  — NPC spawning, encounter type selection
  QuestManager.js     — Special cargo, reactor quest, moon quest, etc.
  BankManager.js      — Loans, insurance, net worth calculation
  NewsEngine.js       — Headline generation per solar system
  ReputationManager.js— Police record, trader rep, pirate rep
ui/
  screens/            — One file per game screen (see Section 20)
  components/
    Button.js         — Reusable canvas button
    Panel.js          — Bordered panel with title bar
    ListView.js       — Scrollable list of items
    StarMap.js        — Interactive galaxy map
    ProgressBar.js    — HP / fuel / cargo bars
    Tooltip.js        — Hover info boxes
    Modal.js          — Confirmation / alert dialogs
    TextScroller.js   — Animated news ticker
data/
  ships.js            — Ship type definitions
  equipment.js        — Weapon, shield, gadget definitions
  goods.js            — Trade good definitions
  events.js           — Random event table
  quests.js           — Quest definitions
  names.js            — Procedural name tables
  techLevels.js       — Tech level production/consumption tables
constants.js          — All magic numbers centralised
utils/
  Random.js           — Seeded PRNG (Mulberry32)
  Math2D.js           — Distance, angle helpers
  Format.js           — Credit formatting, pluralisation
  Colours.js          — Colour palette constants
```

### 3.2 Game Loop

```
GameLoop.start()
  └─ requestAnimationFrame(tick)
       ├─ deltaTime = now - lastFrame
       ├─ InputManager.flush()       // process queued input events
       ├─ SceneManager.update(dt)    // update active scene logic
       ├─ Renderer.clear()
       ├─ SceneManager.render(ctx)   // draw active scene
       └─ Renderer.present()        // (noop on 2D canvas, for future WebGL swap)
```

### 3.3 Scene Stack

Scenes are pushed/popped on a stack. Rendering walks the stack bottom-up; input is consumed top-down.

| Scene ID | Description |
|---|---|
| `MAIN_MENU` | Title screen |
| `NEW_GAME` | Character creation, difficulty |
| `SOLAR_SYSTEM` | Planet orbit view, port actions |
| `STAR_MAP` | Galaxy navigation |
| `WARP` | Warp animation / travel resolution |
| `ENCOUNTER` | NPC encounter screen |
| `COMBAT` | Combat rounds |
| `MARKET` | Buy/sell goods |
| `SHIPYARD` | Buy ships |
| `EQUIPMENT` | Buy/sell weapons, shields, gadgets |
| `SHIP_UPGRADES` | Install/remove ship upgrades |
| `PERSONNEL` | Hire crew |
| `BANK` | Loans |
| `COLONIAL_REGISTRY` | Colonist transport contracts |
| `COMMANDER` | Player status screen |
| `CARGO` | Cargo manifest |
| `SETTINGS` | Options |
| `SAVE_LOAD` | Save / load browser slots and file import/export |
| `HIGH_SCORE` | Score / retirement screen |
| `QUIT_CONFIRM` | Quit dialog |

---

## 4. Canvas & Rendering System

### 4.1 Canvas Setup

```html
<!-- index.html -->
<canvas id="gameCanvas"></canvas>
```

```javascript
// Renderer.js
const DESIGN_W = 480;   // logical width in px
const DESIGN_H = 800;   // logical height in px (portrait, mobile-first)

// On resize: scale canvas to fill window while preserving aspect ratio
// devicePixelRatio scaling applied for HiDPI displays
```

### 4.2 Coordinate System

- Origin at top-left `(0, 0)`
- Logical resolution: **480 × 800** (portrait orientation)
- All game coordinates expressed in logical pixels
- `Renderer.scale(factor)` applied at draw time based on window size
- Landscape orientation supported by rotating canvas 90° in CSS and swapping W/H (optional toggle in settings)

### 4.3 Layer Order (draw order per frame)

| Layer | Contents |
|---|---|
| 0 — Background | Space background, star field, nebula gradients |
| 1 — World | Star map, planet orbit rings |
| 2 — Entities | Ships, planets, projectiles |
| 3 — UI Panels | Semi-transparent panels |
| 4 — UI Controls | Buttons, lists, inputs |
| 5 — Overlays | Modals, tooltips, notifications |
| 6 — Debug | FPS counter, hitbox visualiser (dev mode only) |

### 4.4 Font Rendering

| Use | Font | Size |
|---|---|---|
| Body text | `monospace` (fallback) or bundled bitmap font | 13px |
| Headings | Same, bold | 16px |
| Credits / numbers | Same | 14px |
| News ticker | Same | 12px |
| Title screen | Larger, spaced | 28px |

### 4.5 Colour Palette

See `utils/Colours.js`. Key values:

| Token | Hex | Use |
|---|---|---|
| `BG_DARK` | `#0a0a14` | Space background |
| `PANEL_BG` | `#111827cc` | Semi-transparent panel fill |
| `PANEL_BORDER` | `#3b82f6` | Panel border, accent |
| `TEXT_PRIMARY` | `#e5e7eb` | Main text |
| `TEXT_DIM` | `#6b7280` | Secondary text |
| `TEXT_WARN` | `#f59e0b` | Warnings, prices |
| `TEXT_DANGER` | `#ef4444` | Danger, police red |
| `TEXT_OK` | `#10b981` | Success, credits gained |
| `BTN_PRIMARY` | `#1d4ed8` | Primary button fill |
| `BTN_HOVER` | `#2563eb` | Button hover fill |
| `BTN_DISABLED` | `#374151` | Disabled button fill |

---

## 5. Game State & Data Model

### 5.1 Top-Level State Object

```javascript
GameState = {
  version: "1.0",
  seed: Number,           // universe seed — all static system data is regenerated from this
  day: Number,            // game day (increments on warp)
  difficulty: String,     // "beginner" | "easy" | "normal" | "hard" | "impossible"
  player: PlayerData,
  universeState: UniverseState,   // only mutable per-system state; NOT the full generated data
  wormholes: [WormholePair],      // fixed at generation; saved since they never change per seed
  currentSystemIndex: Number,
  currentPlanetIndex: Number,
  quests: QuestData,
  news: NewsData,
  settings: SettingsData,
}
```

### 5.2 PlayerData

```javascript
PlayerData = {
  name: String,
  credits: Number,
  debt: Number,
  insurancePremium: Number,
  noClaim: Number,            // days without insurance claim
  ship: ShipInstance,
  skills: {
    pilot: Number,            // 1–10
    fighter: Number,          // 1–10
    trader: Number,           // 1–10
    engineer: Number,         // 1–10
  },
  policeRecord: Number,       // negative = criminal, positive = hero
  reputationScore: Number,    // affects NPC disposition
  kills: {
    pirate: Number,
    trader: Number,
    police: Number,
  },
  crew: [CrewMember],         // hired NPCs
  cargo: [CargoSlot],         // actual cargo hold contents
  // escapePod is tracked on ShipInstance, not PlayerData
  moonBought: Boolean,
}
```

### 5.3 ShipInstance

```javascript
ShipInstance = {
  typeId: String,             // references ships.js
  hullStrength: Number,       // current HP
  fuelTanks: Number,          // current fuel units
  weapons: [EquipSlot],       // up to ship.weaponSlots
  shields: [EquipSlot],       // up to ship.shieldSlots
  gadgets: [EquipSlot],       // up to ship.gadgetSlots
  crew: [CrewSlot],           // up to ship.crewSlots (references player.crew)
  tribbles: Number,           // 0 = none, else count
  escapePod: Boolean,         // Escape Pod installed (see Section 10.5)
  upgrades: {
    lifeSupport:    Boolean,  // Cargo Life Support installed (enables colonist transport)
    reinforcedHull: Boolean,
    extendedTanks:  Boolean,
    cargoExpansion: Boolean,
  },
}
```

### 5.4 Universe — Static vs. Mutable Data

The universe is split into two categories. Only mutable state is saved; static data is always regenerated from the seed at load time.

#### Static Data — Regenerated from `seed` (never saved)

```javascript
// Produced by Universe.generate(seed) on new game AND on every load
SolarSystemStatic = {
  index: Number,
  name: String,               // from names.js table, position assigned by PRNG
  x: Number,                  // grid position, derived from seed
  y: Number,
  size: Enum,                 // TINY | SMALL | MEDIUM | LARGE | HUGE
  techLevel: Number,          // 0–7
  government: Enum,
  politicalActivity: Number,
  resources: Enum,
  police: Number,             // 0–7 base presence
  pirates: Number,            // 0–7 base density
  traders: Number,            // 0–7 base density
}
```

#### Mutable Data — Saved in `GameState.universeState`

```javascript
// Only what changes during a playthrough
UniverseState = {
  systemStates: [SystemMutableState],  // one entry per system index
}

SystemMutableState = {
  index: Number,
  visited: Boolean,
  condition: Enum,             // current event condition (WAR, PLAGUE, etc.) — can change
  portInventory: [PortEntry],  // prices and quantities — regenerated on each visit, then saved
  specialEventUsed: Boolean,   // one-time event consumed flag
  colonistContracts: [ColonistGroup],  // active contracts at this port
}
```

#### Wormholes — Saved Separately

```javascript
// Generated once from seed, fixed for the life of the game, saved explicitly
// Saved because: small, fixed, and trivially cheap to store
WormholePair = {
  systemA: Number,   // system index
  systemB: Number,   // system index
}
```

#### Load Sequence

```
1. Read seed from GameState
2. Universe.generate(seed) → rebuilds full SolarSystemStatic[] in memory
3. Merge GameState.universeState on top:
   - systemStates[i].visited, condition, portInventory, specialEventUsed applied
4. Wormholes loaded directly from GameState.wormholes
5. Game ready — player's map matches original exactly
```

---

## 6. Universe & Solar Systems

### 6.1 Galaxy Generation

The universe is fully procedural, generated deterministically from a single integer `seed` stored in `GameState`. Given the same seed, `Universe.generate(seed)` always produces an identical galaxy. This means **only the seed needs to be saved** — all static system properties are reconstructed at load time.

- The universe contains **GALAXY_SIZE = 71** named solar systems
- System positions are generated using a seeded PRNG (Mulberry32) spread across a **150 × 110** unit grid
- A minimum distance of **MIN_SYSTEM_DIST = 6** units is enforced between any two systems
- **CLOSE_DIST = 20** units: systems within this range are considered "nearby"
- System names are drawn from a fixed 71-entry table in `names.js`, assigned to positions by the PRNG — so names shuffle per seed, but the table itself never changes
- Wormhole pairs are generated from the seed at new game creation, then **saved explicitly** in `GameState.wormholes` (they are fixed for the lifetime of a playthrough and trivially small to store)
- Port inventories are **not** part of static generation — they are calculated fresh on each player visit and then saved in `SystemMutableState.portInventory` until the next visit

### 6.2 SolarSystemData (Full In-Memory Object)

The following is the complete in-memory representation used at runtime, combining static (regenerated) and mutable (loaded from save) data. Only the fields marked **[MUTABLE — SAVED]** are persisted; the rest are always regenerated from the seed.

```javascript
SolarSystemData = {
  // --- STATIC (regenerated from seed — never saved) ---
  index: Number,
  name: String,               // from names.js table (71 fixed names, positions assigned by PRNG)
  x: Number,                  // grid position
  y: Number,
  size: Enum,                 // TINY | SMALL | MEDIUM | LARGE | HUGE
  techLevel: Number,          // 0 (pre-agricultural) – 7 (hi-tech)
  government: Enum,           // see Section 6.4
  politicalActivity: Number,  // 0–7 (affects police presence)
  resources: Enum,            // see Section 6.5
  police: Number,             // 0–7 base police presence
  pirates: Number,            // 0–7 base pirate density
  traders: Number,            // 0–7 base trader density

  // --- MUTABLE (saved in GameState.universeState.systemStates[i]) ---
  visited: Boolean,           // [MUTABLE — SAVED]
  condition: Enum,            // [MUTABLE — SAVED] WAR | PLAGUE | DROUGHT | COLD | CROP_FAILURE | BOREDOM | NONE
  portInventory: [PortEntry], // [MUTABLE — SAVED] regenerated on each visit, then cached
  specialEventUsed: Boolean,  // [MUTABLE — SAVED] one-time event consumed flag
  colonistContracts: [ColonistGroup], // [MUTABLE — SAVED] active Registry contracts
}
```

### 6.3 Tech Levels

| Level | Name | Description |
|---|---|---|
| 0 | Pre-Agricultural | Primitive; no industry |
| 1 | Agricultural | Basic farming; no manufacturing |
| 2 | Medieval | Early industry |
| 3 | Renaissance | Trade economy |
| 4 | Early Industrial | Basic manufacturing |
| 5 | Industrial | Full manufacturing |
| 6 | Post-Industrial | Advanced goods |
| 7 | Hi-Tech | All goods available |

Tech level governs which goods are produced, which are consumed, and at what base price.

### 6.4 Government Types

| Enum | Name | Police | Pirates | Taxes |
|---|---|---|---|---|
| 0 | Anarchy | 0 | 7 | None |
| 1 | Feudal | 1 | 6 | Low |
| 2 | Multi-Gov | 2 | 4 | Low |
| 3 | Dictatorship | 3 | 3 | Medium |
| 4 | Communist | 6 | 2 | High |
| 5 | Confederacy | 5 | 3 | Medium |
| 6 | Democracy | 4 | 2 | Medium |
| 7 | Corporate State | 7 | 1 | High |

### 6.5 Resource Types

| Enum | Name | Effect |
|---|---|---|
| 0 | No Special Resources | — |
| 1 | Mineral Rich | Ore/minerals cheaper |
| 2 | Mineral Poor | Ore/minerals expensive |
| 3 | Desert | Water expensive |
| 4 | Sweetwater Oceans | Water cheap |
| 5 | Rich Soil | Food cheap |
| 6 | Poor Soil | Food expensive |
| 7 | Rich Fauna | Animal hides cheap |
| 8 | Lifeless | Animal hides expensive |
| 9 | Weird Mushrooms | Stims cheap |
| 10 | Special Herbs | Stims very cheap |
| 11 | Artistic | Holoware cheap |
| 12 | Techsavvy | Machinery cheap |
| 13 | Mercenary Enclave | Weapons cheap |

---

## 7. Planets & Ports

### 7.1 Planet Services (available depending on tech level)

| Service | Min Tech Level | Description |
|---|---|---|
| Fuel | 0 | Refuel ship |
| Repair | 2 | Hull repair |
| Marketplace | 1 | Trade goods |
| Shipyard | 4 | Buy new ships; install ship upgrades |
| Equipment Dock | 3 | Buy weapons/shields/gadgets |
| Personnel Office | 2 | Hire mercenaries |
| Bank | 4 | Loans |
| Colonial Registry | 3 | Accept colonist transport contracts (Medium+ systems only) |

### 7.2 Port Inventory

Each port maintains a per-good entry:

```javascript
PortEntry = {
  goodId: String,
  quantity: Number,    // units available (0 = not stocked)
  buyPrice: Number,    // credits per unit (0 = not available to buy)
  sellPrice: Number,   // credits per unit (0 = port will not buy)
}
```

Prices are calculated on arrival:

```
basePrice   = good.basePrice
techMod     = good.techPriceIncrease * (techLevel - good.minTechProduce)
resourceMod = resourceModifier(system.resources, good)
conditionMod= conditionModifier(system.condition, good)
random      = PRNG.range(-good.variance, good.variance)
finalPrice  = basePrice + techMod + resourceMod + conditionMod + random
```

### 7.3 Fuel

- Fuel is sold per unit (1 unit = 1 parsec of travel capacity)
- Base fuel price: **2 credits/unit** (modified by system)
- Engineer skill reduces fuel cost: `cost = base * (1 - engineer * 0.03)`
- Ships have different max fuel tank sizes (see Section 9)

### 7.4 Repairs

- Repair cost: **1 credit per 1 HP** repaired (base)
- Engineer skill reduces cost: `cost = base * (1 - engineer * 0.025)`
- Repairs only available at planets with tech level ≥ 2

---

## 8. Trade Goods & Economy

### 8.1 Trade Good Definitions

All 12 standard goods; contraband noted:

| ID | Name | Min Tech Prod | Min Tech Use | Base Price | Variance | Legal |
|---|---|---|---|---|---|---|
| `water` | Water | 0 | 0 | 30 | 4 | Yes |
| `pelts` | Pelts | 0 | 0 | 250 | 10 | Yes |
| `rations` | Rations | 1 | 0 | 100 | 5 | Yes |
| `ore` | Ore | 2 | 2 | 350 | 20 | Yes |
| `holoware` | Holoware | 3 | 1 | 250 | 10 | Yes |
| `munitions` | Munitions | 3 | 1 | 1250 | 75 | **No** |
| `medpacks` | Medpacks | 4 | 1 | 650 | 20 | Yes |
| `machinery` | Machinery | 4 | 3 | 900 | 30 | Yes |
| `stims` | Stims | 5 | 0 | 3500 | 150 | **No** |
| `xenocultures` | Xenocultures | 5 | 3 | 1800 | 80 | Yes |
| `neuralware` | Neuralware | 6 | 3 | 2400 | 90 | Yes |
| `synthminds` | Synthminds | 6 | 4 | 5000 | 100 | Yes |

**Xenocultures** — Engineered biological samples and alien cultivars; produced at Post-Industrial worlds with unusual biomes, consumed by research-oriented Industrial and Hi-Tech systems. Condition PLAGUE increases demand significantly.

**Neuralware** — Cybernetic neural interface components and cognitive augmentation hardware; produced only at Post-Industrial and Hi-Tech worlds, consumed across Industrial and above. Techsavvy resource reduces price.

### 8.2 Cargo Mechanics

- Cargo is stored in the `player.cargo` array, each slot:
  ```javascript
  CargoSlot = { goodId: String, quantity: Number, avgCostPerUnit: Number }
  ```
- `avgCostPerUnit` tracks buy price for profit calculation
- Cargo bays are measured in "units" (1 good = 1 unit)
- Equipment also consumes cargo space if removed from ship

### 8.3 Price Influencers Summary

| Factor | Effect |
|---|---|
| Tech level | Higher tech = some goods cheaper, some unavailable |
| Government | Affects legal goods availability and police tolerance |
| Resources | Reduces/increases specific good prices |
| Condition | E.g., WAR increases munitions price |
| Trader skill | Reduces buy price, increases sell price slightly |
| Day/market fluctuation | Small random variance each visit |

### 8.4 Illegal Goods

- **Stims** are universally contraband in all systems
- **Munitions** are universally contraband in all systems
- Carrying contraband increases police aggression proportionally to quantity carried
- If police scanner detects contraband → `POLICE_ENCOUNTER` branch (surrender/bribe/fight)
- Jettisoning cargo overboard is possible to avoid police confiscation
- Contraband goods yield significantly higher profit to compensate for legal risk

---

## 9. Ships & Ship Upgrades

### 9.1 Ship Type Definitions (`data/ships.js`)

| ID | Name | Cargo Bays | Weapon Slots | Shield Slots | Gadget Slots | Crew Slots | Fuel Tanks | Hull | Base Price |
|---|---|---|---|---|---|---|---|---|---|
| `drifter` | Drifter | 10 | 0 | 0 | 0 | 1 | 14 | 25 | 2,000 |
| `courier` | Courier | 15 | 1 | 0 | 2 | 1 | 14 | 100 | 10,000 |
| `runner` | Runner | 20 | 1 | 1 | 2 | 1 | 17 | 100 | 25,000 |
| `interceptor` | Interceptor | 15 | 2 | 1 | 2 | 1 | 13 | 100 | 30,000 |
| `hauler` | Hauler | 25 | 1 | 2 | 4 | 2 | 15 | 100 | 60,000 |
| `freighter` | Freighter | 50 | 0 | 1 | 2 | 3 | 14 | 50 | 80,000 |
| `corvette` | Corvette | 20 | 3 | 2 | 2 | 2 | 16 | 150 | 100,000 |
| `surveyor` | Surveyor | 30 | 2 | 2 | 6 | 3 | 15 | 150 | 150,000 |
| `bulk carrier` | Bulk Carrier | 60 | 1 | 3 | 4 | 3 | 13 | 200 | 225,000 |
| `marauder` | Marauder | 35 | 3 | 2 | 4 | 3 | 14 | 200 | 300,000 |

> **Note:** The Drifter has no gadget slots by design — it is a bare-bones escape vessel with no internal bay space for additional hardware. All other ships carry a minimum of 2 gadget slots.

### 9.2 Ship Purchase Rules

- Player's current ship is traded in at a depreciated value
- Trade-in value: `originalPrice * (currentHull / maxHull) * SHIP_TRADE_IN_RATE`
- `SHIP_TRADE_IN_RATE = 0.90` (90% of condition-adjusted price)
- Weapons/shields/gadgets on old ship are transferred to cargo or new ship if slots allow
- Tribbles (if present) transfer to new ship

### 9.3 Ship Condition

- `hullStrength / maxHull` is expressed as a percentage
- Display: coloured bar (green ≥ 60%, yellow ≥ 30%, red < 30%)
- Ships below 20% hull have increased chance of sub-light drive failure during warp

### 9.4 Ship Upgrades

Ship Upgrades are permanent structural modifications installed at a Shipyard (min tech level 4). Unlike gadgets, upgrades do **not** occupy a gadget slot — they are bolted-in changes to the ship's physical configuration. Upgrades persist through ship trade-ins if the new ship supports the same upgrade type (otherwise the upgrade is refunded at 50% of install cost).

Only one upgrade of each type may be installed at a time.

| ID | Name | Effect | Min Tech | Install Cost | Min Ship Class |
|---|---|---|---|---|---|
| `life_support` | Cargo Life Support | Enables Colonist Transport; reserves bays for life support hardware | 4 | 15,000 | Courier |
| `reinforced_hull` | Reinforced Hull Plating | +25% max hull HP | 5 | 40,000 | Runner |
| `extended_tanks` | Extended Fuel Tanks | +4 max fuel units | 3 | 8,000 | Drifter |
| `cargo_expansion` | Cargo Bay Expansion | +10 cargo bays | 3 | 12,000 | Hauler |

#### 9.4.1 Cargo Life Support (Upgrade Detail)

This upgrade is the prerequisite for the Colonist Transport system (see Section 14). It represents the installation of atmospheric recyclers, bunks, sanitation, and emergency bulkheads inside the cargo hold.

- **Install cost:** 15,000 credits (flat, at any qualifying Shipyard)
- **Bay overhead:** The life support hardware permanently occupies **5 cargo bays** while installed, reducing effective cargo capacity
- **Removal:** Can be uninstalled at a Shipyard for a 5,000 credit labour fee; bays are restored
- **Transfer on trade-in:** Transfers to any ship with `cargoUpgradeSlot = true` (Courier and above); does **not** transfer to the Drifter
- Displayed as a dedicated indicator on the Commander and Cargo screens: `[LIFE SUPPORT: INSTALLED]`

```javascript
ShipInstance = {
  ...
  upgrades: {
    lifeSupport:      Boolean,   // Cargo Life Support installed
    reinforcedHull:   Boolean,
    extendedTanks:    Boolean,
    cargoExpansion:   Boolean,
  },
}
```

---

## 10. Equipment

### 10.1 Weapons (`data/equipment.js`)

| ID | Name | Power | Min Tech | Price |
|---|---|---|---|---|
| `pulse_laser` | Pulse Laser | 15 | 3 | 2,000 |
| `beam_laser` | Beam Laser | 25 | 4 | 12,500 |
| `military_laser` | Military Laser | 35 | 5 | 35,000 |
| `void_cannon` | Void Cannon | 85 | 6 | Special (quest) |
| `plasma_burst` | Plasma Burst | 20 | 4 | 15,000 |

### 10.2 Shields

| ID | Name | Power | Min Tech | Price |
|---|---|---|---|---|
| `energy_shield` | Energy Shield | 100 | 4 | 5,000 |
| `reflective_shield` | Reflective Shield | 200 | 6 | 20,000 |

### 10.3 Gadgets

Gadgets are sorted by minimum tech level. A ship may equip up to its available gadget slots; all gadgets occupy one slot each.

| ID | Name | Effect | Min Tech | Price |
|---|---|---|---|---|
| `fuel_recycler` | Fuel Recycler | Reduces fuel consumption per jump by 10% | 3 | 1,800 |
| `life_support_vents` | Life Support Vents | Reduces the daily upkeep cost of hiring Mercenaries | 3 | 2,200 |
| `trade_scanner` | Trade Scanner | Allows viewing of local commodity prices without landing | 3 | 3,000 |
| `modular_hold` | Modular Hold | +5 cargo capacity | 4 | 2,500 |
| `reinforced_struts` | Reinforced Struts | Increases the ship's maximum hull integrity by 5% | 4 | 4,500 |
| `signal_jammer` | Signal Jammer | Grants a 20% chance to bypass cargo scans from Police | 4 | 5,000 |
| `solar_collector` | Solar Collector | Provides a 5% chance to regain 1 unit of fuel after each jump | 4 | 6,500 |
| `nano_patch_kit` | Nano-Patch Kit | Automatically repairs 1 point of hull damage every few turns during combat | 5 | 7,500 |
| `bounty_database` | Bounty Database | Increases the credit rewards earned for destroying Pirate ships | 5 | 8,000 |
| `shock_dampeners` | Shock Dampeners | Reduces damage taken when travelling through Nebula hazards | 5 | 9,500 |
| `emergency_thrusters` | Emergency Thrusters | Grants a 100% flee success rate once per journey | 5 | 11,000 |
| `shield_capacitor` | Shield Capacitor | Causes shields to regenerate 15% faster between combat rounds | 6 | 14,000 |
| `astro_nav_core` | Astro-Nav Core | Provides a +1 bonus to your Pilot skill | 6 | 15,000 |
| `ballistics_suite` | Ballistics Suite | Provides a +1 bonus to your Fighter skill | 6 | 15,000 |
| `market_predictor` | Market Predictor | Highlights the most profitable trade routes on the star map | 6 | 18,500 |
| `photon_overclocker` | Photon Overclocker | Increases the damage dealt by your lasers, but risks damaging them | 7 | 20,000 |
| `mining_drill` | Mining Drill | Enables the ability to harvest ore units from asteroid belts | 7 | 22,000 |
| `engine_tuner` | Engine Tuner | Increases the maximum jump range of the ship by 2 parsecs | 7 | 25,000 |
| `ai_copilot` | AI Co-Pilot | Provides a +1 bonus to both Pilot and Fighter skills simultaneously | 8 | 40,000 |
| `void_veil` | Void Veil | Makes you harder to spot; allows you to bypass some encounters or flee easier | 8 | 30,000 |
| `wormhole_finder` | Wormhole Finder | Reveals hidden shortcuts and jump-points on the galaxy map | 8 | 55,000 |

#### 10.3.1 Gadget Notes

- **Emergency Thrusters** — The 100% flee guarantee resets at the start of each new journey (warp). It is consumed on use; the gadget itself is not removed.
- **Photon Overclocker** — Each combat round it is active, there is a `OVERCLOCKER_DAMAGE_CHANCE = 10%` chance one equipped laser weapon takes 5 HP of internal damage (reduced power until repaired at port).
- **Mining Drill** — When a system has the *Mineral Rich* resource type, a "Mine Asteroids" action becomes available on the Solar System screen (costs 1 day, yields `random(1, 5)` units of Ore added to cargo for free).
- **Market Predictor** — On the Star Map, the top 3 highest-profit trade routes from the current system are highlighted with a colour-coded overlay. Route profitability is estimated based on known port prices.
- **Wormhole Finder** — Reveals all wormhole pairs on the Star Map regardless of proximity, removing the normal `WORMHOLE_DETECT_RANGE` requirement.
- **AI Co-Pilot** — Stacks with Astro-Nav Core and Ballistics Suite for Pilot/Fighter bonuses, but effective skill is still capped at 10.
- **Void Veil** — Reduces base encounter chance and adds +30 to flee rolls.
- **Life Support Vents** — Reduces each hired Mercenary's daily wage by `LIFE_SUPPORT_VENTS_DISCOUNT = 15%`, rounded down per crew member per day.

### 10.4 Ship Upgrades (`data/shipUpgrades.js`)

Ship Upgrades are permanent structural modifications installed at a Shipyard. They do **not** occupy a gadget slot — they are bolted-in changes to the ship's physical configuration. Only one upgrade of each type may be installed at a time. Full detail in Section 9.4.

| ID | Name | Effect | Min Tech | Install Cost | Removal Fee | Min Ship Class |
|---|---|---|---|---|---|---|
| `life_support` | Cargo Life Support | Enables Colonist Transport; reserves 5 cargo bays for life support hardware | 4 | 15,000 | 5,000 | Courier |
| `reinforced_hull` | Reinforced Hull Plating | +25% max hull HP | 5 | 40,000 | 10,000 | Runner |
| `extended_tanks` | Extended Fuel Tanks | +4 max fuel units | 3 | 8,000 | 2,000 | Drifter |
| `cargo_expansion` | Cargo Bay Expansion | +10 cargo bays | 3 | 12,000 | 3,000 | Hauler |

### 10.5 Escape Pod (`data/equipment.js`)

The Escape Pod is a one-use emergency survival device, purchasable at any Equipment Dock (min tech level 4). It occupies **no gadget slot** — it is mounted externally and does not compete with other equipment.

| ID | Name | Effect | Min Tech | Price |
|---|---|---|---|---|
| `escape_pod` | Escape Pod | On ship destruction, player survives and is delivered to the nearest system | 4 | 2,000 |

**Rules:**
- Only one Escape Pod may be installed at a time
- On activation (ship hull reaches 0): player is ejected and arrives at the nearest solar system; all cargo, equipment, and the ship itself are lost
- The pod is consumed on use — player must purchase a replacement
- If colonists are aboard when the pod fires, colonists do not survive (see Section 14.9 for penalties)
- Insurance payout (if active) is triggered immediately after pod activation (see Section 17.3)
- Cannot be sold back once purchased; the pod is considered a safety device and all sales are final
- Displayed as a status badge on the Commander screen: `[ESCAPE POD: READY]` / `[ESCAPE POD: NONE]`
- On the Beginner difficulty setting, the Escape Pod is auto-equipped at game start at no cost (see Section 20)

### 10.6 Equipment Slot Rules

- Weapons, shields, and gadgets each occupy their own typed slot
- Escape Pods occupy no slot and do not count against gadget capacity
- Ship Upgrades occupy no slot — they are installed via the Shipyard Upgrades tab (see Section 21.7b)
- Gadgets and weapons/shields can be sold back at `EQUIP_SELL_RATE = 0.50` of purchase price (used condition)
- Escape Pods cannot be sold back once purchased
- Ship Upgrades are removed at the stated removal fee rather than sold back
- Min tech level enforced at point of sale for all equipment and upgrades

---

## 11. Combat System

### 11.1 Combat Overview

Combat is turn-based and round-resolved. Both sides fire simultaneously each round. The player chooses one action per round, or sets a **standing order** that repeats automatically until cancelled or combat ends.

#### Single-Round Actions

| Action | Description |
|---|---|
| **Fire** | Fire all weapons at the enemy this round |
| **Evade** | Attempt to flee; all thrust goes to escape (no weapons fire) |
| **Surrender** | Give up cargo (pirates), pay fine (police), or face destruction |
| **Yield** | Specific context only (e.g., police stop: allow search without fighting) |

#### Standing Orders (Auto-repeat every round)

| Action | Description |
|---|---|
| **Guns Blazing** | Repeat **Fire** every round automatically until combat ends or player cancels |
| **Full Retreat** | Repeat **Evade** every round automatically until escape succeeds or player cancels |
| **Broadside** | Fire weapons AND attempt to flee simultaneously each round; escape chance is reduced (see Section 11.3); cancels automatically on successful escape |

Standing orders display a highlighted active-mode indicator on the combat screen. The player can cancel a standing order at any time by pressing any single-round action button, which also immediately executes that action for the current round.

### 11.2 Round Resolution

```
For each combatant (player, enemy):
  totalAttack = sum(weapon.power) * attackModifier(fighter skill)
  hitChance   = BASE_HIT + (attacker.fighter - defender.pilot) * SKILL_FACTOR
  hit         = random(0, 100) < hitChance
  if hit:
    damage = totalAttack * random(0.75, 1.25)
    applyDamage(target, damage)

applyDamage(target, damage):
  shieldAbsorb = min(totalShieldHP, damage * SHIELD_FACTOR)
  remaining    = damage - shieldAbsorb
  target.shieldHP -= shieldAbsorb
  target.hullHP   -= remaining
```

### 11.3 Flee & Evasion Mechanics

#### Base Flee Chance Formula

```
baseFleeChance = 40 + (player.pilot - enemy.pilot) * 10
               + (voidVeil ? 30 : 0)
               + (enemy.type == POLICE ? -10 : 0)
```

Emergency Thrusters override all other factors when triggered (see Section 10.3).

#### Per-Action Flee Chance Modifiers

| Action | Flee Chance | Weapons Fire | Notes |
|---|---|---|---|
| **Evade** | `baseFleeChance` (unmodified) | None | Full thrust to escape |
| **Full Retreat** | `baseFleeChance` (unmodified) | None | Same as Evade but repeats; enemy fires each failed round |
| **Broadside** | `baseFleeChance × BROADSIDE_FLEE_PENALTY` | Full attack | Split focus; weapons and thrusters active simultaneously |

`BROADSIDE_FLEE_PENALTY = 0.60` — flee chance reduced to 60% of base (e.g. a 50% base chance becomes 30%).

#### Resolution

```
// Evade / Full Retreat
fleeChance = clamp(baseFleeChance, 5, 95)
success    = random(0, 100) < fleeChance
if success:
  fuelCost = FLEE_FUEL_COST   // default 1
  leaveEncounter()
else:
  enemy fires once more (flee penalty round)
  if Full Retreat: repeat next round automatically

// Broadside
fleeChance = clamp(baseFleeChance * BROADSIDE_FLEE_PENALTY, 5, 75)  // hard cap 75
             + (emergencyThrusters ? 100 : 0)  // still overrides if available
playerFires()                // weapons resolve at full damage regardless of flee outcome
success    = random(0, 100) < fleeChance
if success:
  fuelCost = FLEE_FUEL_COST
  leaveEncounter()           // Broadside standing order cancelled on success
else:
  enemy fires once more (flee penalty round)
  // Broadside standing order: repeat next round automatically
```

#### Emergency Thrusters Interaction

- When Emergency Thrusters are triggered during **Evade** or **Full Retreat**: guaranteed escape, pod consumed
- When triggered during **Broadside**: weapons fire resolves first, then guaranteed escape; pod consumed
- Emergency Thrusters are consumed regardless of which action triggered them

### 11.4 Standing Order State Machine

```javascript
CombatState = {
  standingOrder: Enum,   // NONE | GUNS_BLAZING | FULL_RETREAT | BROADSIDE
  emergencyThrustersUsed: Boolean,
}
```

Each round:
```
if standingOrder == NONE:
  wait for player input (show action buttons)
else:
  execute standingOrder action automatically after SHORT_DELAY ms
  update combat log: "[Standing Order: Guns Blazing]" etc.
  check cancellation conditions:
    - GUNS_BLAZING:   cancel if enemy destroyed, player destroyed, or player manually cancels
    - FULL_RETREAT:   cancel if escape succeeds, player destroyed, or player manually cancels
    - BROADSIDE:      cancel if escape succeeds, player destroyed, or player manually cancels
```

`SHORT_DELAY = 800` ms — brief pause between auto-rounds so the player can read the log and cancel.

When a standing order is active, a **[CANCEL]** button is prominently displayed alongside the round log. Pressing any of the four single-round action buttons instantly cancels the standing order and executes that action instead.

### 11.5 Damage Display

- Player hull and shields shown as animated progress bars
- Enemy hull shown as a bar (no exact number displayed, just visual)
- Active standing order shown as a coloured badge: `● GUNS BLAZING`, `● FULL RETREAT`, `● BROADSIDE`
- Hit/miss flashes on the combatant sprite
- Combat log panel shows last 6 rounds of text events, including auto-round markers
- Round counter

### 11.6 Post-Combat

| Outcome | Result |
|---|---|
| Player wins vs Pirate | Loot cargo (partial), possible bounty reward |
| Player wins vs Police | Police record worsens significantly |
| Player wins vs Trader | Possible loot, record worsens slightly |
| Player flees (any method) | No loot, no record change |
| Player surrenders (pirate) | Cargo stolen (up to demand amount) |
| Player surrenders (police) | Fine paid, contraband confiscated |
| Player destroyed | Game over (or escape pod if equipped) |

### 11.7 Escape Pod

Full specification in Section 10.5. Combat-relevant summary:

- If equipped (`ship.escapePod == true`), on hull reaching 0: pod fires automatically
- Player survives and arrives at the nearest solar system; ship, cargo, and equipment are lost
- The pod is consumed on activation — `ship.escapePod` set to `false`
- Insurance payout triggered immediately if active (see Section 17.3)

---

## 12. Player Character & Skills

### 12.1 Skill Allocation

At new game, player receives **SKILL_POINTS = 16** to distribute among four skills (1–10 scale, max 10 per skill, min 1 per skill):

| Skill | Effect |
|---|---|
| **Pilot** | Flee chance, avoid encounters, navigation accuracy |
| **Fighter** | Hit chance, damage output in combat |
| **Trader** | Buy prices lower, sell prices higher (up to ±10%) |
| **Engineer** | Repair/fuel costs lower, auto-repair rate, ship maintenance |

### 12.2 Effective Skill

Effective skill = base skill + crew bonuses + gadget bonuses

```javascript
effectivePilot   = player.skills.pilot   + sum(crew.pilotBonus)
                 + (hasAstroNavCore   ? 1 : 0)
                 + (hasAiCoPilot      ? 1 : 0)
effectiveFighter = player.skills.fighter + sum(crew.fighterBonus)
                 + (hasBallisticsSuite ? 1 : 0)
                 + (hasAiCoPilot       ? 1 : 0)
effectiveTrader  = player.skills.trader  + sum(crew.traderBonus)
effectiveEngineer= player.skills.engineer+ sum(crew.engineerBonus)
                 + (hasNanoPatchKit   ? 1 : 0)
```

---

## 13. NPC & Encounter System

### 13.1 Encounter Probability

On each warp, for each parsec of travel, roll for encounter:

```
encounterChance = BASE_ENCOUNTER + system.pirates * PIRATE_WEIGHT
                + system.police * POLICE_WEIGHT + system.traders * TRADER_WEIGHT
                - player.effectivePilot * PILOT_REDUCTION
                - (voidVeil ? CLOAK_REDUCTION : 0)
```

### 13.2 Encounter Types

| Type | Trigger Conditions |
|---|---|
| `PIRATE` | Pirate density > 0 |
| `POLICE` | Police presence > 0 |
| `TRADER` | Trader density > 0; can trade or rob |
| `GHOST_SHIP` | Random; abandoned ship with cargo |
| `BOTTLE_OLD` | Random; skill point reward |
| `BOTTLE_GOOD` | Random; credits reward |
| `CAPTAIN_VOSS` | Quest-related (reactor/hull upgrade) |
| `CAPTAIN_RAEL` | Quest-related (shield upgrade) |
| `CAPTAIN_OKORO` | Quest-related (gadget) |
| `SPACE_LEVIATHAN` | Quest: Leviathan-class monster |
| `PHANTOM` | Quest: stolen ship |

### 13.3 NPC Ship Selection

NPC ship type chosen based on difficulty + system pirate/police rating:

- Police: weighted toward Corvettes, Marauders at high police levels
- Pirates: weighted toward Interceptor, Corvette, Marauder at high pirate levels
- Traders: weighted toward Freighter, Hauler

NPC equipment proportional to ship type and game difficulty.

### 13.4 NPC Behaviour (Combat AI)

| AI Type | Behaviour |
|---|---|
| Aggressive | Always attacks, never flees |
| Normal | Attacks, flees when hull < 20% |
| Cowardly | Prefers fleeing, attacks when cornered |
| Berserker | Attacks with increasing damage as hull drops |

Police: Initially hail, scan cargo, then decide to fine/arrest/attack based on player record.

---

## 14. Colonist Transport System

### 14.1 Overview

The Colonist Transport system allows players to earn income by ferrying groups of colonists from established worlds to frontier or newly founded planetary systems. It is a distinct revenue stream from commodity trading, rewarding long-haul routes rather than rapid short hops.

To participate, the player must:
1. Install the **Cargo Life Support** ship upgrade (see Section 9.4.1) at a qualifying Shipyard
2. Visit a **Colonial Registry Office** (available at planets with tech level ≥ 3 and population size Medium or above)
3. Accept a colonist group contract specifying a destination system
4. Transport colonists to the destination before their contract deadline

### 14.2 Colonial Registry Office

The Colonial Registry Office is a new service available at ports meeting the minimum criteria. It is added to the planet services list alongside Market, Shipyard, etc.

| Service | Min Tech Level | Min System Size | Description |
|---|---|---|---|
| Colonial Registry | 3 | Medium | Accept colonist transport contracts |

The Registry UI presents a list of available colonist groups currently waiting for transport from that planet. Each entry in the list is a `ColonistGroup` object.

### 14.3 ColonistGroup Data Model

```javascript
ColonistGroup = {
  id: String,                  // unique contract ID
  label: String,               // e.g. "47 Settlers — Dracos System"
  count: Number,               // number of colonists (1 count = 1 cargo bay)
  destinationSystemIndex: Number,
  destinationName: String,
  contractDeadline: Number,    // game day by which delivery must be made
  baseFarePerColonist: Number, // credits per colonist (pre-distance multiplier)
  totalFare: Number,           // pre-calculated: baseFare * count * distanceMultiplier
  urgencyBonus: Number,        // extra credits if delivered with days to spare
  status: Enum,                // WAITING | ABOARD | DELIVERED | FAILED
}
```

### 14.4 Fare Calculation

Fares are calculated when the contract is generated, at the moment the player visits the Registry:

```
distanceParsecs     = distance(currentSystem, destinationSystem)
distanceMultiplier  = 1.0 + (distanceParsecs / MAX_COLONIST_RANGE) * COLONIST_DISTANCE_SCALE
baseFarePerColonist = COLONIST_BASE_FARE                          // 80 credits
totalFare           = baseFarePerColonist * count * distanceMultiplier

urgencyBonus        = totalFare * COLONIST_URGENCY_RATE           // 0.20 (20% bonus)
                      if delivered with ≥ COLONIST_URGENCY_DAYS days remaining (5 days)

deadlineDays        = ceil(distanceParsecs * COLONIST_DEADLINE_FACTOR) + COLONIST_DEADLINE_BUFFER
                                                                  // factor=1.5, buffer=5 days
contractDeadline    = currentDay + deadlineDays
```

**Example:** 30 colonists travelling 18 parsecs.
- `distanceMultiplier = 1 + (18/60) * 2.0 = 1.60`
- `totalFare = 80 * 30 * 1.60 = 3,840 credits`
- `urgencyBonus = 3,840 * 0.20 = 768 credits` (if delivered 5+ days early)

### 14.5 Cargo Bay Consumption

Each colonist occupies **1 cargo bay** for the duration of the journey. The colonist groups are stored as a special cargo type in `player.cargo`:

```javascript
CargoSlot = {
  goodId: 'colonists',          // reserved ID; treated as non-tradeable special cargo
  quantity: Number,             // number of colonists (= bays used)
  avgCostPerUnit: 0,            // colonists are not purchased; no buy cost
  colonistGroupId: String,      // links back to ColonistGroup contract
}
```

- The **5-bay Life Support overhead** (from the upgrade hardware itself) is always reserved and is shown separately from colonist bays in the Cargo screen
- Colonists **cannot** be jettisoned (doing so constitutes a criminal act: police record −5, full fare forfeited)
- Multiple colonist groups may travel simultaneously if cargo space allows (one `CargoSlot` entry per group)

### 14.6 Available Contracts Per Port

The number of contracts available at any Registry scales with system size:

| System Size | Max Active Contracts |
|---|---|
| Tiny | 0 (no Registry) |
| Small | 0 (no Registry) |
| Medium | 1–2 |
| Large | 2–4 |
| Huge | 3–6 |

Contracts refresh each time the player visits the system (one full day cycle). Unaccepted contracts expire after `COLONIST_CONTRACT_EXPIRY = 7` days and are replaced with new ones.

### 14.7 Colonist Group Size Range

| Size Class | Count Range | Notes |
|---|---|---|
| Family | 1–5 | Small group; low payout, very low bay cost |
| Settlers | 6–20 | Standard contract |
| Community | 21–50 | High payout; requires significant cargo allocation |
| Expedition | 51–80 | Large payout; typically only available on Huge worlds |

### 14.8 Delivery & Payout

On arrival at the destination system:

```
1. Game detects player has colonists aboard with destinationSystemIndex == currentSystemIndex
2. "Colonists Disembarked" notification shown
3. Payout calculated:
   if currentDay <= contractDeadline:
     payout = totalFare
     if (contractDeadline - currentDay) >= COLONIST_URGENCY_DAYS:
       payout += urgencyBonus
   else:
     payout = totalFare * COLONIST_LATE_PENALTY   // 0.50 — half fare on late delivery
4. Credits added to player
5. CargoSlot removed; bays freed
6. ColonistGroup.status = DELIVERED
7. Police record +1 (lawful delivery bonus) if delivered on time
```

### 14.9 Contract Failure

If the player **never** delivers colonists and the deadline passes:

- Contract flagged `FAILED`; no payout
- Police record −2 (abandonment of contracted persons)
- Failed contracts are tracked; accumulating 3+ failures adds a permanent **"Unreliable"** flag visible to future Registry offices (higher haggling resistance, lower fare offers)

Colonists can be voluntarily returned to any planet with a Registry (not necessarily origin) at a **−30% fare penalty** — a legitimate way to exit a contract the player cannot complete.

### 14.10 Interactions with Other Systems

| System | Interaction |
|---|---|
| Combat | If player ship is destroyed with colonists aboard, colonists are lost; police record −10 (in addition to standard destruction penalties) |
| Escape Pod | Escape pod triggers ship loss; colonists do not survive; same penalty as above |
| Police Encounter | Police scanner detects colonists as legitimate cargo; no aggression modifier |
| Pirate Encounter | Pirates may demand colonists as ransom (special dialogue branch); player can pay ransom (credits), fight, or flee |
| Tribbles | If tribbles are present, colonists complain; morale penalty applies: urgency bonus is forfeited if tribbles still aboard on delivery |
| Wormholes | Wormhole travel is valid for colonist delivery; distance is calculated pre-wormhole (actual travel distance, not grid distance) |

### 14.11 UI — Colonial Registry Screen

A new screen `ColonialRegistryScreen.js` is added to the screen stack.

**Layout:**

```
┌──────────────────────────────────────┐
│  COLONIAL REGISTRY — [System Name]   │
│  Life Support: [INSTALLED / MISSING] │
├──────────────────────────────────────┤
│  Available Contracts:                │
│  ┌────────────────────────────────┐  │
│  │ 12 Settlers → Kelvari System  │  │
│  │ Fare: 1,440 cr  Dist: 9 pc    │  │
│  │ Deadline: Day 34  Bays: 12    │  │
│  │              [ACCEPT CONTRACT] │  │
│  ├────────────────────────────────┤  │
│  │ 35 Community → Elysara System │  │
│  │ Fare: 5,880 cr  Dist: 22 pc   │  │
│  │ Deadline: Day 48  Bays: 35    │  │
│  │              [ACCEPT CONTRACT] │  │
│  └────────────────────────────────┘  │
│                                      │
│  Active Contracts (Aboard):          │
│  [list of groups currently in hold]  │
├──────────────────────────────────────┤
│  Free Cargo Bays: 12 / 50            │
│  Life Support Overhead: 5 bays       │
│                    [CLOSE]           │
└──────────────────────────────────────┘
```

- If Life Support is **not** installed: all contract rows are greyed out, and a prompt reads *"Install Cargo Life Support at the Shipyard to accept colonist contracts."*
- Accepting a contract that exceeds available bays shows an error: *"Insufficient cargo capacity for this group."*
- The Cargo screen shows colonist entries with a person icon (👤) rather than a box icon, distinguishing them from goods

### 14.12 Sound Effects (Colonist-Specific)

| ID | Trigger |
|---|---|
| `colonist_board` | Colonist group accepted and loaded |
| `colonist_deliver` | Successful delivery payout |
| `colonist_late` | Late delivery (reduced fare) |
| `colonist_alarm` | Pirate ransom demand for colonists |

---

## 15. Travel & Navigation

### 15.1 Warp Mechanics

- Player selects destination system on Star Map
- If within fuel range: warp is available
- Travel cost: `ceil(distance(current, destination))` fuel units
- Distance formula: `sqrt((dx*dx) + (dy*dy))` in grid units

### 15.2 Travel Sequence

```
1. Player selects destination on Star Map
2. Validate fuel ≥ distance
3. Show "Confirm Warp" dialog (destination name, fuel cost, estimated day)
4. On confirm:
   a. Deduct fuel
   b. Advance game day by max(1, floor(distance / WARP_SPEED))
   c. Resolve encounter rolls along path
   d. Apply auto-repair (if gadget equipped)
   e. Apply tribble breeding (if any)
   f. Arrive at destination: update currentSystemIndex
   g. Regenerate port inventory if first visit in cycle
   h. Generate system news headlines
   i. Transition to SOLAR_SYSTEM scene
```

### 15.3 Fuel Management

- Fuel bar displayed on Star Map and Commander screen
- Systems out of fuel range are greyed out on Star Map
- "No fuel" state: player is stranded — must be rescued or wait for encounter
- Fuel purchased at fuel dock (service available on most planets)

### 15.4 Sub-Light Travel (Same System)

- Moving between planets within a system costs 0 fuel but advances 1 day
- Tribbles still breed; no encounters within system (except event triggers)

---

## 16. Wormholes

### 16.1 Wormhole Rules

- **WORMHOLE_COUNT = 6** pairs generated at universe creation
- Wormholes are visually distinct on the Star Map (animated portal icon)
- Travelling through a wormhole costs `WORMHOLE_FUEL_COST = 10` fuel regardless of distance
- Wormhole exit is the paired system (random selection at generation)
- Wormholes do not appear on the map until the player is adjacent (`≤ WORMHOLE_DETECT_RANGE = 2` parsecs)

---

## 17. Banking & Finance

### 17.1 Loans

- Maximum loan: `min(1,000,000, netWorth * 0.25)` credits
- Interest rate: `LOAN_RATE = 10%` per annum (scaled to game days)
- Debt accrues daily; unpaid debt increases at compound rate
- Player cannot retire if debt > 0
- If debt exceeds net worth × 2, game-over condition triggers (bankruptcy)

### 17.2 Net Worth Calculation

```
netWorth = credits
         + ship.currentValue
         + cargoValue
         + sum(equipment.sellValue)
         - debt
```

### 17.3 Insurance

- Ship insurance costs `INSURANCE_RATE = 0.0025` × ship base price per day
- On ship loss (destroyed): insurance pays out `min(shipValue, ship.basePrice * 0.9)`
- No-claims bonus: reduces premium after N days without a claim
- Insurance cancelled if player fails to pay premiums (auto-deducted daily)

---

## 18. Special Events & Quests

### 18.1 Random Events

Triggered by PRNG rolls at port arrival:

| Event | Description | Effect |
|---|---|---|
| Skill Increase | Tribble trainer, lucky encounter | +1 to random skill |
| Tribbles | Merchant sells tribbles cheap | Tribbles infest cargo hold |
| Pirate Raid (docked) | Ground raid while docked | Random cargo stolen |
| Market Tip | Insider info from a dockworker | Highlight one good on market (price anomaly) |
| Weird Gas Cloud | Warp mishap en route | Random system component damaged |
| Space Casino | Gambling den at port | Win or lose up to 2,000 credits |
| Fuel Leak | Engineer notices slow leak | −2 fuel on arrival; free repair if engineer skill ≥ 5 |
| Hull Microcrack | Debris impact en route | −5 hull HP; reduced repair cost |
| Crew Bonus | Crew member finds contraband stash | +random(200, 800) credits |
| Beggar's Plea | Desperate drifter at docking bay | Donate credits for small police record boost, or ignore |

---

### 18.2 Quest Catalogue

All 30 quests below are one-per-game (with noted exceptions). Quest availability is seeded — not every quest appears in every playthrough. Most require specific system visits or trigger conditions. Columns:

- **Trigger** — how the quest starts
- **Objective** — what the player must do
- **Complication** — the twist or risk
- **Reward** — on success
- **Failure** — on timeout or wrong choice

| # | Name | Type | Trigger | Objective | Complication | Reward | Failure Consequence |
|---|---|---|---|---|---|---|---|
| Q01 | **The Kelvari Reactor** | Timed Delivery | Visit `KELVARI_SYSTEM`; offered by port authority | Deliver radioactive reactor (5 bays) to `DRACOS_SYSTEM` within 20 days | Reactor deals 1 HP hull damage per day in cargo; cannot be jettisoned | 500,000 cr (difficulty-scaled) | Reactor detonates on day 21 — ship destroyed; escape pod if equipped |
| Q02 | **The Leviathan** | Boss Combat | Random deep-space encounter (triggered once per game, mid-to-late game) | Defeat the Leviathan creature in combat | Extreme hull, no shields, devastating attack; flees after 60% damage then returns stronger | Leviathan hull plating (permanent +20 HP), salvage credits | Creature escapes; cannot be re-triggered |
| Q03 | **The Phantom** | Multi-Stage Combat | Rumour heard at any tech-7 system | Track and destroy the stolen experimental ship Phantom across 3 systems | Phantom has military laser, reflective shields, and attempts to flee each round | Military pulse laser + 80,000 cr | Phantom escapes permanently; bounty uncollected |
| Q04 | **The Princess** | Timed Rescue | Distress beacon near mid-game system | Transport the kidnapped Princess Vael to her home system within 30 days | Pirates actively intercept (increased encounter rate while she is aboard) | +3 randomly distributed skill points + 25,000 cr | Princess taken by pirates; police record −5 |
| Q05 | **The Sanctuary Moon** | Retirement / Win | Purchase offered at `ELYSARA_SYSTEM` (requires `MOON_PRICE` credits) | Buy the private moon and retire | None — this is the primary win condition | Game won; retirement score calculated | N/A — quest persists until bought or game ends |
| Q06 | **Bottles of Fortune** | Random Loot | Found aboard Ghost Ship event or sold by wandering merchant | Identify the bottle contents | Bottle of Skill (+1 random skill) or Bottle of Good (500–2,000 cr); 10% chance of Bottle of Poison (−10 hull) | +1 skill or credits | −10 hull if poisoned |
| Q07 | **Ghost Ship** | Exploration / Loot | Random warp event (low probability, deep-space only) | Board the abandoned vessel and salvage its cargo | Cargo may include illegal goods (police risk); 20% chance of automated defence drone combat | Random cargo (4–12 bays worth) + possible rare equipment | Drone destroys ship if combat failed; no salvage |
| Q08 | **The Plague Shipment** | Moral Choice | Port authority at system under PLAGUE condition | Carry experimental medpacks (3 bays) to afflicted system within 15 days | Medpacks are flagged as controlled substances — police scanners will trigger stops | 40,000 cr + police record +3 if delivered | Patients die; police record −2 for abandonment |
| Q09 | **War Profiteer** | Trade / Ethics | Arms dealer at any DICTATORSHIP system | Buy munitions cheap and deliver to a system at WAR | Police record −3 on delivery regardless (supplying a war zone); chance of pirate ambush en route | 3× munitions sale price | Ambush may destroy cargo; police record loss still applies |
| Q10 | **The Defector** | Escort | Intelligence contact at CORPORATE STATE system | Escort defector's ship to a DEMOCRACY system within 20 days | Corporate bounty hunters (3 encounters guaranteed) pursue both ships; defector's ship has weak hull | 60,000 cr + Pilot +1 | Defector destroyed; police record −1 |
| Q11 | **Rael's Ransom** | Timed Payment | Captain Rael captured; message received at any port | Gather 75,000 cr ransom and deliver to pirate station within 25 days | Pirate station only appears on map after quest starts; no other services there | Captain Rael joins as a free crew member (Fighter +2) | Rael executed; crew slot lost permanently |
| Q12 | **The Okoro Artefact** | Investigation | Antiquities dealer at tech-6+ system | Collect 3 artefact fragments from 3 separate systems | Each fragment is mildly illegal; fragments cannot be in cargo during police scans | Ancient navigation device: reveals all system names on map permanently | Fragments confiscated; no reward |
| Q13 | **The Fuel Cache** | Exploration | Decoded star chart sold by merchant at Renaissance-level system | Navigate to hidden cache coordinates in deep space | Cache is in a system with no port — no repairs or services; pirates guard it | 30 free fuel units + 15,000 cr in salvage | Pirates destroy player if combat lost |
| Q14 | **The Void Cannon** | Quest Weapon | Offer appears at a remote ANARCHY system after player reaches 50+ kills | Retrieve the experimental Void Cannon from a derelict military station | Station guarded by two military-class ships; Void Cannon takes 2 weapon slots | Void Cannon (triple damage, 2-slot weapon) | Guards permanently hostile if fled; station sealed |
| Q15 | **The Colonist Uprising** | Crisis Response | Triggered if player has delivered 5+ colonist groups | Emergency broadcast: colonists have revolted on a remote frontier world | Player must choose: deliver suppression forces (police record +5, trader rep −3) or deliver aid supplies (police record +2, trader rep +2) | Credits + reputation shift based on choice | No consequence if ignored except −1 reputation with Colonial Registry |
| Q16 | **The Wormhole Cartographer** | Multi-System Exploration | Professor at a tech-7 system offers a research contract | Visit all 6 wormhole exit systems and return sensor readings | Each wormhole visit is a timed window (10 days from previous); player must chain them | Wormhole Finder gadget (free, fully functional) + 30,000 cr | Incomplete data; partial reward (15,000 cr) |
| Q17 | **The Debt Collector** | Moral Choice | Loan shark's representative appears at any port if player has debt > 20,000 cr | Pay off debt in full within 10 days OR refuse | Refusing triggers two guaranteed bounty hunter encounters; paying gives clean record | Debt cleared + police record +1 | Bounty hunters attack; debt doubles if both defeated |
| Q18 | **Tribble Zero** | Pest Control | Triggered when tribbles exceed 5,000 aboard ship | Find the Tribble Exterminator at a specific tech-5 system within 20 days | Cannot sell or jettison tribbles; they breed faster the longer the quest is active | Tribbles eliminated; receive Anti-Tribble Spray (prevents future infestation) | Tribbles consume 10% of all cargo per day until exterminated |
| Q19 | **The Insurance Fraud Ring** | Investigation | Insurance broker at any CONFEDERACY system tips off the player | Collect evidence from 2 systems, then report to police HQ system | Evidence flagged as stolen property by the fraud ring; they send one enforcer ship | 50,000 cr bounty + police record +4 | Enforcers destroy evidence; ring continues operating (insurance costs +5% permanently) |
| Q20 | **The Lost Expedition** | Rescue | Distress signal from deep-space system with no port | Rescue 8 stranded scientists (requires Life Support upgrade) and return them within 10 days | Scientists take 8 cargo bays; no Registry contract — strictly quest cargo | 45,000 cr + Engineer +1 | Scientists die; police record −3 |
| Q21 | **The Pirate King** | Boss Combat | Reputation among pirates deteriorates past −60 OR high kill count (20+ pirates) | Defeat the Pirate King in his flagship in his home system | Pirate King has a full crew (2 extra combat rounds of support fire per round); ship is the strongest in the game short of military | Pirate King's ship (upgrade current ship hull by 50 HP) + 100,000 cr | Pirate King survives; pirate encounter rate increases by 25% permanently |
| Q22 | **The Smuggler's Favour** | Contraband Delivery | Shady contact in ANARCHY or FEUDAL system | Deliver unmarked cargo (3 bays, contents unknown) to named contact at target system | If scanned by police, cargo reveals illegal weapons — record −5 and confiscation; no quest cancel | 35,000 cr (no record change if unscanned) | Cargo confiscated; no payment; possible arrest |
| Q23 | **The Clone Scandal** | Story / Moral Choice | News headline triggers investigation at a tech-6 system | Investigate a black-market cloning lab and choose: expose it (newscast reward) or sell the data to the lab | Exposure: police record +5, lab sends one assassin ship; sale: 60,000 cr, police record −2 | Depends on choice (see Complication) | Ignored after 30 days; no consequence |
| Q24 | **Engine of Ruin** | Timed Sabotage | Black ops contact at a COMMUNIST system | Plant a device on a corporate freighter within 15 days (requires intercepting it in deep space) | Intercepting the freighter may be read as piracy (police record −2 on intercept); device must be installed via cargo transfer, not combat | 90,000 cr + Void Veil gadget (free) | Freighter warps out; contact disappears; no reward |
| Q25 | **The Stranded Merchant** | Rescue / Trade | Random encounter: merchant drifting with no fuel | Donate fuel (minimum 5 units) to stranded merchant | None | Merchant gives discount coupon: one item at 50% off at their home system's Equipment Dock | Merchant drifts; no consequence |
| Q26 | **The Neural Heist** | Stealth Delivery | Black market contact at tech-5+ system | Smuggle a crate of Neuralware (3 bays) past a police blockade system | Blockade system has 100% scan rate for this journey leg only | 3× Neuralware value + Engineer +1 | Cargo confiscated; police record −4; contact unfindable |
| Q27 | **The Mercenary Contract** | Combat Bounty | Mercenary Guild board at MERCENARY ENCLAVE resource system | Hunt and destroy 5 marked pirate ships within 30 days | Marked pirates are tougher than normal (buffed hull × 1.5); last target is a Corvette-class | 70,000 cr + Fighter +1 | Incomplete; partial payment (10,000 cr per confirmed kill) |
| Q28 | **The Dying Admiral** | Moral Choice / Escort | Random encounter: crippled military vessel hailing for help | Escort the Admiral's ship to a DEMOCRACY system OR ignore | If escorted: 2 guaranteed police encounters (they verify identity); if ignored: no immediate consequence | Escort reward: permanent police encounter rate −15% for rest of game | Ignored: no penalty; Admiral's ship destroyed eventually (no further effect) |
| Q29 | **The Ore Cartel** | Trade Disruption | Mining syndicate contact at MINERAL RICH system | Purchase 20 units of ore from independent miners across 3 systems and deliver to cartel headquarters | Cartel's rivals intercept once per system (guaranteed combat or bribe); ore price fluctuates mid-quest | 2× market value for all 20 units + Trader +1 | Rivals steal cargo; partial delivery accepted at 1× value |
| Q30 | **The Final Broadcast** | Story Climax | Triggered on day 100+ OR after completing any 10 other quests | Transmit a recovered signal from a derelict probe to the galactic beacon at `ELYSARA_SYSTEM` | Signal is contested — a military ship and a pirate ship both intercept (sequential encounters) | Unlock secret epilogue text on retirement screen + 50,000 cr + police record +5 | Signal lost; epilogue text not unlocked; no other penalty |

---

### 18.3 Quest Design Notes

- **Seeded availability:** At new game creation, `Universe.generate(seed)` assigns a subset of Q07–Q30 to the playthrough. Q01–Q06 and Q25 (Stranded Merchant) are always present. Q07–Q30 have a 70% chance each of being active in any given game, ensuring variety across playthroughs.
- **Quest state tracking:** Each quest is tracked in `GameState.quests` as `{ id, status, progress, dayStarted, flagData }` where `status` is one of `UNAVAILABLE | AVAILABLE | ACTIVE | COMPLETE | FAILED`.
- **Mutual exclusions:** Q09 (War Profiteer) and Q28 (Dying Admiral) cannot both be active simultaneously — the Admiral quest blocks the profiteer offer if already accepted.
- **Moral choice quests** (Q08, Q09, Q15, Q23, Q28) have no "wrong" answer — both paths give rewards of different types. Police record and reputation shifts are the trade-off.
- **Skill rewards** (+1 Pilot, +1 Fighter, etc.) are capped by the normal skill maximum of 10 and silently ignored if already at cap.

---

## 19. Police, Bounty & Reputation

### 19.1 Police Record Score

Range: `-100` (Villain) to `+100` (Hero). Starting value: `0` (Clean).

| Score Range | Label | Police Behaviour |
|---|---|---|
| 100 to 50 | Hero | Police assist in combat |
| 49 to 10 | Lawful | No hassle |
| 9 to -9 | Neutral | Occasional scan |
| -10 to -25 | Dubious | Regular scanning |
| -26 to -50 | Criminal | Frequent stops, fines |
| -51 to -90 | Felon | Police attack on sight |
| -91 to -100 | Psychopath | Maximum hostility |

### 19.2 Record Changes

| Action | Change |
|---|---|
| Destroy police ship | -3 |
| Destroy trader ship | -1 |
| Destroy pirate ship | +1 |
| Pay fine | +1 |
| Caught with contraband | -2 |
| Bribe police | -1 |
| Complete quest (lawful) | +2 to +5 |

### 19.3 Bounties

- If player is a wanted criminal, a bounty is placed: `abs(policeRecord) * 100` credits
- Bounty hunters (special NPC type) actively pursue player
- Bounty cleared by: paying fine at a police post, completing community service quest

### 19.4 Reputation Among Traders

- Separate from police record
- High trader reputation: merchants offer better deals, insurance discounts
- Low reputation (robbing traders): merchants refuse to trade, become hostile

---

## 20. Difficulty Settings

| Setting | Beginner | Easy | Normal | Hard | Impossible |
|---|---|---|---|---|---|
| Starting Credits | 5,000 | 3,000 | 1,000 | 500 | 100 |
| Starting Ship | Courier | Courier | Drifter | Drifter | Drifter |
| Skill Points | 20 | 18 | 16 | 14 | 12 |
| Police Aggressiveness | ×0.5 | ×0.75 | ×1.0 | ×1.25 | ×1.5 |
| Pirate Aggressiveness | ×0.5 | ×0.75 | ×1.0 | ×1.25 | ×1.5 |
| Insurance Available | Yes | Yes | Yes | No | No |
| Escape Pod Auto-Equip | Yes | No | No | No | No |
| Moon Price | 250,000 | 400,000 | 500,000 | 750,000 | 1,000,000 |
| Loan Interest | 5% | 8% | 10% | 12% | 15% |

---

## 21. UI Screens & Panels

All screens are drawn entirely on canvas. No HTML elements (except the canvas tag itself) are used for gameplay UI.

### 21.1 Main Menu Screen

- Full-screen animated starfield background
- Title "VOID MERCHANT" in large text with glow effect
- Buttons: **New Game**, **Load Game** (opens Save / Load screen), **High Scores**, **Settings**, **About**
- Version number in corner

### 21.2 New Game Screen

- Name entry (canvas text input field)
- Difficulty selector (radio button row)
- Skill point allocation (+ / − buttons per skill, remaining points shown)
- Preview panel: ship image placeholder, starting credits, ship type
- **Start Game** button (disabled until name entered and points fully allocated)

### 21.3 Solar System Screen (Main Hub)

Layout: Top bar → Content area → Bottom navigation

**Top Bar:**
- System name, tech level badge, government type, condition icon
- Day counter, current credits

**Content Area (tabbed or scrollable):**
- Planet portrait (procedurally drawn from system seed)
- News ticker (scrolling headlines)
- Available services as large icon buttons: Market, Shipyard, Equipment, Personnel, Bank, Fuel, Repair, Colonial Registry (if available)

**Bottom Navigation:**
- Star Map button, Commander button, Cargo button, Settings button

### 21.4 Star Map Screen

- Canvas-drawn galaxy: dots for systems, lines for wormholes
- Current system highlighted (pulsing dot)
- Visited systems: white; unvisited: dim
- Fuel range drawn as a circle around current system
- Tap/click system → shows info panel (name, tech, govt, distance, fuel cost)
- **Warp Here** button in info panel
- Zoom in/out: pinch-zoom (touch) or mouse wheel
- Pan: drag

### 21.5 Market Screen

- Table layout: Good name | Available | Buy Price | Sell Price | Qty in Cargo
- Buy row: − / quantity input / + / **Buy** button
- Sell row: − / quantity input / + / **Sell** button
- Profit indicator: colour-coded (green = profit vs purchase price)
- Credit balance updated live as quantities change
- Cargo bay usage bar at bottom

### 21.6 Ship Yard Screen

- Scrollable list of available ships
- Each entry: ship image | stats table | trade-in value | final cost
- Highlight selected ship
- **Buy Ship** button with confirmation modal

### 21.7 Equipment Dock Screen

- Three tabs: **Weapons**, **Shields**, **Gadgets**
- Available items listed with price, effect, and "Add to Ship" button
- Currently installed items listed with "Remove" button (and sell-back value)
- Slot indicators show used/total slots

### 21.7b Ship Upgrades Screen

Accessible from the Shipyard as a second tab labelled **Upgrades**.

- Lists all available ship upgrades with install cost, effect, and compatibility note
- Currently installed upgrades shown with an **Uninstall** button (displays labour fee)
- Incompatible upgrades greyed out with a note (e.g., *"Requires Courier class or above"*)
- Life Support row shows: *"Enables Colonial Registry contracts. Reserves 5 cargo bays."*
- Confirmation modal on install/uninstall with before/after cargo bay count

### 21.7c Colonial Registry Screen

See Section 14.11 for full layout specification. Accessible from the Solar System hub when the service is available.

### 21.8 Personnel Office Screen

- Available mercenaries listed: name, skills, daily wage
- Hired crew listed with dismiss option
- Crew slot usage indicator

### 21.9 Bank Screen

- Current debt display
- Loan amount input (slider + text)
- **Take Loan** / **Repay Loan** buttons
- Interest rate and daily cost shown
- Net worth breakdown panel

### 21.10 Commander Screen

- Player name, current day, credits
- Skills display (current effective values)
- Police record bar (colour-coded)
- Kill counts (pirate/trader/police)
- Ship summary (name, hull %, cargo used)
- Insurance status
- Net worth summary

### 21.11 Cargo Screen

- List of goods in cargo with quantity and average buy price
- Jettison button per good (with confirmation); jettison disabled for colonist entries
- Colonist groups shown with person icon (👤), count, destination, and days remaining on contract
- Equipment in cargo listed separately
- Life Support overhead shown as reserved bays (if upgrade installed): `[LIFE SUPPORT: 5 bays reserved]`
- Total bays used / total bays available

### 21.12 Encounter Screen

- Enemy ship image (stylised pixel art)
- Enemy name / type label
- Brief hail text (dialogue line)
- Action buttons: **Attack**, **Flee**, **Surrender**, **Yield** (context-dependent)
- "Ignore" option for non-hostile encounters

### 21.13 Combat Screen

- Player ship (bottom), enemy ship (top), animated
- Hull/shield bars for both sides
- Weapon animation: laser beams drawn from attacker to target on Fire/Broadside rounds
- Hit/miss text animation
- Combat log: last 6 lines scrolling, auto-round entries marked with `●`
- Round counter

**Action Buttons (two rows):**

```
┌─────────────────────────────────────────┐
│  Round 4          ● BROADSIDE ACTIVE    │
│  ┌───────────────────────────────────┐  │
│  │ Enemy Hull ████████░░░░░░░░░ 52%  │  │
│  │ Your Hull  ██████████████░░ 88%   │  │
│  │ Shields    ████████████░░░░ 75%   │  │
│  └───────────────────────────────────┘  │
│  > Round 4 [Broadside]: HIT 42 dmg      │
│  > Enemy fired: MISS                    │
│  > Round 3 [Broadside]: HIT 38 dmg      │
│  > Enemy fired: HIT 12 dmg              │
│  > Round 2 [Broadside]: MISS            │
│  > Round 1 [Fire]: HIT 45 dmg           │
├─────────────────────────────────────────┤
│  Single Round:                          │
│  [  FIRE  ]  [  EVADE  ]  [SURRENDER]  │
│                                         │
│  Standing Orders:                       │
│  [GUNS BLAZING] [FULL RETREAT]          │
│  [   BROADSIDE  ]  [  CANCEL  ]        │
└─────────────────────────────────────────┘
```

- **FIRE** — single round attack
- **EVADE** — single round flee attempt
- **SURRENDER** / **YIELD** — context-dependent; shown when applicable
- **GUNS BLAZING** — sets standing order; button highlights when active
- **FULL RETREAT** — sets standing order; button highlights when active
- **BROADSIDE** — sets standing order; button highlights when active
- **CANCEL** — only visible when a standing order is active; cancels it immediately
- Standing order badge shown in top-right of combat panel with colour coding:
  - `● GUNS BLAZING` — red
  - `● FULL RETREAT` — cyan
  - `● BROADSIDE` — amber

### 21.14 Settings Screen

- Sound volume (SFX / Music separately)
- Display: fullscreen toggle, HiDPI toggle
- Controls: touch sensitivity (mobile)
- **Delete Save** (with double confirmation)
- **Credits** (shows original game credits)

### 21.15 High Score / Retirement Screen

- Final net worth
- Days survived
- Kill breakdown
- Difficulty badge
- "Void Merchant of the Year" trophy if retirement achieved
- **New Game** / **Main Menu** buttons

---

## 22. Input Handling

### 22.1 Mouse Input

| Event | Handler |
|---|---|
| `mousedown` | Start drag / button press |
| `mouseup` | Button release, select |
| `mousemove` | Hover state, drag pan |
| `wheel` | Zoom (Star Map) |

### 22.2 Touch Input

| Event | Handler |
|---|---|
| `touchstart` | Map to mousedown |
| `touchend` | Map to mouseup |
| `touchmove` | Map to mousemove / detect pinch |
| Two-finger pinch | Zoom (Star Map) |

### 22.3 Keyboard Input

| Key | Action |
|---|---|
| `Escape` | Back / close modal |
| `Enter` | Confirm dialog |
| `M` | Open Star Map |
| `C` | Open Commander screen |
| `G` | Open Cargo |
| `Arrow Keys` | Pan Star Map |
| `+` / `-` | Zoom Star Map |

### 22.4 Input Manager Architecture

```javascript
InputManager = {
  queue: [],                 // buffered events
  mousePos: {x, y},
  heldKeys: Set,
  flush() {                  // called each frame, dispatched to SceneManager
    while (queue.length) { process(queue.shift()); }
  }
}
```

Buttons implement hit-testing:

```javascript
Button.hitTest(x, y) → Boolean  // x, y in logical canvas coords
```

---

## 23. Sound & Music

### 23.1 Sound Effects

| ID | Trigger |
|---|---|
| `laser_fire` | Weapon fires |
| `laser_hit` | Hit lands |
| `shield_hit` | Shield absorbs hit |
| `explosion_small` | Ship takes heavy damage |
| `explosion_large` | Ship destroyed |
| `warp_engage` | Warp begins |
| `warp_arrive` | Warp complete |
| `purchase` | Goods bought |
| `sell` | Goods sold |
| `alert` | Police encounter |
| `upgrade` | Equipment installed |
| `coin` | Credits received |
| `button_click` | UI button press |
| `error` | Invalid action |

### 23.2 Music

- Procedurally generated ambient space music via Web Audio API (oscillators + reverb)
- Tracks: `ambient_travel`, `combat_tense`, `market_upbeat`, `menu_title`
- Crossfade between tracks on scene change
- Mute/volume controls in settings

### 23.3 Audio Manager

```javascript
AudioManager = {
  sfxVolume: 0.8,
  musicVolume: 0.5,
  playSFX(id),
  playMusic(trackId, fadeMs),
  stopMusic(fadeMs),
  mute(),
  unmute(),
}
```

---

## 24. Save & Load System

### 24.1 Storage Overview

The game uses two complementary save mechanisms that work together:

| Mechanism | Storage | Capacity | Persistence |
|---|---|---|---|
| **Browser Slots** | `localStorage` | 3 named slots | Until browser data cleared |
| **File Export** | User's file system (`.json`) | Unlimited files | Permanent, user-managed |

Both mechanisms use identical JSON format so saves are fully interchangeable between them.

### 24.2 Browser Save Slots

- **3 slots** available, labelled Slot 1, Slot 2, Slot 3 in the UI
- **Slot 1 is the auto-save slot** — written automatically on every scene transition
- Slots 2 and 3 are manual saves only
- Each slot stores the full `GameState` object and a separate lightweight metadata entry for the slot picker

```javascript
// Full save
localStorage.setItem(`voidmerchant_save_${slot}`, JSON.stringify(GameState));

// Slot picker metadata (written alongside every save)
localStorage.setItem(`voidmerchant_meta_${slot}`, JSON.stringify({
  playerName: String,
  day:        Number,
  credits:    Number,
  difficulty: String,
  shipName:   String,
  timestamp:  Number,   // Date.now()
}));
```

### 24.3 Save File Format (JSON Export)

Exported save files are plain `.json` files. The top-level structure wraps the `GameState` with a small file header to allow validation and version checking on import:

```json
{
  "fileType": "voidmerchant_save",
  "fileVersion": "1.0",
  "exportedAt": 1720000000000,
  "meta": {
    "playerName": "Kira Voss",
    "day": 42,
    "credits": 84500,
    "difficulty": "normal",
    "shipName": "Surveyor",
    "timestamp": 1720000000000
  },
  "state": { ...full GameState object... }
}
```

- `fileType` — sentinel string; import rejects files missing this field
- `fileVersion` — matched against `SAVE_FILE_VERSION` constant; mismatch triggers a compatibility warning
- `exportedAt` — Unix timestamp in milliseconds; display-only, used in the import preview
- `meta` — mirrors the localStorage metadata object; shown in the import preview before committing
- `state` — the complete `GameState` object, identical to what is written to `localStorage`

### 24.4 Exporting a Save File

Export is available from the **Save / Load screen** (accessible from the Commander screen and the Main Menu).

**Flow:**
```
1. Player clicks "Export Save" next to any occupied browser slot
2. SaveManager.exportToFile(slot):
   a. Read GameState from localStorage slot
   b. Wrap in file envelope (fileType, fileVersion, exportedAt, meta, state)
   c. Serialise to JSON string
   d. Create a Blob: new Blob([jsonString], { type: 'application/json' })
   e. Create a temporary <a> element with download attribute
   f. Trigger programmatic click → browser Save File dialog
   g. Suggested filename: `voidmerchant_[playerName]_day[day]_[timestamp].json`
      e.g.  voidmerchant_KiraVoss_day42_1720000000.json
3. File is saved to user's chosen location
4. Toast notification: "Save exported successfully."
```

No server communication occurs — the entire operation is client-side.

### 24.5 Importing a Save File

Import is available from the same **Save / Load screen**.

**Flow:**
```
1. Player clicks "Import Save File"
2. A hidden <input type="file" accept=".json"> element is programmatically clicked
3. Player selects a .json file from their file system
4. SaveManager.importFromFile(file):
   a. Read file contents via FileReader.readAsText()
   b. Parse JSON — on parse error: show modal "Invalid save file."
   c. Validate fileType === "voidmerchant_save" — on fail: show modal "Not a Void Merchant save file."
   d. Check fileVersion compatibility — on mismatch: show modal
      "This save was created with version X. Load anyway?" [Yes] [Cancel]
   e. Display import preview modal:
      ┌─────────────────────────────────────┐
      │  Import Save File                   │
      │  ─────────────────────────────────  │
      │  Commander:  Kira Voss              │
      │  Day:        42                     │
      │  Credits:    84,500 cr              │
      │  Ship:       Surveyor               │
      │  Difficulty: Normal                 │
      │  Exported:   2024-07-03 14:22       │
      │  ─────────────────────────────────  │
      │  Load into slot:  [1] [2] [3]       │
      │                                     │
      │        [IMPORT]      [CANCEL]       │
      └─────────────────────────────────────┘
   f. Player selects target slot and confirms
   g. Write state to localStorage slot (overwrites existing; confirmation shown if slot occupied)
   h. Write metadata to localStorage meta slot
   i. Toast notification: "Save imported into Slot [N]."
   j. Slot picker refreshes to show newly imported save
5. Player may now load the imported save normally via the slot picker
```

### 24.6 Save Validation

Applied on both localStorage loads and file imports:

1. Parse JSON — reject on syntax error
2. Check `fileType` (imports only) and `version` fields
3. Validate all required top-level keys present (`seed`, `player`, `universeState`, `wormholes`, `day`, `difficulty`, etc.)
4. Validate nested critical fields (`player.ship.typeId`, `player.credits`, `universeState.systemStates`, etc.)
5. On version mismatch: warn and offer to attempt load anyway (best-effort forward compatibility)
6. On field missing: warn and substitute safe defaults where possible; log warnings to console
7. Run `Universe.generate(seed)` to rebuild all static system data in memory
8. Merge `universeState.systemStates` on top of generated data (visited flags, conditions, port inventories)
9. Rehydrate class instances from plain data (e.g. `new Ship(data.player.ship)`, `new ColonistGroup(data)`)

### 24.7 Save / Load UI Screen

A dedicated **Save / Load screen** is accessible from the Commander screen (in-game) and the Main Menu (without an active game).

**Layout:**

```
┌──────────────────────────────────────────┐
│  SAVE / LOAD                             │
├──────────────────────────────────────────┤
│  SLOT 1 — AUTO-SAVE                      │
│  Kira Voss · Day 42 · 84,500 cr          │
│  Surveyor · Normal · 3 Jul 2024 14:22    │
│  [LOAD]  [SAVE]  [EXPORT ↓]  [DELETE]    │
├──────────────────────────────────────────┤
│  SLOT 2                                  │
│  Kira Voss · Day 18 · 12,300 cr          │
│  Courier · Normal · 1 Jul 2024 09:05     │
│  [LOAD]  [SAVE]  [EXPORT ↓]  [DELETE]    │
├──────────────────────────────────────────┤
│  SLOT 3 — EMPTY                          │
│  [SAVE]                                  │
├──────────────────────────────────────────┤
│           [IMPORT SAVE FILE ↑]           │
└──────────────────────────────────────────┘
```

- **LOAD** — loads the slot into the active game (confirmation if currently in-game)
- **SAVE** — writes current game state to this slot (confirmation if overwriting)
- **EXPORT ↓** — triggers browser file download of the slot as a `.json` file
- **DELETE** — clears the slot from localStorage (double-confirmation required)
- **IMPORT SAVE FILE ↑** — opens the file picker; see Section 24.5

### 24.8 File Size Estimate

Because all static system data is regenerated from the seed at load time, saves are significantly leaner than storing the full universe:

| Component | Approx. Size |
|---|---|
| Seed + wormhole pairs (6 pairs) | < 1 KB |
| Per-system mutable state (71 × visited, condition, specialEventUsed) | ~5 KB |
| Port inventories (only visited systems, 12 goods each) | ~10–20 KB (scales with progress) |
| Colonist contracts (active only) | ~2 KB |
| Player data, ship, cargo, crew | ~5 KB |
| Quest state, news | ~3 KB |
| File envelope and metadata | < 1 KB |
| **Total (uncompressed JSON)** | **~25–35 KB per save file** |

This is roughly half the size of storing the full universe, and shrinks further early-game when few systems have been visited. Port inventories are the largest variable component — a fully explored galaxy will trend toward the higher estimate.

---

## 25. Settings & Options

```javascript
SettingsData = {
  sfxVolume: Number,          // 0.0 – 1.0
  musicVolume: Number,        // 0.0 – 1.0
  fullscreen: Boolean,
  hiDPI: Boolean,
  touchSensitivity: Number,   // 0.5 – 2.0
  showFPS: Boolean,           // dev/debug
  animationSpeed: Enum,       // SLOW | NORMAL | FAST | INSTANT
}
```

Settings are stored separately in `localStorage` (`voidmerchant_settings`) and persist across saves.

---

## 26. Scoring & Win/Loss Conditions

### 26.1 Win Conditions

| Condition | Trigger |
|---|---|
| **Retirement** | Player has purchased the private moon at Elysara and net worth ≥ difficulty threshold |
| **Quest Victory** | All main quests completed (if quest-completion mode enabled in settings) |

### 26.2 Loss Conditions

| Condition | Trigger |
|---|---|
| **Ship Destroyed** | Hull reaches 0 with no escape pod |
| **Bankruptcy** | Debt > net worth × 2 |
| **Reactor Explosion** | Reactor quest timer runs out |

### 26.3 Score Calculation (on retirement)

```
score = netWorth
      × difficultyMultiplier
      × (1 + kills.pirate * 0.01)
      / max(1, day)          // time bonus: finish faster for higher score
      × questBonus           // +10% per completed quest
```

Difficulty multipliers:

| Difficulty | Multiplier |
|---|---|
| Beginner | 0.5 |
| Easy | 0.75 |
| Normal | 1.0 |
| Hard | 1.5 |
| Impossible | 2.0 |

### 26.4 High Score Table

- Top 10 scores stored in `localStorage`
- Fields: name, score, net worth, days, difficulty, timestamp

---

## 27. Localisation

- All display strings stored in `data/strings_en.js`
- String keys referenced by ID throughout codebase
- Future: `strings_fr.js`, `strings_de.js`, etc.
- Canadian English defaults (e.g., "colour", "defence", "honour")

---

## 28. File & Folder Structure

```
/
├── index.html
├── style.css                   (minimal page chrome)
├── constants.js
├── main.js
├── engine/
│   ├── GameLoop.js
│   ├── Renderer.js
│   ├── InputManager.js
│   ├── AudioManager.js
│   ├── EventBus.js
│   ├── SaveManager.js
│   └── SceneManager.js
├── game/
│   ├── Universe.js
│   ├── SolarSystem.js
│   ├── Planet.js
│   ├── Player.js
│   ├── Ship.js
│   ├── ShipUpgradeManager.js   — Install/remove/validate ship upgrades
│   ├── TradeEngine.js
│   ├── CombatEngine.js
│   ├── EncounterEngine.js
│   ├── QuestManager.js
│   ├── BankManager.js
│   ├── ColonistManager.js      — Contract generation, fare calc, delivery resolution
│   ├── NewsEngine.js
│   └── ReputationManager.js
├── ui/
│   ├── screens/
│   │   ├── MainMenuScreen.js
│   │   ├── NewGameScreen.js
│   │   ├── SolarSystemScreen.js
│   │   ├── StarMapScreen.js
│   │   ├── MarketScreen.js
│   │   ├── ShipYardScreen.js
│   │   ├── EquipmentScreen.js
│   │   ├── ShipUpgradesScreen.js
│   │   ├── ColonialRegistryScreen.js
│   │   ├── PersonnelScreen.js
│   │   ├── BankScreen.js
│   │   ├── CommanderScreen.js
│   │   ├── CargoScreen.js
│   │   ├── EncounterScreen.js
│   │   ├── CombatScreen.js
│   │   ├── SettingsScreen.js
│   │   ├── SaveLoadScreen.js
│   │   └── HighScoreScreen.js
│   └── components/
│       ├── Button.js
│       ├── Panel.js
│       ├── ListView.js
│       ├── StarMap.js
│       ├── ProgressBar.js
│       ├── Tooltip.js
│       ├── Modal.js
│       └── TextScroller.js
├── data/
│   ├── ships.js
│   ├── shipUpgrades.js         — Ship upgrade type definitions
│   ├── equipment.js
│   ├── goods.js
│   ├── colonists.js            — Contract size tables, fare constants, group name templates
│   ├── events.js
│   ├── quests.js
│   ├── names.js
│   ├── techLevels.js
│   └── strings_en.js
└── utils/
    ├── Random.js
    ├── Math2D.js
    ├── Format.js
    └── Colours.js
```

---

## 29. Constants Reference

```javascript
// constants.js

// Universe
GALAXY_SIZE              = 71
MIN_SYSTEM_DIST          = 6
CLOSE_DIST               = 20
WORMHOLE_COUNT           = 6
WORMHOLE_FUEL_COST       = 10
WORMHOLE_DETECT_RANGE    = 2

// Economy
SHIP_TRADE_IN_RATE       = 0.90
EQUIP_SELL_RATE          = 0.50
INSURANCE_RATE           = 0.0025       // per day, of ship base price
LOAN_RATE_DEFAULT        = 0.10         // annual, applied daily
MOON_PRICE_DEFAULT       = 500_000

// Save System
SAVE_FILE_VERSION        = "1.0"        // must match fileVersion in exported JSON
SAVE_SLOTS               = 3            // number of localStorage save slots
SAVE_FILE_TYPE           = "voidmerchant_save"  // sentinel for import validation
LIFE_SUPPORT_COST        = 15_000       // install cost
LIFE_SUPPORT_BAY_OVERHEAD= 5            // bays permanently reserved
LIFE_SUPPORT_REMOVE_FEE  = 5_000        // labour cost to uninstall
UPGRADE_SELL_RATE        = 0.50         // fraction of install cost refunded on removal

// Colonist Transport
COLONIST_BASE_FARE       = 80           // credits per colonist (base)
COLONIST_DISTANCE_SCALE  = 2.0          // multiplier scaling factor over distance
MAX_COLONIST_RANGE       = 60           // max meaningful range for scaling
COLONIST_DEADLINE_FACTOR = 1.5          // days-per-parsec deadline factor
COLONIST_DEADLINE_BUFFER = 5            // flat extra days added to deadline
COLONIST_URGENCY_DAYS    = 5            // days-early threshold for urgency bonus
COLONIST_URGENCY_RATE    = 0.20         // fraction of fare paid as urgency bonus
COLONIST_LATE_PENALTY    = 0.50         // fraction of fare paid on late delivery
COLONIST_CONTRACT_EXPIRY = 7            // days before unaccepted contracts expire
COLONIST_ABANDON_RECORD  = -2           // police record change on contract abandonment
COLONIST_JETTISON_RECORD = -5           // police record change if colonists jettisoned/lost
COLONIST_DEATH_RECORD    = -10          // police record change if colonists die in ship loss
COLONIST_DELIVERY_RECORD = +1           // police record bonus for on-time delivery
COLONIST_UNRELIABLE_THRESHOLD = 3       // failed contracts before "Unreliable" flag

// Gadgets
OVERCLOCKER_DAMAGE_CHANCE    = 0.10     // chance per combat round of laser self-damage
OVERCLOCKER_SELF_DAMAGE      = 5        // HP of internal weapon damage per trigger
MINING_DRILL_ORE_MIN         = 1        // min free ore units per asteroid mining action
MINING_DRILL_ORE_MAX         = 5        // max free ore units per asteroid mining action
FUEL_RECYCLER_REDUCTION      = 0.10     // fraction of fuel cost saved per jump
SOLAR_COLLECTOR_CHANCE       = 0.05     // chance per jump to recover 1 fuel unit
SHIELD_CAPACITOR_REGEN_BONUS = 0.15     // fraction faster shield regen between rounds
SIGNAL_JAMMER_BYPASS_CHANCE  = 0.20     // chance to skip police cargo scan
ENGINE_TUNER_RANGE_BONUS     = 2        // extra parsecs of jump range
REINFORCED_STRUTS_HULL_BONUS = 0.05     // fraction increase to max hull HP
LIFE_SUPPORT_VENTS_DISCOUNT  = 0.15     // fraction reduction to daily mercenary wage

// Combat
BASE_HIT_CHANCE              = 50       // percent
SKILL_FACTOR                 = 5        // per skill point difference
SHIELD_FACTOR                = 0.75     // fraction of damage absorbed by shields
FLEE_FUEL_COST               = 1
BROADSIDE_FLEE_PENALTY       = 0.60     // flee chance multiplier when firing during Broadside
BROADSIDE_FLEE_CAP           = 75       // hard cap on flee chance during Broadside (percent)
COMBAT_AUTO_ROUND_DELAY      = 800      // ms pause between standing order auto-rounds

// Travel
WARP_SPEED               = 1            // parsecs per day
AUTO_REPAIR_RATE         = 1            // HP per parsec (if gadget equipped)

// Scoring
DIFFICULTY_MULT_BEGINNER = 0.5
DIFFICULTY_MULT_EASY     = 0.75
DIFFICULTY_MULT_NORMAL   = 1.0
DIFFICULTY_MULT_HARD     = 1.5
DIFFICULTY_MULT_IMPOSSIBL= 2.0

// Rendering
DESIGN_W                 = 480
DESIGN_H                 = 800
TARGET_FPS               = 60
```

---

## 30. Known Limitations & Future Work

| Item | Notes |
|---|---|
| Multiplayer | Not planned; single-player only |
| WebGL Renderer | Canvas 2D used initially; renderer abstraction allows future WebGL swap |
| Procedural Ship Art | Ships drawn procedurally; sprite sheet can be swapped in |
| Achievements | Not in v1.0 scope; EventBus hooks prepared |
| Cloud Save | Not in v1.0; LocalStorage only |
| Accessibility | Keyboard nav and screen reader support deferred to v1.1 |
| Localisation | English only in v1.0; string table architecture ready |
| Analytics | No telemetry in v1.0 |
| PWA / Offline | `manifest.json` + Service Worker deferred to v1.1 |
| Modding API | Architecture allows data-driven mods via JSON overrides in future |

---

*End of Void Merchant HTML5 Canvas Game Specification Document*  
*Version 1.0 — Prepared for development handoff*