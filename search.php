<?php
// search.php  -  Enhanced multi-mode ticket search
$connection = new mysqli("localhost", "root", "", "miniproject");
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Ensure all expected columns exist
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS dept VARCHAR(100) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS phone_no VARCHAR(20) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'Normal'");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS description TEXT");

// ============================================================
//  HELPER: render a single ticket detail card
// ============================================================
function renderTicketCard($row, $index = null) {
    $priorityClass = strtolower($row['priority']) === 'urgent' ? 'badge-urgent'
                   : (strtolower($row['priority']) === 'high'   ? 'badge-high' : 'badge-normal');
    $id = (int)$row['id'];
    $label = $index !== null ? "Result #$index" : "Ticket Found";
    echo '<div class="ticket-card">';
    echo '<div class="ticket-card-header">';
    echo '  <span class="ticket-card-title">&#9989; ' . htmlspecialchars($label) . '</span>';
    echo '  <span class="ticket-id-badge"># ' . $id . '</span>';
    echo '</div>';
    echo '<table class="result-table">';
    echo '<tr><th>Name</th><td>'        . htmlspecialchars($row['name'])        . '</td></tr>';
    echo '<tr><th>Email</th><td>'       . htmlspecialchars($row['email'])       . '</td></tr>';
    echo '<tr><th>Issue</th><td>'       . htmlspecialchars($row['issue'])       . '</td></tr>';
    echo '<tr><th>Department</th><td>' . htmlspecialchars($row['dept'])        . '</td></tr>';
    echo '<tr><th>Contact No.</th><td>'. htmlspecialchars($row['phone_no'])    . '</td></tr>';
    echo '<tr><th>Priority</th><td><span class="badge ' . $priorityClass . '">'
         . htmlspecialchars($row['priority']) . '</span></td></tr>';
    echo '<tr><th>Description</th><td>'. nl2br(htmlspecialchars($row['description'])) . '</td></tr>';
    echo '</table>';
    echo '<div class="action-row">';
    echo '  <a href="update_ticket.php?id=' . $id . '" class="btn-update">&#9998; Update</a>';
    echo '  <a href="delete.php" class="btn-delete-link">&#128465; Delete</a>';
    echo '</div>';
    echo '</div>';
}

// ============================================================
//  DETERMINE SEARCH MODE
//  Priority: ticket_id > fname/lname > name (keyword)
// ============================================================
$doSearch   = false;
$searchMode = '';   // 'id' | 'name'
$searchVal  = '';
$searchLabel = '';

$rawId    = trim($_GET['ticket_id'] ?? '');
$rawFname = trim($_GET['fname']     ?? '');
$rawLname = trim($_GET['lname']     ?? '');

if ($rawId !== '') {
    $doSearch    = true;
    $searchMode  = 'id';
    $searchVal   = $rawId;
    $searchLabel = 'Ticket ID: ' . htmlspecialchars($rawId);
} elseif ($rawFname !== '' || $rawLname !== '') {
    $doSearch    = true;
    $searchMode  = 'name';
    $searchVal   = trim($rawFname . ' ' . $rawLname);
    $parts = array_filter([$rawFname, $rawLname]);
    $searchLabel = 'Name: &ldquo;' . htmlspecialchars(implode(' ', $parts)) . '&rdquo;';
}

// ============================================================
//  CSS
// ============================================================
$css = <<<ENDCSS
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #1a237e 0%, #283593 50%, #1565c0 100%);
    min-height: 100vh; color: #fff;
  }
  /* navbar */
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
  /* search form card */
  .search-card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 16px;
    max-width: 700px; margin: 24px auto 0; padding: 32px 36px;
  }
  /* tab switcher */
  .tab-bar {
    display: flex; gap: 0; margin-bottom: 28px;
    background: rgba(0,0,0,0.2); border-radius: 10px; padding: 4px;
  }
  .tab-btn {
    flex: 1; padding: 10px 0; border: none; border-radius: 8px;
    background: transparent; color: rgba(255,255,255,0.6);
    font-size: 0.92rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
  }
  .tab-btn.active {
    background: #1976d2; color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  }
  .tab-btn:hover:not(.active) { color: #fff; background: rgba(255,255,255,0.1); }
  /* tab panels */
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }
  /* form elements */
  .form-group { margin-bottom: 18px; }
  .form-group label {
    display: block; font-size: 0.88rem; font-weight: 600;
    margin-bottom: 6px; color: rgba(255,255,255,0.9);
  }
  .form-group label .req { color: #ff6b6b; margin-left: 3px; }
  .form-row { display: flex; gap: 14px; }
  .form-row .form-group { flex: 1; }
  input[type="text"], input[type="number"] {
    width: 100%; padding: 11px 14px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.12); color: #fff; font-size: 0.95rem;
    outline: none; transition: border-color 0.2s, background 0.2s;
  }
  input::placeholder { color: rgba(255,255,255,0.45); }
  input:focus { border-color: #42a5f5; background: rgba(255,255,255,0.2); }
  input.error-field { border-color: #ff6b6b !important; }
  .field-error { color: #ff6b6b; font-size: 0.8rem; margin-top: 4px; display: none; }
  .field-error.visible { display: block; }
  .hint { font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 5px; }
  .btn-submit {
    width: 100%; padding: 13px; background: #1976d2; color: #fff;
    border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
    cursor: pointer; transition: background 0.2s; margin-top: 4px;
  }
  .btn-submit:hover { background: #1565c0; }
  /* results area */
  .results-wrap {
    max-width: 700px; margin: 28px auto 60px;
  }
  .results-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
  }
  .results-header h2 { font-size: 1.2rem; font-weight: 800; }
  .results-count {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
    border-radius: 20px; padding: 4px 14px; font-size: 0.85rem; font-weight: 700;
  }
  /* individual ticket card */
  .ticket-card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 14px;
    padding: 24px 28px; margin-bottom: 20px;
    transition: box-shadow 0.2s;
  }
  .ticket-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.25); }
  .ticket-card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; flex-wrap: wrap; gap: 8px;
  }
  .ticket-card-title { font-size: 1rem; font-weight: 800; }
  .ticket-id-badge {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 3px 14px; font-size: 1rem; font-weight: 800;
    letter-spacing: 1px;
  }
  .result-table { width: 100%; border-collapse: collapse; }
  .result-table th, .result-table td {
    padding: 9px 12px; text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.1);
  }
  .result-table th {
    color: rgba(255,255,255,0.55); font-size: 0.8rem;
    text-transform: uppercase; width: 32%; font-weight: 600;
  }
  .result-table td { font-weight: 500; font-size: 0.95rem; }
  .badge {
    display: inline-block; padding: 3px 12px; border-radius: 20px;
    font-size: 0.8rem; font-weight: 700;
  }
  .badge-normal { background: rgba(33,150,243,0.3); border: 1px solid #42a5f5; }
  .badge-high   { background: rgba(255,152,0,0.3);  border: 1px solid #ffa726; }
  .badge-urgent { background: rgba(244,67,54,0.3);  border: 1px solid #ef5350; }
  /* action row inside card */
  .action-row {
    display: flex; flex-wrap: wrap; gap: 10px;
    margin-top: 18px; justify-content: flex-end;
  }
  .btn-update {
    background: #f57c00; color: #fff; padding: 8px 20px;
    border-radius: 7px; text-decoration: none; font-weight: 700;
    font-size: 0.9rem; transition: background 0.2s;
  }
  .btn-update:hover { background: #e65100; }
  .btn-delete-link {
    background: rgba(211,47,47,0.3); color: #fff; padding: 8px 20px;
    border-radius: 7px; text-decoration: none; font-weight: 700;
    font-size: 0.9rem; border: 1px solid #ef5350; transition: background 0.2s;
  }
  .btn-delete-link:hover { background: rgba(211,47,47,0.55); }
  /* not found */
  .not-found {
    text-align: center; padding: 40px 20px;
    background: rgba(255,255,255,0.07); border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.15);
  }
  .not-found .icon { font-size: 3rem; margin-bottom: 14px; }
  .not-found h3 { font-size: 1.2rem; margin-bottom: 8px; }
  .not-found p { color: rgba(255,255,255,0.7); font-size: 0.92rem; }
  /* bottom nav */
  .bottom-nav {
    display: flex; justify-content: center; gap: 12px;
    margin-top: 28px; flex-wrap: wrap;
  }
  .btn-back {
    background: rgba(255,255,255,0.15); color: #fff; padding: 10px 24px;
    border-radius: 8px; text-decoration: none; font-weight: 600;
    border: 1px solid rgba(255,255,255,0.3); transition: background 0.2s;
  }
  .btn-back:hover { background: rgba(255,255,255,0.25); }
  .btn-home {
    background: #1976d2; color: #fff; padding: 10px 24px;
    border-radius: 8px; text-decoration: none; font-weight: 700;
    transition: background 0.2s;
  }
  .btn-home:hover { background: #1565c0; }
  /* highlight matched text */
  mark {
    background: rgba(255,235,59,0.45); color: #fff;
    border-radius: 3px; padding: 0 2px;
  }
  /* footer */
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
    <a href="javascript:history.go(-1)" class="nav-btn">&larr; Back</a>
  </div>
</nav>
ENDNAV;

// ============================================================
//  EXECUTE QUERY
// ============================================================
$rows   = [];
$errors = [];

if ($doSearch) {
    if ($searchMode === 'id') {
        $idVal = intval($rawId);
        if ($idVal <= 0) {
            $errors[] = 'Ticket ID must be a positive number.';
        } else {
            $stmt = $connection->prepare("SELECT * FROM tickets WHERE id = ?");
            $stmt->bind_param("i", $idVal);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        }
    } else {
        // name search - build flexible LIKE query
        $fname = trim($rawFname);
        $lname = trim($rawLname);

        if ($fname === '' && $lname === '') {
            $errors[] = 'Please enter at least a first name or last name.';
        } else {
            if ($fname !== '' && $lname !== '') {
                // both provided: match full name in either order
                $like1 = '%' . $fname . '%';
                $like2 = '%' . $lname . '%';
                $stmt = $connection->prepare(
                    "SELECT * FROM tickets WHERE name LIKE ? AND name LIKE ? ORDER BY id ASC"
                );
                $stmt->bind_param("ss", $like1, $like2);
            } elseif ($fname !== '') {
                $like = '%' . $fname . '%';
                $stmt = $connection->prepare(
                    "SELECT * FROM tickets WHERE name LIKE ? ORDER BY id ASC"
                );
                $stmt->bind_param("s", $like);
            } else {
                $like = '%' . $lname . '%';
                $stmt = $connection->prepare(
                    "SELECT * FROM tickets WHERE name LIKE ? ORDER BY id ASC"
                );
                $stmt->bind_param("s", $like);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        }
    }
}

$connection->close();

// ============================================================
//  HIGHLIGHT helper  (wraps matched keyword in <mark>)
// ============================================================
function highlight($text, $keyword) {
    if ($keyword === '') return htmlspecialchars($text);
    $safe = htmlspecialchars($text);
    $kw   = htmlspecialchars($keyword);
    return preg_replace('/(' . preg_quote($kw, '/') . ')/i', '<mark>$1</mark>', $safe);
}

// ============================================================
//  OUTPUT
// ============================================================
echo "<!DOCTYPE html>\n<html lang='en'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>MISO HELP PAGE | Search Ticket</title>\n";
echo $css;
echo "</head>\n<body>\n";
echo $navbar;
?>

<div class="page-title">
  <h1>&#128269; Search a Ticket</h1>
  <p>Search by Ticket ID for an exact match, or by name for a partial match</p>
</div>

<!-- ── SEARCH FORM ── -->
<div class="search-card">

  <!-- Tab bar -->
  <div class="tab-bar" role="tablist">
    <button class="tab-btn <?= (!$doSearch || $searchMode==='id')   ? 'active' : '' ?>"
            role="tab" onclick="switchTab('tab-id',this)">
      &#127381; Search by Ticket ID
    </button>
    <button class="tab-btn <?= ($doSearch && $searchMode==='name') ? 'active' : '' ?>"
            role="tab" onclick="switchTab('tab-name',this)">
      &#128100; Search by Name
    </button>
  </div>

  <!-- Panel: Ticket ID -->
  <div id="tab-id" class="tab-panel <?= (!$doSearch || $searchMode==='id') ? 'active' : '' ?>">
    <form id="formId" method="GET" novalidate>
      <div class="form-group">
        <label for="ticket_id">Ticket ID <span class="req">*</span></label>
        <input type="number" name="ticket_id" id="ticket_id" min="1"
               placeholder="e.g. 42"
               value="<?= htmlspecialchars($rawId) ?>">
        <div class="field-error" id="err-ticket-id">Please enter a valid Ticket ID (positive number).</div>
        <div class="hint">Finds the exact ticket matching this ID.</div>
      </div>
      <button type="submit" class="btn-submit">&#128269; Search by ID</button>
    </form>
  </div>

  <!-- Panel: Name -->
  <div id="tab-name" class="tab-panel <?= ($doSearch && $searchMode==='name') ? 'active' : '' ?>">
    <form id="formName" method="GET" novalidate>
      <div class="form-row">
        <div class="form-group">
          <label for="fname">First Name</label>
          <input type="text" name="fname" id="fname"
                 placeholder="e.g. Juan"
                 value="<?= htmlspecialchars($rawFname) ?>">
        </div>
        <div class="form-group">
          <label for="lname">Last Name</label>
          <input type="text" name="lname" id="lname"
                 placeholder="e.g. Dela Cruz"
                 value="<?= htmlspecialchars($rawLname) ?>">
        </div>
      </div>
      <div class="field-error" id="err-name">Please enter at least a first name or last name.</div>
      <div class="hint">Partial match &mdash; returns all tickets whose name contains the keyword(s).</div>
      <button type="submit" class="btn-submit" style="margin-top:14px;">&#128269; Search by Name</button>
    </form>
  </div>

</div><!-- /search-card -->

<?php
// ── RESULTS ──────────────────────────────────────────────────────────────
if ($doSearch):
?>
<div class="results-wrap">

  <?php if (!empty($errors)): ?>
    <div class="not-found">
      <div class="icon">&#9888;&#65039;</div>
      <h3>Invalid Search</h3>
      <p><?= implode('<br>', $errors) ?></p>
    </div>

  <?php elseif (empty($rows)): ?>
    <div class="not-found">
      <div class="icon">&#128270;</div>
      <h3>No Results Found</h3>
      <?php if ($searchMode === 'id'): ?>
        <p>No ticket exists with ID <strong>#<?= htmlspecialchars($rawId) ?></strong>.</p>
        <p style="margin-top:6px;">Double-check the ID and try again.</p>
      <?php else: ?>
        <p>No tickets matched the name <strong>&ldquo;<?= htmlspecialchars(trim($rawFname . ' ' . $rawLname)) ?>&rdquo;</strong>.</p>
        <p style="margin-top:6px;">Try a shorter keyword or check the spelling.</p>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="results-header">
      <h2>
        &#9989; Results for <?= $searchLabel ?>
      </h2>
      <span class="results-count">
        <?= count($rows) ?> ticket<?= count($rows) !== 1 ? 's' : '' ?> found
      </span>
    </div>

    <?php
    $keyword = ($searchMode === 'name') ? trim($rawFname . ' ' . $rawLname) : '';
    foreach ($rows as $i => $row):
        $priorityClass = strtolower($row['priority']) === 'urgent' ? 'badge-urgent'
                       : (strtolower($row['priority']) === 'high'   ? 'badge-high' : 'badge-normal');
        $ticketId = (int)$row['id'];
        $label = count($rows) > 1 ? 'Result #' . ($i + 1) : 'Ticket Found';
    ?>
    <div class="ticket-card">
      <div class="ticket-card-header">
        <span class="ticket-card-title">&#9989; <?= htmlspecialchars($label) ?></span>
        <span class="ticket-id-badge"># <?= $ticketId ?></span>
      </div>
      <table class="result-table">
        <tr>
          <th>Name</th>
          <td><?= ($searchMode === 'name')
                ? highlight($row['name'], $rawFname !== '' ? $rawFname : $rawLname)
                : htmlspecialchars($row['name']) ?></td>
        </tr>
        <tr><th>Email</th>       <td><?= htmlspecialchars($row['email'])       ?></td></tr>
        <tr><th>Issue</th>       <td><?= htmlspecialchars($row['issue'])       ?></td></tr>
        <tr><th>Department</th>  <td><?= htmlspecialchars($row['dept'])        ?></td></tr>
        <tr><th>Contact No.</th> <td><?= htmlspecialchars($row['phone_no'])    ?></td></tr>
        <tr>
          <th>Priority</th>
          <td><span class="badge <?= $priorityClass ?>"><?= htmlspecialchars($row['priority']) ?></span></td>
        </tr>
        <tr><th>Description</th> <td><?= nl2br(htmlspecialchars($row['description'])) ?></td></tr>
      </table>
      <div class="action-row">
        <a href="update_ticket.php?id=<?= $ticketId ?>" class="btn-update">&#9998; Update</a>
        <a href="delete.php" class="btn-delete-link">&#128465; Delete</a>
      </div>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>

  <div class="bottom-nav">
    <a href="search.php" class="btn-back">&#128269; New Search</a>
    <a href="index.html" class="btn-home">&#127968; Home</a>
  </div>
</div>
<?php endif; ?>

<div class="footer">&copy; 2026 MISO Help Page &mdash; IT Support Portal</div>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(panelId, btn) {
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b)   { b.classList.remove('active'); });
  document.getElementById(panelId).classList.add('active');
  btn.classList.add('active');
}

// ── ID form validation ─────────────────────────────────────────────────────
document.getElementById('formId').addEventListener('submit', function(e) {
  var val = parseInt(document.getElementById('ticket_id').value, 10);
  var err = document.getElementById('err-ticket-id');
  var inp = document.getElementById('ticket_id');
  if (!val || val <= 0) {
    e.preventDefault();
    err.classList.add('visible');
    inp.classList.add('error-field');
  } else {
    err.classList.remove('visible');
    inp.classList.remove('error-field');
  }
});
document.getElementById('ticket_id').addEventListener('input', function() {
  document.getElementById('err-ticket-id').classList.remove('visible');
  this.classList.remove('error-field');
});

// ── Name form validation ───────────────────────────────────────────────────
document.getElementById('formName').addEventListener('submit', function(e) {
  var fn = document.getElementById('fname').value.trim();
  var ln = document.getElementById('lname').value.trim();
  var err = document.getElementById('err-name');
  if (!fn && !ln) {
    e.preventDefault();
    err.classList.add('visible');
    document.getElementById('fname').classList.add('error-field');
    document.getElementById('lname').classList.add('error-field');
  } else {
    err.classList.remove('visible');
    document.getElementById('fname').classList.remove('error-field');
    document.getElementById('lname').classList.remove('error-field');
  }
});
['fname','lname'].forEach(function(id) {
  document.getElementById(id).addEventListener('input', function() {
    document.getElementById('err-name').classList.remove('visible');
    document.getElementById('fname').classList.remove('error-field');
    document.getElementById('lname').classList.remove('error-field');
  });
});
</script>
</body>
</html>
