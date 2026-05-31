# System Requirements Document (SRD)
## Project: Dense Word Puzzle Game

### 1. Document Overview
This document specifies the functional, technical, and interface requirements for a grid-based word puzzle game. The game utilizes thematic clues and answers to generate dense crossword-style layouts. Players can configure new sessions or resume existing ones using local or encrypted physical save states.

### 2. Core Data Architecture
Each puzzle question/item in the game's database must conform to the following schema:
| Attribute | Type | Constraints | Description |
|-----|-----|-----|-----|
| Theme | String | Required | The category or subject classification of the question. |
| Clue | String | Required | The actual prompt or question shown to the player. |
| Answer | String | Max 15 characters; A-Z only | The target solution string. |
| Length | Integer | Max 15; Derived | Automatically computed length of the Answer string. |
| Difficulty | Integer | Range: 0 to 5 | Complexity ranking. 1-5 are active ranks. 0 represents uncategorized items. |

### 3. Game Initialization & Setup Screen
Upon launching the application, the user must be presented with three primary paths to begin play:

#### 3.1 Load Physical Encrypted Save File
- **Action**: User uploads a file (e.g., `.dat` or `.puz`) from their local machine.
- **Requirement**: The system must decrypt the file using a lightweight standard (e.g., AES-GCM) with a hardcoded or game-instance key.
- **Validation**: If decryption fails or file structure is corrupt, display a non-blocking error modal and return to setup.

#### 3.2 Load Browser Local Storage
- **Action**: System checks the browser’s `localStorage` for an active game state.
- **Requirement**: If a saved game exists, enable a "*Resume Last Session*" button showing the date and completion percentage.

#### 3.3 Create New Game
- The setup screen must expose the following configuration controls:
  - **Theme Selection**: A multi-select component showing all available themes in the database.
    - **Rule**: The user must select at least 2 themes, up to "*All Themes*".
  - **Board Size**: A slider or selector allowing values from $10 \times 10$ to $15 \times 15$ grid squares.
  - **Difficulty Tuning**:
    - A discrete selector containing: `1`, `2`, `3`, `4`, `5`, and `Random`.
    - If a specific difficulty $D$ is selected, the board generator should prioritize questions where $\text{Difficulty} = D$. If there are insufficient questions, it can fall back to $D \pm 1$.
    - If `Random` is selected, difficulty parameters are ignored during pool filtering.

### 4. Board Generation Engine
  Once parameters are submitted, the engine compiles the game board in memory:
  - **Pool Filtering**: Filter the global puzzle database to match the chosen themes and difficulty parameters (filtering out `0` difficulty unless `Random` is chosen).
  - **Density Heuristic**: The algorithm must place words onto the $N \times N$ grid attempting to maximize intersections (shared letters) and minimize unused blank space.
    - It must use a recursive backtracking or greedy intersection placement approach.
    - **Constraint**: Generated puzzles must fit entirely within the selected $10 \times 10$ to $15 \times 15$ boundaries.
    - **Failure Fallback**: If a valid dense configuration cannot be generated with the chosen criteria within a 3-second timeout, the system must slightly loosen difficulty filters, alert the user quietly, and attempt generation again.

### 5. Play Mode & Interactive Mechanics
Once the board renders, the game enters **Play Mode**.

#### 5.1 Player Interface
- **Active Selection**: Clicking a cell highlights the entire corresponding horizontal or vertical word strip.
- **Clue Display**: The associated Clue and Theme are displayed prominently in a dedicated "Active Clue" panel.
- **Navigation**: Arrow keys navigate between cells; Spacebar toggles direction (Across vs. Down).
- **OneAcross Helper Integration**:
  - **Trigger**: A dedicated "*Get Help*" button must be positioned clearly within the "*Active Clue*" panel.
  - **Action**: Clicking this button opens a new browser window/tab targeting OneAcross with parameters built from the player's active state.
  - **Target URL Structure**: `https://www.oneacross.com/cgi-bin/s.cgi?c0={Clue}&p0={Pattern}`
    - `c0` parameter: The URI-encoded value of the currently selected Clue string.
    - `p0` parameter: A dynamically constructed pattern string representing the active word strip. Empty/unfilled cell slots must be replaced by a `?` wildcard. Filled-in letter cells must remain as-is (e.g., if a 5-letter word has "M" in the first position and "P" in the last, the constructed pattern must be `M??P`).

#### 5.2 Real-Time Validation & Highlight Modes
The game features live verification options and automatic cell-locking mechanics:
- **Incorrect Guess Highlighting:**
  - When activated (via toggle or triggered automatically on letter input), any letter typed by the user that does not match the solution key is instantly highlighted in a soft red hue (e.g., `#EF4444`).
  - Correct letters remain in a neutral or validated state (e.g., `#10B981` or standard text).
- **Correct Word Locking:**
  - **Trigger**: The instant an entire horizontal or vertical word strip is fully and correctly entered (matching the database solution key perfectly), the system must mark that word as Locked.
  - **Behaviour**: All cells belonging to that locked word are permanently protected against edits. The user cannot backspace or overwrite letters inside locked cells.
  - **Intersection Rule**: Shared letters at the intersection of a locked word and an active, incomplete word remain immutable. When typing or navigating through the incomplete word, the cursor must automatically skip over any locked intersecting cells.
  - **Visual Style**: Locked cells must transition to a highly readable, success-oriented visual state (e.g., a soft green background like #10B981 or standard text with a subtle padlock badge) to provide immediate positive feedback.

- **Completion Validation & Victory Celebration:**
  - **Trigger**: When all cells are filled, the engine verifies the complete board.
  - **Fireworks Animation**: If correct, the game must instantly render a full-screen canvas-based *Celebration Fireworks Animation*. Multi-colored particle explosions should burst across the screen behind or alongside the interactive elements without locking or causing performance degradation to the UI.
  - **Victory Overlay**: A "*Victory*" modal displays over the fireworks animation showing game statistics (time taken, hints used, etc.). The fireworks should continue to loop dynamically in the background until the user closes the modal or begins a new session.

### 6. Local and Encrypted Saving
- **Auto-Save**: The game must auto-serialize its state (board grid, entered letters, selected clues, elapsed time) to browser `localStorage` on every valid keystroke.
- **Manual Export (Encrypted File)**: A "*Save to File*" button allows downloading the current state.
  - The payload must contain the board layout, solution keys, player inputs, and elapsed time.
  - The system must serialize this metadata as JSON, encrypt it, and trigger a file download in `JSON` format (e.g., `wordpuzzle_save.puz`).
