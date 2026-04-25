<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Space Trader Command</title>
  <link rel="stylesheet" href="public/css/styles.css" />
</head>
<body>
  <main class="app">
    <header>
      <h1>Space Trader Command Deck</h1>
      <p>Explore 100 systems, trade cargo, and survive tactical space combat.</p>
    </header>

    <section id="stats" class="stats"></section>

    <section class="panel">
      <h2>Galaxy Navigation Map</h2>
      <p>Click any highlighted system within your current fuel range to jump.</p>
      <canvas id="galaxy-map" width="1000" height="1000"></canvas>
      <p class="hint" id="map-hint"></p>
    </section>

    <section class="panel combat-panel" id="combat-panel">
      <h2>Combat Screen</h2>
      <canvas id="combat-canvas" width="900" height="300"></canvas>
      <div class="combat-controls">
        <button id="attack-btn">Attack</button>
        <button id="brace-btn">Brace + Fire</button>
        <button id="flee-btn">Attempt Flee</button>
      </div>
    </section>

    <section class="columns">
      <section class="panel">
        <h2>Market</h2>
        <table>
          <thead>
            <tr><th>Good</th><th>Price</th><th>Owned</th><th>Trade</th></tr>
          </thead>
          <tbody id="market-rows"></tbody>
        </table>
      </section>

      <section class="panel">
        <h2>Ship Services</h2>
        <div class="controls">
          <label for="fuel-amount">Fuel Units</label>
          <input id="fuel-amount" type="number" min="1" value="5" />
          <button id="refuel-btn">Refuel</button>
        </div>

        <h3>Upgrades</h3>
        <div id="upgrade-list"></div>
        <button id="reset-btn" class="danger">Reset Save</button>
      </section>
    </section>

    <p id="message" class="message"></p>
  </main>

  <script src="public/js/game.js"></script>
</body>
</html>
