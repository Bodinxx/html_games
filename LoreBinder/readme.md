# Project: LoreBinder — TTRPG Document Authoring & Publishing Platform

## System Requirements Specification (SRS)

### 1. Project Overview & Scope
This document details the functional, non-functional, and system requirements for *LoreBinder*, a web-based, Markdown-driven publishing platform tailored for tabletop roleplaying game (TTRPG) creators. Inspired by tools like GMBinder and Homebrewery, LoreBinder allows authors to write content in a highly optimized Markdown editor, customize layout structures using CSS, organize assets and sub-documents in a visual file tree, and compile documents into print-ready, professionally styled PDFs or digital books.

### 2. Core Functional Requirements

#### 2.1 Project Management & File Tree Navigation
The system must present a workspace containing a persistent, nested project hierarchy sidebar to allow creators to organize massive compendiums logically.

- **Hierarchical File Tree**:
  - Authors must be able to create, rename, delete, and nest documents and folders to an arbitrary depth (minimum of 5 levels).
  - A drag-and-drop visual interface must allow intuitive restructuring of the file tree.
- **Compilation Sequencing**:
  - The visual order of files in the tree determines the chronological compiling order when exporting the entire project as a single cohesive book.
  - Authors can toggle individual files or folders to be "Excluded from Compilation" while retaining them in the workspace.
- **Active Document Workspace**:
  - Double-clicking or selecting a file in the tree opens it in the active editor workspace.
  - The workspace must support multiple open tabs for rapid context switching.

### 2.2 Asset & Image Management
To minimize external dependencies, each project must host and serve its own localized asset directory.

- **Isolated Project Asset Folder**:
  - Every project possesses an independent directory for user-uploaded media (images, vector graphics).
  - Supported file formats: `PNG`, `JPEG`, `WEBP`, `SVG`, and `GIF`.
  - Maximum file upload size default is set to `10MB` per image, with an automated server-side compression pipeline targeting highly optimized web-safe outputs.
- **Relative Path Referencing**:
  - Uploaded assets must be referencable within the Markdown files using clean relative paths (e.g., `![Monster Art](/assets/monster_art.webp)`).
- **Media Gallery UI**:
  - A visual drawer within the editor containing all uploaded project assets.
  - Features a "Copy Markdown Link" button, quick delete, renaming, and basic alt-text configuration.

### 2.3 Visual Layout & Image Wrapping Engine
Professional TTRPG books rely heavily on intricate layout rules, text flow, and blended artwork. The renderer must support advanced image alignment techniques natively.

- **Text Wrapping Class Extensions**:
  - The Markdown parser must support custom block/inline class extensions (e.g., using syntax like `::: wrap-left` or custom HTML/CSS attributes) to wrap text around adjacent images seamlessly.
- **Advanced Layout Presets**:
  - **Float Left/Right**: Images float to a page column margin, with text wrapping around them naturally.
  - **Full-Page/Half-Page Spreads**: Absolute positioning hooks that clamp images to background layers, ignoring standard text margins.
  - **Footer/Header Banners**: Automatic snapping to top or bottom regions of the rendering grid.
- **Aesthetic Edge Blending**:
    - The rendering engine must provide pre-built CSS utility classes for watercolor/ink edge blending using CSS mask-image properties, enabling uploaded images to blend smoothly with book page textures.

### 2.4 High-Performance Tabbed Dual-Mode Editor
Rather than a simple passive text previewer or standard split-pane, the platform requires a dedicated, bi-directionally synchronized tabbed editor designed for both fast visual writing and detailed code control.

- **Tabbed Interface Layout (Write/Code Modes)**:
  - The system presents two distinct, switchable tabs at the top of the workspace: **Visual Layout (WYSIWYG)** and **Plain Text (Markdown Code)**.
  - Both views are fully interactive and editable, functioning as twin gateways to the same core document.
- **Tab A: Interactive Visual Layout Editor (WYSIWYG Mode)**:
  - Displays the document styled perfectly to its final page layout (including page margins, background textures, column boundaries, custom fonts, and inline styles).
  - Authors can type directly onto the pages. The visual engine processes text insertions, formatting shortcuts, and media wrapping in real-time.
  - Provides quick-click formatting toolbars, table expansion helpers, and block manipulation controls.
- **Tab B: Plain Text / Code Editor (Markdown Mode)**:
  - Displays the document as raw, uncompiled Markdown markup mixed with custom curly-brace block styling templates.
  - Features a high-performance raw editor with full syntax highlighting for Markdown structures, HTML elements, and nested inline CSS.
  - Optimized for bulk text modification, manual system-component injection, and complex custom styling declarations.
- **Bi-directional Live Synchronization & State Engine**:
  - Any edits, formatting modifications, or structural changes applied in the Visual Layout Tab instantly compile into the corresponding Markdown syntax behind the scenes, updating the Plain Text Tab seamlessly.
  - Any manual code adjustments, styling overrides, or structural additions written in the Plain Text Tab instantly compile and layout-render on the page grids when switching back to the *Visual Layout Tab*.
  - The cursor position, viewport scroll offsets, and undo/redo history stacks are synchronized across both tab states to ensure zero progress or focus loss when toggling between editing modes.
- **Global Autocomplete & Assist**:
  - Autocomplete lists are supported in both views (suggesting markdown elements, variable tokens, CSS selectors, and custom TTRPG templates).

### 2.5 Dynamic CSS & Styling System
The platform must provide developers and authors with maximum control over the visual presentation of their documents.

- **Base Style Injection**:
  - The system loads a core, read-only system stylesheet containing default themes (e.g., standard fantasy parchment, sci-fi sleek, clean modern) containing typography rules, grid layouts, page sizes, and print margins.
- **Project-Wide CSS Overrides**:
  - Every project contains a global `style.css` file accessible via a dedicated CSS Editor panel.
  - Custom CSS declared in this file is loaded *after* the base style, giving the author absolute authority to override any visual property (fonts, colors, layouts, table borders).
- **Page-Specific Rules**:
  - Support for scoped styles or page-break targets, allowing authors to style specific pages differently (e.g., blank backgrounds for cover pages, specialized margins for index pages).

### 2.6 Automation: Smart Autosave System
To prevent loss of work in an browser-based environment, the editor must feature a passive, non-intrusive auto-save routine.

- **Idle Detection Engine**:
  - The system actively monitors keypresses, mouse clicks, and active cursor positions within the editor workspace.
  - A state manager triggers a silent, background save operation precisely 5 seconds after the last input or cursor movement is detected.
- **Visual Status Indicator**:
  - A subtle status bar in the bottom corner of the UI displays the current state:
    - `● Saved` (All changes stored safely in database)
    - `○ Editing...` (Active input detected, waiting for idle)
    - `↻ Autosaving...` (Saving payload over API)
- **Backup and Rollback**:
  - In the event of network disruption, the autosave routine falls back to storing changes in browser `localStorage` or `IndexedDB`, prompting synchronization once connection resumes.

### 2.7 Cross-Document Link Resolution Engine
To allow the creation of cohesive, multi-document books or linked campaign modules, the system must process internal and external routing intelligently.

- **Universal Internal Referencing**:
  - The compiler and reader must support dynamic links referencing sections across the entire project structure.
- **Link Specification Matrix**:
  | Link Type | Direct Target | Syntax Specification (Example) | Expected System Action |
  |-----|-----|-----|-----|
  | Local Anchor | Header inside the same active file. | `[Combat Rules](#combat-rules)` | Scrolls view to local element ID (`#combat-rules`). |
  | Cross-Doc File | A separate file inside the project tree. | `[Spells List](doc:spells_list)` | Loads the targeted document context into the workspace/viewer. |
  | Cross-Doc Anchor | A specific section inside a separate file. | `[Fireball Stats](doc:spells_list#fireball)` | Loads target document and immediately scrolls to element ID. |
- **Link Integrity Validator (Link Checker)**:
  - The platform must automatically run a link validation pass whenever a project is compiled, identifying and highlighting broken relative references or missing document targets for the author.
  - If a document filename is changed, the system should prompt the author to automatically update all references across the project or automatically resolve the pathing via a unique internal document ID.

### 2.8 Page Geometry, Headers, Footers & Auto-Numbering
To produce industry-standard physical books, LoreBinder must handle complex page geometries, running headers/footers, and flexible page numbering dynamically during both preview and PDF rendering phases.

- **Dynamic Auto-Page Numbering**:
  - The compilation and rendering engines must leverage CSS Page Model counters (`counter-increment: page;`) to track page numbers programmatically.
  - Numbering Styles: Authors can toggle page number numbering formats globally or per section (e.g., standard Arabic `1, 2, 3`, lowercase Roman `i, ii, iii` for indexes/prefaces, or alphabetical `A, B, C` for appendixes).
  - **Counter Control & Suppression**: Authors must have the ability to suppress page numbers on specific pages (e.g., book covers, table of contents, full-bleed artwork inserts) using custom Markdown class declarations (e.g., `::: cover-page` or `::: no-page-number`). Authors must also be able to reset or shift the starting counter value (e.g., beginning the numerical page `1` on what is physically page `3` of the PDF).
- **Alternating Spreads (Left / Right Page Geometry)**:
  - The document renderer must distinguish between "Left" (even) and "Right" (odd) pages to support physical book layout design (facing pages).
  - **Margins & Guttering**: The template system must automatically mirror inner/outer page margins to account for page gutters when binding a physical book (e.g., a larger left-hand margin on right pages and a larger right-hand margin on left pages).
- **Running Headers & Footers**:
  - The CSS template system must auto-populate headers and footers dynamically based on metadata and page context.
  - **Header Rules**: Left pages display the overall Book/Project Title in the outer header margin, while Right pages display the current active top-level section/chapter name.
  - **Footer Rules**: Page numbers must alternate on the outer bottom edges (bottom-left corner for Left pages, bottom-right corner for Right pages), with custom accent lines or graphic borders (parchment flourishes, metallic bars) framing the text.
  - **Variable Extraction**: The rendering parser must extract headers from heading tags (such as standard `H1` or `H2` titles) dynamically, allowing the running header text to update automatically as chapters transition without forcing the user to declare them manually on each page.

### 2.9 Modern Layout Syntax: Block & Inline Curly Braces
To prevent source files from becoming cluttered with standard HTML tags (such as `<div>`, `<span>`, and manually written attributes), the renderer must interpret a simplified block/inline wrapper syntax.

- **Block Container Syntax**:
  - Authors can group paragraphs, tables, or lists into specialized layouts using double curly braces:
    ```
    {{monster-stat-block,border-color:crimson,box-shadow:none
    ### Fire Drake
    *Medium dragon, chaotic evil*
    ...
    }}
    ```
  - The parser interprets this construct and compiles it directly into `<div class="monster-stat-block" style="border-color: crimson; box-shadow: none;">...</div>`
- **Inline Span Syntax**:
  - Authors can inject scoped styling rules into normal sentences dynamically using inline curly braces (e.g., `This spell deals {{damage-text,color:red 4d6 fire damage}} to targets.`).

### 2.10 Built-in Snippet Library & Component Library
To speed up document styling and reduce the learning curve for new authors, the workspace must have a comprehensive snippets template library.

- **Integrated Template Snippet Drawer**:
  - A slide-out drawer or overlay providing categorized, click-to-inject structural presets:
    - **Structural elements**: Cover Page, Table of Contents, 2-Column Text Divider, Column Breaks, Page Breaks.
    - **TTRPG Specifics**: Class Progression Tables, Monster Stat Blocks (standard & wide), Note Boxes/Sidebars (parchment style, sci-fi computer log style, cursed artifact scroll), Spell descriptions.
    - **Aesthetics**: High-resolution watercolor asset borders and ink-bleed masking tags.
  - **Context-Aware Autocomplete**:
    - While typing in the text editor, pressing a keyboard shortcut (e.g., `Ctrl+Space`) launches an auto-suggest pop-up matching snippet titles or common layout elements, allowing rapid injection without opening the visual drawer.

### 2.11 Collaborative Authorship & Access Control
Large TTRPG books are frequently collaborative efforts. The system must support shared editing and viewing permissions among multiple users.

- **Author Roles & Access Control List (ACL)**:
  - Every project is owned by a single **Primary Creator** who retains administrative control.
  - The owner can invite other registered users to join the project as:
    - **Co-Author (Read/Write)**: Can edit document files, custom stylesheets, and upload assets, but cannot delete the project or alter root permissions.
    - **Reviewer (Read/Comment)**: Has read access to draft documents and can drop visual annotations or comments directly in the editor workspace, but cannot modify text or files.
  - **State Locking & Collision Avoidance**:
    - To prevent merge conflicts during concurrent edits, the file-tree blocks active editing. If User A is currently editing `chapter_1.md`, a lock icon is placed on the tree, rendering that file "Read-Only" for User B with a banner stating: "*Currently being edited by User A.*"

### 2.12 Document-Level Variables & Token Substitutions
To simplify editing wide-ranging rulebooks, the engine must support dynamic variables, letting authors declare reusable metadata keys globally.

- **Variable Declarations**:
  - An author can define key-value document variables at the top of a document or inside a global configuration panel:
    ```
    [var:campaign_setting="The Iron Domains"]
    [var:major_villain="Lord Malakor"]
    ```
- **Token Replacement Engine**:
  - Throughout the Markdown source, inserting the variables (e.g., Welcome to `[var:campaign_setting]`! Here rules `[var:major_villain]`.) forces the parser to replace the tag with its value during live rendering and compilation.
  - If a variable is updated globally, the changes cascade through all sub-files in the project tree automatically.

### 2.13 High-Quality Export Profiles & Print-Optimization Engine
Compiling digital drafts versus producing print-ready, high-resolution books require different rendering pipelines. The compiler must feature toggleable optimizations.

- **Digital vs. Physical Print Profiles**:
  - **Ink-Saver Mode**: A global toggle that swaps out highly textured, colored parchment backgrounds for clean, high-contrast white pages with grayscale text and vector borders. This drastically reduces ink consumption when printing drafts at home.
  - **Standard Sizing Profiles**: Dropdown configurations to instantly snap page geometry layout and styling to standard US Letter (`8.5 x 11` inches) or international A4 page footprints (`210 x 297` mm), automatically recalibrating column math.
- **Web-Optimized vs. High-Res PDF compilation**:
  - **Web Draft PDF**: Automatically compresses all embedded PNG and JPEG image assets to 150 DPI and applies moderate compression ratios to yield small, easily shared PDF files.
  - **Print-Ready PDF**: Outputs uncompressed vector elements and upscale images (targeting 300+ DPI), embedding print crop marks and color bleed profiles (CMYK compatibility styling guidelines) inside the PDF payload.

### 2.14 Git-Style Versioning & Publishing Pipeline
Authors must have the ability to continuously work on live, experimental edits without breaking the active version currently being shared with readers.

- **Working Drafts vs. Published Releases**:
  - A document has two core URLs: an Edit Link (restricted to authorized creators) and a public Share Link (read-only).
  - The public Share Link points to the most recent explicitly published snapshot, while the active editor reflects the current, live Working Draft.
- **Manual Snapshots & Release Log**:
  - Authors can manually trigger a "Publish Release Version" (e.g., Tagging a file *Version 1.2*). The system locks that state, updates the public Share Link, and provides a changelog field.
  - A revision timeline panel allows authors to inspect previous published versions, view diff comparisons side-by-side, and restore a project to a historical state if an edit corrupts their design.

## 3. Non-Functional Requirements

### 3.1 Performance & Rendering Speed
- The Markdown parser must render preview updates within 150ms of input pause to avoid layout lag.
- Large projects (100+ pages) must compile into high-quality PDFs within 10 seconds.

### 3.2 Reliability & Fault Tolerance
- No single save failure should corrupt the working state of a document.
- Version snapshot history should be automatically generated once every 30 minutes of active editing, separate from the 5-second idle autosave.

### 3.3 Usability & Print Accuracy
- The preview panel must use a CSS Paged Media layout to represent visual page breaks exactly as they will look when printed or exported to PDF.
