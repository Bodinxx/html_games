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
  seed: Number,           // universe seed
  day: Number,            // game day (increments on warp)
  difficulty: String,     // "beginner" | "easy" | "normal" | "hard" | "impossible"
  player: PlayerData,
  universe: UniverseData,
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

### 5.4 UniverseData

```javascript
UniverseData = {
  systems: [SolarSystemData], // array of GALAXY_SIZE systems
  wormholes: [WormholePair],  // pairs of system indices
}
```

---

## 6. Universe & Solar Systems

### 6.1 Galaxy Generation

- The universe contains **GALAXY_SIZE = 71** named solar systems.
- Solar system positions are generated using a seeded PRNG spread across a **150 × 110** unit grid.
- A minimum distance of **MIN_SYSTEM_DIST = 6** units is enforced between any two systems.
- **CLOSE_DIST = 20** units: systems within this range are considered "nearby" (reachable with partial fuel).

### 6.2 SolarSystemData

```javascript
SolarSystemData = {
  index: Number,
  name: String,               // from names.js table (71 fixed canonical names)
  x: Number,                  // grid position
  y: Number,
  size: Enum,                 // TINY | SMALL | MEDIUM | LARGE | HUGE
  techLevel: Number,          // 0 (pre-agricultural) – 7 (hi-tech)
  government: Enum,           // see Section 6.4
  politicalActivity: Number,  // 0–7 (affects police presence)
  resources: Enum,            // see Section 6.5
  condition: Enum,            // NOSPECIALRESOURCES | WAR | PLAGUE | DROUGHT | COLD | CROP_FAILURE | BOREDOM | LACE_WEATHER | NONE
  police: Number,             // 0–7 police presence
  pirates: Number,            // 0–7 pirate density
  traders: Number,            // 0–7 trader density
  visited: Boolean,
  portInventory: [PortEntry], // per-good quantity and price, regenerated on visit
  specialEvent: Enum | null,  // one-time special event index
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

Combat is turn-based and round-resolved. Both sides fire simultaneously each round. The player may:

- **Attack** — Fire all weapons at target
- **Flee** — Attempt to escape (pilot skill vs enemy pilot)
- **Surrender** — Give up cargo (pirates), pay fine (police), or face destruction
- **Yield** — Specific context (e.g., police stop: allow search)

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

### 11.3 Flee Mechanics

```
fleeChance = 40 + (player.pilot - enemy.pilot) * 10
             + (voidVeil ? 30 : 0)
             + (emergencyThrusters ? 100 : 0)   // overrides all other factors; once per journey
             + (enemy.type == POLICE ? -10 : 0)
success = random(0,100) < clamp(fleeChance, 5, 95)
if success:
  fuelCost = FLEE_FUEL_COST (default 1)
  leaveEncounter()
else:
  enemy fires once more (flee penalty round)
```

### 11.4 Damage Display

- Player hull and shields shown as animated progress bars
- Enemy hull shown as a bar (no exact number displayed, just visual)
- Hit/miss flashes on the combatant sprite
- Combat log panel shows last 5 rounds of text events

### 11.5 Post-Combat

| Outcome | Result |
|---|---|
| Player wins vs Pirate | Loot cargo (partial), possible bounty reward |
| Player wins vs Police | Police record worsens significantly |
| Player wins vs Trader | Possible loot, record worsens slightly |
| Player flees | No loot, no record change |
| Player surrenders (pirate) | Cargo stolen (up to demand amount) |
| Player surrenders (police) | Fine paid, contraband confiscated |
| Player destroyed | Game over (or escape pod if equipped) |

### 11.6 Escape Pod

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
| Skill Increase | Tribble trainer, etc. | +1 to random skill |
| Tribbles | Merchant sells tribbles | Tribbles infest cargo |
| Pirate Attack (planet) | Ground raid | Cargo stolen |
| Good Prices Tip | Insider info | Highlight good on market |
| Weird Gas | Warp mishap | Random system damage |
| Gambling | Space casino | Win/lose credits |

### 18.2 The Kelvari Reactor Quest

- Available at specific system: `KELVARI_SYSTEM`
- Deliver radioactive reactor to `DRACOS_SYSTEM` within 20 days
- Reactor occupies 5 cargo bays and slowly damages hull (1 HP/day)
- Reward: 500,000 credits (scaled to difficulty)
- Failure: reactor explodes (ship destroyed, game over if no escape pod)

### 18.3 The Leviathan Quest (Space Monster)

- Unique encounter: Leviathan-class creature guarding valuable salvage
- Leviathan has extreme hull, no shields, powerful attack
- Reward: Leviathan hull plating (permanent hull upgrade)
- Only available once per game

### 18.4 The Phantom Quest

- Series of encounters tracking the stolen experimental ship "Phantom"
- Phantom has military laser, cloaking device, reflective shields
- Capturing it requires defeating it in combat
- Reward: military equipment + credits

### 18.5 The Princess Quest

- Deliver the kidnapped princess to her home planet
- Timed: must arrive within 30 days
- Reward: skill points distributed randomly, plus credits

### 18.6 The Sanctuary Retirement

- Player can purchase a retirement property (a private moon) for `MOON_PRICE = 500,000` credits
- Moon purchased at `ELYSARA_SYSTEM`
- Triggers the retirement win condition

### 18.7 Bottle of Skill / Bottle of Good

- `BOTTLE_OLD`: ancient bottle — +1 random skill
- `BOTTLE_GOOD`: full bottle — credits reward (`random(500, 2000)`)

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
- Buttons: **New Game**, **Load Game** (if save exists), **High Scores**, **Settings**, **About**
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
- Weapon animation: laser beams drawn from attacker to target
- Hit/miss text animation
- Combat log: last 5 lines scrolling
- Buttons: **Auto-Combat**, **Attack**, **Flee**
- Round counter

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

### 24.1 Save Slots

- 3 save slots available
- Auto-save on every scene transition (slot 0)
- Manual save available from Commander screen

### 24.2 Storage Format

```javascript
localStorage.setItem(`voidmerchant_save_${slot}`, JSON.stringify(GameState));
```

### 24.3 Save Validation

On load:
1. Parse JSON
2. Check `version` field — if incompatible, warn player and offer reset
3. Validate required fields present
4. Rehydrate class instances from plain data (e.g., `new Ship(data.ship)`)

### 24.4 Save Metadata (for slot picker)

```javascript
localStorage.setItem(`voidmerchant_meta_${slot}`, JSON.stringify({
  playerName: String,
  day: Number,
  credits: Number,
  difficulty: String,
  timestamp: Number,
}));
```

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

// Ship Upgrades
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
SKILL_FACTOR             = 5            // per skill point difference
SHIELD_FACTOR            = 0.75         // fraction of damage absorbed by shields
FLEE_FUEL_COST           = 1

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