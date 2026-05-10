<?php
// viewguide.php  -  Browse, search, and filter DIY troubleshooting guides
$connection = new mysqli("localhost", "root", "", "miniproject");
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Ensure all expected columns exist (safe to run every time)
$connection->query("ALTER TABLE diy ADD COLUMN IF NOT EXISTS problem VARCHAR(255) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE diy ADD COLUMN IF NOT EXISTS troubleshooting TEXT");
$connection->query("ALTER TABLE diy ADD COLUMN IF NOT EXISTS category VARCHAR(60) NOT NULL DEFAULT 'Other'");

// ── Read filter params ─────────────────────────────────────────────────────
$search   = trim($_GET["search"]   ?? "");
$catFilter = trim($_GET["category"] ?? "");
$tgidLookup = trim($_GET["tgid"]   ?? "");

// ── Category map ───────────────────────────────────────────────────────────
$catIcons = [
    "Network"  => "&#127760;",
    "Hardware" => "&#128421;",
    "Software" => "&#128187;",
    "Printer"  => "&#128424;",
    "Email"    => "&#128140;",
    "Security" => "&#128274;",
    "Other"    => "&#128196;",
];

// ── Highlight helper ───────────────────────────────────────────────────────
function hl($text, $kw) {
    if ($kw === "") return htmlspecialchars($text);
    return preg_replace(
        "/(" . preg_quote(htmlspecialchars($kw), "/") . ")/i",
        "<mark>$1</mark>",
        htmlspecialchars($text)
    );
}

// ── CSS ────────────────────────────────────────────────────────────────────
$css = <<<ENDCSS
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: linear-gradient(135deg, #1a237e 0%, #283593 50%, #1565c0 100%);
    min-height: 100vh; color: #fff;
  }
  .navbar {
    background: rgba(0,0,0,0.35); backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 32px; border-bottom: 2px solid rgba(255,255,255,0.15);
    position: sticky; top: 0; z-index: 100;
  }
  .brand { display: flex; align-items: center; gap: 12px; }
  .brand img { height: 52px; border-radius: 6px; }
  .brand-text { font-size: 1.3rem; font-weight: 700; letter-spacing: 1px; }
  .nav-links { display: flex; gap: 10px; }
  .nav-btn {
    background: rgba(255,255,255,0.15); color: #fff;
    border: 1px solid rgba(255,255,255,0.3); padding: 8px 18px;
    border-radius: 6px; font-size: 0.9rem; text-decoration: none; transition: background 0.2s;
  }
  .nav-btn:hover { background: rgba(255,255,255,0.3); }
  /* page title */
  .page-title { text-align: center; padding: 36px 20px 10px; }
  .page-title h1 { font-size: 2rem; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.4); }
  .page-title p  { margin-top: 8px; color: rgba(255,255,255,0.75); }
  /* main layout */
  .main-wrap { max-width: 900px; margin: 0 auto; padding: 24px 20px 80px; }
  /* search + filter bar */
  .filter-bar {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 14px;
    padding: 22px 24px; margin-bottom: 24px;
  }
  .filter-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
  .filter-group { flex: 1; min-width: 180px; }
  .filter-group label { display: block; font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
  .filter-group input, .filter-group select {
    width: 100%; padding: 10px 14px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.12); color: #fff; font-size: 0.92rem;
    outline: none; transition: border-color 0.2s;
  }
  .filter-group input::placeholder { color: rgba(255,255,255,0.4); }
  .filter-group input:focus, .filter-group select:focus { border-color: #42a5f5; background: rgba(255,255,255,0.2); }
  .filter-group select option { background: #1a237e; }
  .btn-search {
    padding: 10px 22px; background: #1976d2; color: #fff;
    border: none; border-radius: 8px; font-size: 0.92rem; font-weight: 700;
    cursor: pointer; transition: background 0.2s; white-space: nowrap;
  }
  .btn-search:hover { background: #1565c0; }
  .btn-clear {
    padding: 10px 16px; background: rgba(255,255,255,0.12); color: #fff;
    border: 1px solid rgba(255,255,255,0.25); border-radius: 8px;
    font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: background 0.2s;
    text-decoration: none; white-space: nowrap;
  }
  .btn-clear:hover { background: rgba(255,255,255,0.22); }
  /* TGID lookup */
  .tgid-bar {
    background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px; padding: 14px 20px; margin-top: 14px;
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
  }
  .tgid-bar label { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.65); white-space: nowrap; }
  .tgid-bar input {
    flex: 1; min-width: 120px; padding: 8px 12px; border-radius: 7px;
    border: 1.5px solid rgba(255,255,255,0.25);
    background: rgba(255,255,255,0.1); color: #fff; font-size: 0.9rem; outline: none;
  }
  .tgid-bar input:focus { border-color: #42a5f5; }
  .tgid-bar input::placeholder { color: rgba(255,255,255,0.35); }
  .btn-tgid {
    padding: 8px 18px; background: #00838f; color: #fff;
    border: none; border-radius: 7px; font-size: 0.88rem; font-weight: 700;
    cursor: pointer; transition: background 0.2s;
  }
  .btn-tgid:hover { background: #006064; }
  /* results header */
  .results-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
  }
  .results-header h2 { font-size: 1.1rem; font-weight: 800; }
  .results-count {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
    border-radius: 20px; padding: 4px 14px; font-size: 0.82rem; font-weight: 700;
  }
  /* category filter pills */
  .cat-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
  .cat-pill {
    padding: 6px 16px; border-radius: 20px; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all 0.2s;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff;
  }
  .cat-pill:hover { background: rgba(255,255,255,0.22); }
  .cat-pill.active { background: #1976d2; border-color: #42a5f5; }
  /* guide card */
  .guide-card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.18); border-radius: 14px;
    margin-bottom: 16px; overflow: hidden; transition: box-shadow 0.2s;
  }
  .guide-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.25); }
  .guide-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; cursor: pointer; gap: 12px;
    user-select: none;
  }
  .guide-header-left { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; }
  .guide-tgid {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
    border-radius: 6px; padding: 3px 10px; font-size: 0.78rem; font-weight: 800;
    letter-spacing: 1px; white-space: nowrap; flex-shrink: 0;
  }
  .guide-title { font-size: 1rem; font-weight: 700; line-height: 1.4; }
  .guide-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
  .cat-badge {
    padding: 3px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700;
    white-space: nowrap;
  }
  .cat-Network  { background: rgba(33,150,243,0.25); border: 1px solid #42a5f5; }
  .cat-Hardware { background: rgba(121,85,72,0.35);  border: 1px solid #a1887f; }
  .cat-Software { background: rgba(103,58,183,0.35); border: 1px solid #9575cd; }
  .cat-Printer  { background: rgba(0,150,136,0.3);   border: 1px solid #4db6ac; }
  .cat-Email    { background: rgba(255,152,0,0.3);   border: 1px solid #ffa726; }
  .cat-Security { background: rgba(244,67,54,0.3);   border: 1px solid #ef5350; }
  .cat-Other    { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.3); }
  .chevron { font-size: 1rem; transition: transform 0.25s; color: rgba(255,255,255,0.6); }
  .chevron.open { transform: rotate(180deg); }
  /* guide body */
  .guide-body { display: none; padding: 0 22px 20px; border-top: 1px solid rgba(255,255,255,0.1); }
  .guide-body.open { display: block; }
  .guide-body pre {
    white-space: pre-wrap; word-break: break-word;
    font-family: "Segoe UI", Arial, sans-serif;
    font-size: 0.93rem; line-height: 1.7;
    color: rgba(255,255,255,0.88); margin-top: 14px;
  }
  mark { background: rgba(255,235,59,0.4); color: #fff; border-radius: 3px; padding: 0 2px; }
  /* not found */
  .not-found {
    text-align: center; padding: 48px 20px;
    background: rgba(255,255,255,0.07); border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.12);
  }
  .not-found .icon { font-size: 3rem; margin-bottom: 14px; }
  .not-found h3 { font-size: 1.2rem; margin-bottom: 8px; }
  .not-found p { color: rgba(255,255,255,0.65); font-size: 0.92rem; }
  /* add guide CTA */
  .add-cta {
    background: rgba(46,125,50,0.2); border: 1px solid #66bb6a;
    border-radius: 12px; padding: 18px 24px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-top: 24px;
  }
  .add-cta p { font-size: 0.95rem; color: rgba(255,255,255,0.85); }
  .btn-add-guide {
    background: #2e7d32; color: #fff; padding: 10px 22px;
    border-radius: 8px; text-decoration: none; font-weight: 700;
    font-size: 0.9rem; transition: background 0.2s; white-space: nowrap;
  }
  .btn-add-guide:hover { background: #1b5e20; }
  /* tgid result */
  .tgid-result {
    background: rgba(0,131,143,0.2); border: 1px solid #4dd0e1;
    border-radius: 12px; padding: 20px 24px; margin-bottom: 20px;
  }
  .tgid-result h3 { font-size: 1rem; font-weight: 800; margin-bottom: 12px; }
  .footer {
    text-align: center; padding: 18px; font-size: 0.82rem;
    color: rgba(255,255,255,0.5); border-top: 1px solid rgba(255,255,255,0.1);
  }
</style>
ENDCSS;

$navbar = <<<ENDNAV
<nav class="navbar">
  <div class="brand">
    <img src="MISO logo.jpg" alt="MISO Logo">
    <span class="brand-text">MISO HELP PAGE</span>
  </div>
  <div class="nav-links">
    <a href="index.html" class="nav-btn">&#127968; Home</a>
    <a href="diy_corner.html" class="nav-btn">&#10133; Add Guide</a>
    <a href="javascript:history.go(-1)" class="nav-btn">&larr; Back</a>
  </div>
</nav>
ENDNAV;

// ── TGID exact lookup ──────────────────────────────────────────────────────
$tgidRow = null;
if ($tgidLookup !== "") {
    $tgidNum = intval(preg_replace("/[^0-9]/", "", $tgidLookup));
    if ($tgidNum > 0) {
        $st = $connection->prepare("SELECT * FROM diy WHERE id = ?");
        $st->bind_param("i", $tgidNum);
        $st->execute();
        $tr = $st->get_result();
        if ($tr->num_rows > 0) $tgidRow = $tr->fetch_assoc();
        $st->close();
    }
}

// ── Main query ─────────────────────────────────────────────────────────────
$params = [];
$types  = "";
$where  = [];

if ($search !== "") {
    $like = "%" . $search . "%";
    $where[] = "(problem LIKE ? OR troubleshooting LIKE ?)";
    $params[] = $like; $params[] = $like;
    $types .= "ss";
}
if ($catFilter !== "") {
    $where[] = "category = ?";
    $params[] = $catFilter;
    $types .= "s";
}

$sql = "SELECT * FROM diy";
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY id DESC";

$rows = [];
if (!empty($params)) {
    $stmt = $connection->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
} else {
    $res = $connection->query($sql);
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}

// ── Category counts for pills ──────────────────────────────────────────────
$catCounts = [];
$ccRes = $connection->query("SELECT category, COUNT(*) as cnt FROM diy GROUP BY category");
while ($cc = $ccRes->fetch_assoc()) $catCounts[$cc["category"]] = $cc["cnt"];
$totalCount = array_sum($catCounts);

$connection->close();

// ── Build category pill URL ────────────────────────────────────────────────
function catUrl($cat, $search) {
    $q = [];
    if ($search !== "") $q[] = "search=" . urlencode($search);
    if ($cat !== "")    $q[] = "category=" . urlencode($cat);
    return "viewguide.php" . (!empty($q) ? "?" . implode("&", $q) : "");
}

// ── Output ─────────────────────────────────────────────────────────────────
echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>MISO HELP PAGE | Troubleshooting Guides</title>";
echo $css;
echo "</head><body>";
echo $navbar;
?>

<div class="page-title">
  <h1>&#128218; Troubleshooting Guides</h1>
  <p>Browse, search, and filter community self-help guides</p>
</div>

<div class="main-wrap">

  <!-- ── FILTER BAR ── -->
  <div class="filter-bar">
    <form id="filterForm" method="GET" novalidate>
      <div class="filter-row">
        <div class="filter-group">
          <label>&#128269; Keyword Search</label>
          <input type="text" name="search" id="searchInput"
                 placeholder="e.g. Wi-Fi, printer, password..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group" style="max-width:200px;">
          <label>&#127991; Category</label>
          <select name="category" id="catSelect">
            <option value="">All Categories</option>
            <?php foreach ($catIcons as $cat => $icon): ?>
            <option value="<?= $cat ?>" <?= $catFilter === $cat ? "selected" : "" ?>>
              <?= $icon ?> <?= $cat ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-search">&#128269; Search</button>
        <?php if ($search !== "" || $catFilter !== ""): ?>
        <a href="viewguide.php" class="btn-clear">&#10005; Clear</a>
        <?php endif; ?>
      </div>

      <!-- TGID lookup row -->
      <div class="tgid-bar">
        <label>&#128196; Lookup by TGID:</label>
        <input type="text" name="tgid" id="tgidInput"
               placeholder="e.g. TGID-5 or just 5"
               value="<?= htmlspecialchars($tgidLookup) ?>">
        <button type="submit" class="btn-tgid">&#128270; Find</button>
      </div>
    </form>
  </div>

  <!-- ── TGID RESULT ── -->
  <?php if ($tgidLookup !== ""): ?>
  <div class="tgid-result">
    <?php if ($tgidRow): ?>
      <?php
        $cat = $tgidRow["category"] ?? "Other";
        $icon = $catIcons[$cat] ?? "&#128196;";
        $catClass = "cat-" . preg_replace("/[^a-zA-Z]/", "", $cat);
      ?>
      <h3>&#128270; TGID-<?= (int)$tgidRow["id"] ?> &mdash; <?= htmlspecialchars($tgidRow["problem"]) ?></h3>
      <span class="cat-badge <?= $catClass ?>"><?= $icon ?> <?= htmlspecialchars($cat) ?></span>
      <pre style="margin-top:12px;"><?= htmlspecialchars($tgidRow["troubleshooting"]) ?></pre>
    <?php else: ?>
      <h3>&#9888;&#65039; No guide found for &ldquo;<?= htmlspecialchars($tgidLookup) ?>&rdquo;</h3>
      <p style="color:rgba(255,255,255,0.65);margin-top:6px;">Check the TGID number and try again.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── CATEGORY PILLS ── -->
  <div class="cat-pills">
    <a href="<?= catUrl("", $search) ?>"
       class="cat-pill <?= $catFilter === "" ? "active" : "" ?>">
      &#127775; All (<?= $totalCount ?>)
    </a>
    <?php foreach ($catIcons as $cat => $icon): ?>
      <?php $cnt = $catCounts[$cat] ?? 0; if ($cnt === 0) continue; ?>
      <a href="<?= catUrl($cat, $search) ?>"
         class="cat-pill <?= $catFilter === $cat ? "active" : "" ?>">
        <?= $icon ?> <?= $cat ?> (<?= $cnt ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ── RESULTS HEADER ── -->
  <div class="results-header">
    <h2>
      <?php if ($search !== "" || $catFilter !== ""): ?>
        &#9989; Results
        <?php if ($search !== ""): ?> for &ldquo;<?= htmlspecialchars($search) ?>&rdquo;<?php endif; ?>
        <?php if ($catFilter !== ""): ?> in <strong><?= htmlspecialchars($catFilter) ?></strong><?php endif; ?>
      <?php else: ?>
        &#128218; All Guides
      <?php endif; ?>
    </h2>
    <span class="results-count"><?= count($rows) ?> guide<?= count($rows) !== 1 ? "s" : "" ?></span>
  </div>

  <!-- ── GUIDE CARDS ── -->
  <?php if (empty($rows)): ?>
  <div class="not-found">
    <div class="icon">&#128270;</div>
    <h3>No Guides Found</h3>
    <?php if ($search !== "" || $catFilter !== ""): ?>
      <p>No guides matched your search. Try different keywords or clear the filter.</p>
    <?php else: ?>
      <p>No troubleshooting guides have been added yet.</p>
    <?php endif; ?>
  </div>
  <?php else: ?>
    <?php foreach ($rows as $i => $row):
      $cat = $row["category"] ?? "Other";
      $icon = $catIcons[$cat] ?? "&#128196;";
      $catClass = "cat-" . preg_replace("/[^a-zA-Z]/", "", $cat);
      $tgid = (int)$row["id"];
    ?>
    <div class="guide-card" id="guide-<?= $tgid ?>">
      <div class="guide-header" onclick="toggleGuide(<?= $tgid ?>)">
        <div class="guide-header-left">
          <span class="guide-tgid">TGID-<?= $tgid ?></span>
          <span class="guide-title"><?= hl($row["problem"], $search) ?></span>
        </div>
        <div class="guide-header-right">
          <span class="cat-badge <?= $catClass ?>"><?= $icon ?> <?= htmlspecialchars($cat) ?></span>
          <span class="chevron" id="chev-<?= $tgid ?>">&#9660;</span>
        </div>
      </div>
      <div class="guide-body" id="body-<?= $tgid ?>">
        <pre><?= hl($row["troubleshooting"], $search) ?></pre>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- ── ADD GUIDE CTA ── -->
  <div class="add-cta">
    <p>&#128161; Know a fix that others might need? Share your knowledge!</p>
    <a href="diy_corner.html" class="btn-add-guide">&#10133; Add a Guide</a>
  </div>

</div><!-- /main-wrap -->

<div class="footer">&copy; 2026 MISO Help Page &mdash; IT Support Portal</div>

<script>
// ── Accordion toggle ───────────────────────────────────────────────────────
function toggleGuide(id) {
  var body = document.getElementById("body-" + id);
  var chev = document.getElementById("chev-" + id);
  var isOpen = body.classList.contains("open");
  body.classList.toggle("open", !isOpen);
  chev.classList.toggle("open", !isOpen);
}

// ── Auto-open if only one result ───────────────────────────────────────────
var cards = document.querySelectorAll(".guide-card");
if (cards.length === 1) {
  var firstId = cards[0].id.replace("guide-", "");
  toggleGuide(parseInt(firstId, 10));
}

// ── Auto-open TGID result card if found ───────────────────────────────────
<?php if ($tgidRow): ?>
toggleGuide(<?= (int)$tgidRow["id"] ?>);
document.getElementById("guide-<?= (int)$tgidRow["id"] ?>").scrollIntoView({ behavior: "smooth", block: "center" });
<?php endif; ?>

// ── Live keyword filter (client-side instant filter) ──────────────────────
document.getElementById("searchInput").addEventListener("input", function() {
  var kw = this.value.toLowerCase().trim();
  cards.forEach(function(card) {
    var title = card.querySelector(".guide-title").textContent.toLowerCase();
    var body  = card.querySelector(".guide-body pre") ? card.querySelector(".guide-body pre").textContent.toLowerCase() : "";
    card.style.display = (!kw || title.includes(kw) || body.includes(kw)) ? "" : "none";
  });
});
</script>
</body>
</html>
