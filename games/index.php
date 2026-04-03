<?php
// --- TRACKING LOGIC START ---
// This block handles the click tracking before any HTML is sent.
$trackingFile = 'click_counts.json';

if (isset($_GET['track'])) {
    $targetFile = basename($_GET['track']); // 'basename' prevents security issues (directory traversal)

    // Only process if the file actually exists
    if (file_exists($targetFile)) {
        // 1. Load existing stats
        $stats = [];
        if (file_exists($trackingFile)) {
            $jsonContent = file_get_contents($trackingFile);
            $stats = json_decode($jsonContent, true);
            if (!is_array($stats)) $stats = [];
        }

        // 2. Increment the counter for this specific file
        if (!isset($stats[$targetFile])) {
            $stats[$targetFile] = 0;
        }
        $stats[$targetFile]++;

        // 3. Save the data back to the text/json file
        file_put_contents($trackingFile, json_encode($stats, JSON_PRETTY_PRINT));

        // 4. Redirect the user to the actual game file
        header("Location: " . $targetFile);
        exit;
    }
}

// --- LOAD STATS FOR DISPLAY ---
// Load the counts so we can display them on the cards
$viewCounts = [];
if (file_exists($trackingFile)) {
    $viewCounts = json_decode(file_get_contents($trackingFile), true);
    if (!is_array($viewCounts)) $viewCounts = [];
}
// --- TRACKING LOGIC END ---

/**
 * Extract CATEGORY, DESCRIPTION, and TAGS from an HTML comment header.
 * Expected format:
 * <!--
 * CATEGORY: Image Tools
 * TAGS: convert, image, utility
 * DESCRIPTION: A tool to convert Image files into ICO files.
 * -->
 */
function read_file_meta(string $filePath): array {
    $default = [
        'category' => 'Uncategorized',
        'description' => '',
        'tags' => []
    ];

    // Read only the first chunk for speed.
    $fh = @fopen($filePath, 'r');
    if (!$fh) return $default;

    $head = (string)fread($fh, 8192);
    fclose($fh);

    // Find first HTML comment block
    if (!preg_match('/<!--(.*?)-->/s', $head, $m)) {
        return $default;
    }

    $comment = $m[1];

    // CATEGORY
    if (preg_match('/^\s*CATEGORY\s*:\s*(.+)\s*$/mi', $comment, $cm)) {
        $default['category'] = trim($cm[1]);
    }

    // DESCRIPTION
    if (preg_match('/^\s*DESCRIPTION\s*:\s*(.+)\s*$/mi', $comment, $dm)) {
        $default['description'] = trim($dm[1]);
    }

    // TAGS
    if (preg_match('/^\s*TAGS\s*:\s*(.+)\s*$/mi', $comment, $tm)) {
        $tagsRaw = trim($tm[1]);
        if (!empty($tagsRaw)) {
            // Split by comma, trim whitespace, remove empty entries
            $default['tags'] = array_filter(array_map('trim', explode(',', $tagsRaw)));
        }
    }

    return $default;
}

/**
 * Safe attribute value
 */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web-Mage HTML Apps and Games</title>
    <link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
    <link rel="manifest" href="favicon/site.webmanifest">
    <style>
        :root {
            --bg-color: #0f0f0f;
            --card-bg: #1a1a1a;
            --text-main: #f0f0f0;
            --text-sub: #a0a0a0;
            --accent: #00ff9d;
            --accent-dim: rgba(0, 255, 157, 0.1);
            --category-color: #00d2ff; /* Distinct color for categories */
            --tag-bg: #333;
            --tag-hover: #444;
            --footer-height: 60px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            padding-bottom: calc(var(--footer-height) + 20px);
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-weight: 300;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        /* --- FILTER BUTTON & OVERLAY --- */
        .filter-area {
            text-align: center;
            margin-bottom: 30px;
        }

        .filter-toggle-btn {
            background-color: var(--card-bg);
            border: 1px solid #444;
            color: var(--text-main);
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }

        .filter-toggle-btn:hover {
            border-color: var(--accent);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        
        .filter-toggle-btn.has-filter {
            background-color: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .tag-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .tag-overlay.open {
            display: flex;
            opacity: 1;
        }

        .tag-overlay-content {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 16px;
            max-width: 90%;
            width: 75%;
            border: 1px solid #444;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        .tag-overlay.open .tag-overlay-content {
            transform: scale(1);
        }

        .close-overlay {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            color: #888;
            font-size: 2rem;
            cursor: pointer;
            line-height: 1;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .close-overlay:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }

        .overlay-title {
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--accent);
            font-weight: 300;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* --- TAG CLOUD (Inside Overlay) --- */
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .cloud-tag {
            background-color: var(--tag-bg);
            color: #ccc;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            user-select: none;
            display: flex;
            align-items: center;
        }

        .cloud-tag:hover {
            background-color: var(--tag-hover);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Category Tag Style */
        .cloud-tag.category-tag {
            border: 1px solid var(--category-color);
            color: var(--category-color);
            background-color: rgba(0, 210, 255, 0.05);
            font-weight: 500;
        }

        .cloud-tag.category-tag:hover {
            background-color: var(--category-color);
            color: #000;
        }

        /* Active State (Overrides both) */
        .cloud-tag.active {
            background-color: var(--accent);
            color: #000;
            font-weight: 600;
            border-color: var(--accent);
            box-shadow: 0 0 10px var(--accent-dim);
        }

        /* --- CATEGORY BLOCKS --- */
        .gallery-container {
            display: flex;
            flex-direction: column; /* Stack category blocks */
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .category-block {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }

        .category-title {
            width: 100%;
            margin: 10px auto 12px auto;
            padding: 10px 14px;
            border-left: 4px solid var(--accent);
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 600;
            color: #fff;
        }

        /* --- CARD --- */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            border: 1px solid #333;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            width: 300px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.5);
            border-color: var(--accent);
        }

        /* Tooltip */
        .card[data-desc]:hover::after {
            content: attr(data-desc);
            position: absolute;
            left: 16px;
            right: 16px;
            top: 12px;
            z-index: 50;
            background: rgba(0,0,0,0.92);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 12px 30px rgba(0,0,0,0.6);
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            line-height: 1.25rem;
            color: #eaeaea;
            pointer-events: none;
            white-space: normal;
        }

        /* IMAGE CONTAINER */
        .thumb-container {
            width: 100%;
            aspect-ratio: 4 / 3;
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .thumb-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .card:hover .thumb-container img {
            transform: scale(1.05);
        }

        .placeholder-icon {
            font-size: 3rem;
            color: #333;
        }

        /* INFO SECTION */
        .info {
            padding: 15px 20px;
            border-top: 1px solid #2a2a2a;
            background: var(--card-bg);
            z-index: 2;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .name {
            font-weight: 600;
            font-size: 1.2rem;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: capitalize;
            color: #fff;
            margin-bottom: 8px;
        }

        .meta {
            font-size: 0.85rem;
            color: var(--text-sub);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .file-badge {
            background: #333;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            color: var(--accent);
            text-transform: uppercase;
        }

        .play-count {
            color: #ccc;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
        }

        /* TAGS IN CARD */
        .card-tags {
            margin-top: auto; /* Push to bottom if height varies */
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .card-tag {
            font-size: 0.7rem;
            background: #252525;
            color: #888;
            padding: 2px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: color 0.2s, background 0.2s;
            z-index: 10; /* Ensure clickable above the card link if needed */
        }

        .card-tag:hover {
            background: #444;
            color: #fff;
            text-decoration: underline;
        }

        /* FOOTER */
        .footer {
            position: fixed;
            left: 0;
            bottom: 0;
            width: 100%;
            text-align: center;
            background-color: #003399;
            padding: 10px 0;
            color: #e6eeff;
            font-style: italic;
            font-size: 9pt;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }

        .footer a {
            color: #FFFF00;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <img src="favicon/android-chrome-192x192.png" style="position: fixed; top: 0; left: 0; z-index: -1; max-width: 150px;">
    <img src="favicon/android-chrome-192x192.png" style="position: fixed; top: 0; right: 0; z-index: -1; max-width: 150px;">
    
    <h1>Web-Mage HTML Games and Apps</h1>

    <?php
    // 1. SCAN EVERYTHING
    $allFiles = scandir('.');
    $displayFiles = [];

    // 2. CONFIG: What to Ignore
    $ignoredFiles = [
        'index.php',
        'games_to_make.html',
        'click_counts.json', 
        'counter.php',
        '.', '..',
        '.DS_Store',
        'Thumbs.db'
    ];

    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp'];
    $excludedExtensions = ['inc', 'json', 'csv'];

    // 3. FILTER
    foreach ($allFiles as $file) {
        if (in_array($file, $ignoredFiles, true)) continue;
        if (is_dir($file)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($ext, $imageExtensions, true)) continue;
        if (in_array($ext, $excludedExtensions, true)) continue;

        $displayFiles[] = $file;
    }

    // 4. SORT
    natcasesort($displayFiles);

    // 5. BUILD CATEGORY GROUPS & COLLECT TAGS
    $grouped = []; // [category => [items...]]
    
    // Separate arrays to allow explicit sorting: Categories first, then others.
    $categoryTags = []; 
    $rawTags = []; 

    foreach ($displayFiles as $file) {
        $meta = read_file_meta($file);
        $category = $meta['category'] ?: 'Uncategorized';
        
        // --- COLLECT CATEGORIES ---
        $catLower = mb_strtolower($category);
        if (!isset($categoryTags[$catLower])) {
            $categoryTags[$catLower] = [
                'display' => $category, 
                'count' => 0,
                'type' => 'category' // Mark as category
            ];
        }
        $categoryTags[$catLower]['count']++;
        
        // --- COLLECT RAW TAGS ---
        if (!empty($meta['tags'])) {
            foreach ($meta['tags'] as $tag) {
                $tagLower = mb_strtolower($tag);
                if (!isset($rawTags[$tagLower])) {
                    $rawTags[$tagLower] = [
                        'display' => $tag, 
                        'count' => 0,
                        'type' => 'tag'
                    ];
                }
                $rawTags[$tagLower]['count']++;
            }
        }

        if (!isset($grouped[$category])) $grouped[$category] = [];
        $grouped[$category][] = [
            'file' => $file,
            'meta' => $meta,
        ];
    }

    // Sort categories A-Z for the main page display
    uksort($grouped, function($a, $b) {
        return strcasecmp($a, $b);
    });

    // --- MERGE TAGS LOGIC ---
    // 1. Sort independent lists alphabetically
    ksort($categoryTags);
    ksort($rawTags);

    // 2. Consolidate: If a tag exists as a category, add its count to the category and remove from tags list.
    $finalRegularTags = [];
    foreach ($rawTags as $key => $data) {
        if (isset($categoryTags[$key])) {
            $categoryTags[$key]['count'] += $data['count'];
        } else {
            $finalRegularTags[$key] = $data;
        }
    }

    // 3. Merge: Categories FIRST, then Regular Tags
    // using array_merge to re-index is fine here as we want a sequential list for the loop
    $globalTags = array_merge($categoryTags, $finalRegularTags);
    ?>

    <!-- TAG FILTER UI -->
    <?php if (!empty($globalTags)): ?>
    <div class="filter-area">
        <button id="filter-btn" class="filter-toggle-btn">
            <span class="icon">🏷️</span> 
            <span id="filter-text">Filter by Tag</span>
        </button>
    </div>

    <div id="tag-overlay" class="tag-overlay">
        <div class="tag-overlay-content">
            <button id="close-overlay" class="close-overlay">&times;</button>
            <h2 class="overlay-title">Select a Tag</h2>
            <div class="tag-cloud">
                <?php foreach ($globalTags as $tInfo): 
                    $isCategory = ($tInfo['type'] === 'category');
                    $extraClass = $isCategory ? ' category-tag' : '';
                    $displayType = $isCategory ? 'Category' : 'Tag';
                ?>
                    <div class="cloud-tag<?php echo $extraClass; ?>" 
                         data-tag="<?php echo h($tInfo['display']); ?>"
                         title="<?php echo $displayType; ?>">
                        <?php echo h($tInfo['display']); ?> 
                        <span style="opacity: 0.5; font-size: 0.8em; margin-left: 4px;">
                            (<?php echo $tInfo['count']; ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="gallery-container">
        <?php
        $renderedAny = false;
        foreach ($grouped as $category => $items) {
            if (empty($items)) continue;
            $renderedAny = true;

            // Sort items by filename
            usort($items, function($x, $y) {
                return strcasecmp($x['file'], $y['file']);
            });
            ?>

            <div class="category-block">
                <div class="category-title"><?php echo h($category); ?></div>

                <?php
                foreach ($items as $item) {
                    $file = $item['file'];
                    $description = (string)($item['meta']['description'] ?? '');
                    $tags = $item['meta']['tags'] ?? [];
                    
                    // --- COMBINE TAGS + CATEGORY FOR FILTERING (BUT NOT DISPLAY) ---
                    $filterTags = $tags; 
                    // Add category to the list used for filtering so checking the category tag works
                    $filterTags[] = $category;
                    // Join for data attribute
                    $tagsString = implode(',', array_unique($filterTags));

                    $filenameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
                    $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

                    // MATCHING IMAGE
                    $imagePath = null;
                    $imgExtsToCheck = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    foreach ($imgExtsToCheck as $imgExt) {
                        if (file_exists($filenameWithoutExt . '.' . $imgExt)) {
                            $imagePath = $filenameWithoutExt . '.' . $imgExt;
                            break;
                        }
                    }

                    // STATS
                    $plays = isset($viewCounts[$file]) ? (int)$viewCounts[$file] : 0;
                    $lastModified = date("M Y", filemtime($file));

                    $descAttr = trim($description) !== '' ? ' data-desc="' . h($description) . '"' : '';
                    ?>

                    <a href="?track=<?php echo urlencode($file); ?>" 
                       class="card" 
                       <?php echo $descAttr; ?>
                       data-tags="<?php echo h($tagsString); ?>">
                        
                        <div class="thumb-container">
                            <?php if ($imagePath): ?>
                                <img src="<?php echo h($imagePath); ?>" alt="<?php echo h($filenameWithoutExt); ?>">
                            <?php else: ?>
                                <span class="placeholder-icon">
                                    <?php
                                        if($fileExtension == 'php') echo '🐘';
                                        elseif($fileExtension == 'html') echo '🌐';
                                        elseif($fileExtension == 'pdf') echo '📄';
                                        elseif($fileExtension == 'zip') echo '📦';
                                        else echo '📁';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="info">
                            <span class="name">
                                <?php echo h(str_replace(['-', '_'], ' ', $filenameWithoutExt)); ?>
                            </span>
                            
                            <div class="meta">
                                <span class="file-badge"><?php echo h($fileExtension); ?></span>
                                <div style="text-align: right;">
                                    <span class="play-count">
                                        <?php if($plays > 0): ?>
                                            <span style="color: var(--accent);">▶</span> <?php echo $plays; ?> plays
                                        <?php else: ?>
                                            <span style="color: var(--accent);">New!</span>
                                        <?php endif; ?>
                                    </span>
                                    <span style="display: block; font-size: 0.7rem; opacity: 0.6;">
                                        <?php echo h($lastModified); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- TAGS DISPLAY ON CARD (Does not include category) -->
                            <?php if (!empty($tags)): ?>
                            <div class="card-tags">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="card-tag" data-tag="<?php echo h($tag); ?>"><?php echo h($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>

                <?php
                } // end foreach items
                ?>
            </div> <!-- end category-block -->
            <?php
        } // end foreach grouped

        if (!$renderedAny) {
            echo '<p style="color: #666; width: 100%; text-align: center;">No valid files found in this directory.</p>';
        }
        ?>
    </div>

    <div class="footer">
        Created by Ryan Krawchuk via Agentic Coding (Gemini & ChatGPT). These assets are entirely open-source, copyright-free, and available for unlimited distribution.<br>
        <a href="./games_to_make.html">Games to Make</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.card');
            const categoryBlocks = document.querySelectorAll('.category-block');
            const cloudTags = document.querySelectorAll('.cloud-tag');
            
            const filterBtn = document.getElementById('filter-btn');
            const filterText = document.getElementById('filter-text');
            const overlay = document.getElementById('tag-overlay');
            const closeBtn = document.getElementById('close-overlay');

            let activeFilter = null;

            // --- OVERLAY LOGIC ---
            function openOverlay() {
                if(overlay) overlay.classList.add('open');
            }

            function closeOverlay() {
                if(overlay) overlay.classList.remove('open');
            }

            if(filterBtn) {
                filterBtn.addEventListener('click', openOverlay);
            }
            if(closeBtn) {
                closeBtn.addEventListener('click', closeOverlay);
            }
            if(overlay) {
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) closeOverlay();
                });
            }

            // --- FILTER LOGIC ---
            function applyFilter(tagName) {
                // Toggle Logic
                if (activeFilter === tagName) {
                    activeFilter = null; // Reset
                } else {
                    activeFilter = tagName;
                }

                // Update Button State
                if (filterBtn && filterText) {
                    if (activeFilter) {
                        filterBtn.classList.add('has-filter');
                        filterText.textContent = 'Tag: ' + activeFilter;
                    } else {
                        filterBtn.classList.remove('has-filter');
                        filterText.textContent = 'Filter by Tag';
                    }
                }

                // Update Cloud UI (Active State)
                cloudTags.forEach(ct => {
                    if (activeFilter && ct.dataset.tag.toLowerCase() === activeFilter.toLowerCase()) {
                        ct.classList.add('active');
                    } else {
                        ct.classList.remove('active');
                    }
                });

                // Show/Hide Cards
                cards.forEach(card => {
                    if (!activeFilter) {
                        card.style.display = ''; 
                        return;
                    }
                    const cardTags = (card.dataset.tags || '').split(',').map(t => t.trim().toLowerCase());
                    if (cardTags.includes(activeFilter.toLowerCase())) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Hide Empty Categories
                categoryBlocks.forEach(block => {
                    const visibleCard = block.querySelector('.card:not([style*="display: none"])');
                    block.style.display = visibleCard ? '' : 'none';
                });

                // Close overlay on selection
                closeOverlay();
            }

            // Event Listeners for Tags (Cloud)
            cloudTags.forEach(btn => {
                btn.addEventListener('click', () => applyFilter(btn.dataset.tag));
            });

            // Event Listeners for Tags (Cards)
            document.querySelectorAll('.card-tag').forEach(tagSpan => {
                tagSpan.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    applyFilter(tagSpan.dataset.tag);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });
        });
    </script>
</body>
</html>