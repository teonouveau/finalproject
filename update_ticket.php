<?php
// update_ticket.php  –  Load ticket for editing (GET) and save changes (POST)
$connection = new mysqli("localhost", "root", "", "miniproject");
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Ensure all expected columns exist
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS dept VARCHAR(100) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS phone_no VARCHAR(20) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'Normal'");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS description TEXT");

// ── Shared CSS ─────────────────────────────────────────────────────────────
$css = <<<CSS
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #1a237e 0%, #283593 50%, #1565c0 100%);
    min-height: 100vh; color: #fff;
  }
  /* ── navbar ── */
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
    border-radius: 6px; font-size: 0.9rem; text-decoration: none;
    transition: background 0.2s; cursor: pointer;
  }
  .nav-btn:hover { background: rgba(255,255,255,0.3); }
  /* ── page title ── */
  .page-title { text-align: center; padding: 36px 20px 10px; }
  .page-title h1 { font-size: 2rem; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.4); }
  .page-title p  { margin-top: 8px; color: rgba(255,255,255,0.75); }
  /* ── ticket badge ── */
  .ticket-badge {
    display: inline-block; background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3); border-radius: 8px;
    padding: 4px 16px; font-size: 1rem; font-weight: 800;
    letter-spacing: 1px; margin-left: 10px; vertical-align: middle;
  }
  /* ── form card ── */
  .form-card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 16px;
    max-width: 700px; margin: 24px auto 80px; padding: 36px 40px;
  }
  .section-label {
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1.5px; color: rgba(255,255,255,0.5);
    margin-bottom: 16px; padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
  }
  .form-group { margin-bottom: 18px; }
  .form-group label {
    display: block; font-size: 0.88rem; font-weight: 600;
    margin-bottom: 6px; color: rgba(255,255,255,0.9);
  }
  .form-group label .req { color: #ff6b6b; margin-left: 3px; }
  .form-row { display: flex; gap: 16px; }
  .form-row .form-group { flex: 1; }
  input[type="text"],
  input[type="email"],
  input[type="tel"],
  select,
  textarea {
    width: 100%; padding: 11px 14px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.12); color: #fff; font-size: 0.95rem;
    outline: none; transition: border-color 0.2s, background 0.2s;
  }
  input::placeholder, textarea::placeholder { color: rgba(255,255,255,0.45); }
  input:focus, select:focus, textarea:focus {
    border-color: #42a5f5; background: rgba(255,255,255,0.2);
  }
  input.changed, select.changed, textarea.changed {
    border-color: #ffa726 !important;
    background: rgba(255,167,38,0.1) !important;
  }
  input.error-field, select.error-field, textarea.error-field {
    border-color: #ff6b6b !important;
  }
  select option { background: #1a237e; color: #fff; }
  textarea { resize: vertical; min-height: 110px; }
  /* ── field messages ── */
  .field-error {
    color: #ff6b6b; font-size: 0.8rem; margin-top: 4px; display: none;
  }
  .field-error.visible { display: block; }
  /* ── change indicator ── */
  .change-notice {
    background: rgba(255,167,38,0.2); border: 1px solid #ffa726;
    border-radius: 8px; padding: 10px 16px; margin-bottom: 20px;
    font-size: 0.88rem; display: none; align-items: center; gap: 8px;
  }
  .change-notice.visible { display: flex; }
  /* ── alert banner ── */
  .alert {
    border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;
    font-size: 0.92rem; font-weight: 600; display: none;
  }
  .alert.visible { display: flex; align-items: center; gap: 10px; }
  .alert-error   { background: rgba(211,47,47,0.35); border: 1px solid #ef5350; }
  .alert-success { background: rgba(46,125,50,0.35);  border: 1px solid #66bb6a; }
  /* ── buttons ── */
  .btn-row { display: flex; gap: 12px; margin-top: 8px; }
  .btn-save {
    flex: 1; padding: 13px; background: #2e7d32; color: #fff;
    border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
    cursor: pointer; transition: background 0.2s, opacity 0.2s;
  }
  .btn-save:disabled {
    opacity: 0.4; cursor: not-allowed; background: #555;
  }
  .btn-save:not(:disabled):hover { background: #1b5e20; }
  .btn-cancel {
    padding: 13px 28px; background: rgba(255,255,255,0.15); color: #fff;
    border: 1px solid rgba(255,255,255,0.3); border-radius: 8px;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    text-decoration: none; transition: background 0.2s;
    display: inline-flex; align-items: center;
  }
  .btn-cancel:hover { background: rgba(255,255,255,0.25); }
  /* ── result card ── */
  .result-card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 16px;
    max-width: 560px; margin: 60px auto; padding: 40px 36px; text-align: center;
  }
  .result-card .icon { font-size: 3.5rem; margin-bottom: 16px; }
  .result-card h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: 10px; }
  .result-card p { color: rgba(255,255,255,0.8); font-size: 1rem; margin-bottom: 6px; }
  .btn-home {
    display: inline-block; background: #1976d2; color: #fff;
    padding: 12px 28px; border-radius: 8px; text-decoration: none;
    font-weight: 700; font-size: 1rem; transition: background 0.2s; margin-top: 16px;
  }
  .btn-home:hover { background: #1565c0; }
  .btn-search {
    display: inline-block; background: rgba(255,255,255,0.15); color: #fff;
    padding: 12px 24px; border-radius: 8px; text-decoration: none;
    font-weight: 600; margin-top: 16px; margin-right: 10px;
    border: 1px solid rgba(255,255,255,0.3); transition: background 0.2s;
  }
  .btn-search:hover { background: rgba(255,255,255,0.25); }
  /* ── footer ── */
  .footer {
    text-align: center; padding: 18px; font-size: 0.82rem;
    color: rgba(255,255,255,0.5); border-top: 1px solid rgba(255,255,255,0.1);
  }
</style>
CSS;

$navbar = <<<HTML
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
HTML;

// ══════════════════════════════════════════════════════════════════════════
//  POST  –  Save updated ticket
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id       = intval($_POST["id"] ?? 0);
    $fname    = trim($_POST["fname"]    ?? '');
    $lname    = trim($_POST["lname"]    ?? '');
    $email    = trim($_POST["email"]    ?? '');
    $issue    = trim($_POST["issue"]    ?? '');
    $dept     = trim($_POST["dept"]     ?? '');
    $phone    = trim($_POST["phone_no"] ?? '');
    $priority = trim($_POST["priority"] ?? '');
    $desc     = trim($_POST["desc"]     ?? '');

    // ── Server-side validation ─────────────────────────────────────────────
    $errors = [];
    if ($id <= 0)                                          $errors[] = "Invalid Ticket ID.";
    if (empty($fname))                                     $errors[] = "First name is required.";
    if (empty($lname))                                     $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                                           $errors[] = "A valid email address is required.";
    if (empty($issue))                                     $errors[] = "Problem / issue is required.";
    if (empty($dept))                                      $errors[] = "Department is required.";
    if (empty($phone) || !preg_match('/^\d{7,15}$/', $phone))
                                                           $errors[] = "A valid contact number is required (digits only, 7-15 chars).";
    if (empty($desc))                                      $errors[] = "Problem description is required.";

    // ── Output header ──────────────────────────────────────────────────────
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>";
    echo "<title>MISO HELP PAGE | Update Ticket</title>{$css}</head><body>";
    echo $navbar;

    if (!empty($errors)) {
        $errList = '<ul style="text-align:left;margin-top:8px;">';
        foreach ($errors as $e) $errList .= "<li>$e</li>";
        $errList .= '</ul>';
        echo <<<HTML
<div class="result-card">
  <div class="icon">&#9888;&#65039;</div>
  <h2>Validation Error</h2>
  <p>{$errList}</p>
  <a href="javascript:history.go(-1)" class="btn-search">&larr; Go Back &amp; Fix</a>
</div>
HTML;
        echo '<div class="footer">&copy; 2026 MISO Help Page</div></body></html>';
        $connection->close();
        exit();
    }

    $name = $fname . " " . $lname;

    $stmt = $connection->prepare(
        "UPDATE tickets
         SET name=?, email=?, issue=?, dept=?, phone_no=?, priority=?, description=?
         WHERE id=?"
    );
    $stmt->bind_param("sssssssi", $name, $email, $issue, $dept, $phone, $priority, $desc, $id);

    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        echo <<<HTML
<div class="result-card">
  <div class="icon">&#9989;</div>
  <h2>Ticket Successfully Updated!</h2>
  <p>Ticket <strong>#$id</strong> has been saved with your changes.</p>
  <a href="search.php?id={$id}" class="btn-search">&#128269; View Ticket</a>
  <a href="index.html" class="btn-home">&#127968; Back to Home</a>
</div>
HTML;
    } else {
        echo <<<HTML
<div class="result-card">
  <div class="icon">&#10060;</div>
  <h2>Error Occurred</h2>
  <p>Could not update the ticket. Please try again.</p>
  <p style="font-size:0.82rem;color:rgba(255,255,255,0.5);margin-top:6px;">{$connection->error}</p>
  <a href="javascript:history.go(-1)" class="btn-search">&larr; Try Again</a>
  <a href="index.html" class="btn-home">&#127968; Home</a>
</div>
HTML;
    }

    $stmt->close();
    $connection->close();
    echo '<div class="footer">&copy; 2026 MISO Help Page</div></body></html>';
    exit();
}

// ══════════════════════════════════════════════════════════════════════════
//  GET  –  Load ticket into editable form
// ══════════════════════════════════════════════════════════════════════════
$id = intval($_GET["id"] ?? 0);

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>MISO HELP PAGE | Update Ticket</title>{$css}</head><body>";
echo $navbar;

if ($id <= 0) {
    echo <<<HTML
<div class="page-title"><h1>&#9998; Update Ticket</h1></div>
<div class="result-card">
  <div class="icon">&#9888;&#65039;</div>
  <h2>Invalid Ticket ID</h2>
  <p>Please search for a ticket first, then click Update.</p>
  <a href="search.php" class="btn-search">&#128269; Search Ticket</a>
  <a href="index.html" class="btn-home">&#127968; Home</a>
</div>
HTML;
    echo '<div class="footer">&copy; 2026 MISO Help Page</div></body></html>';
    $connection->close();
    exit();
}

$stmt = $connection->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo <<<HTML
<div class="page-title"><h1>&#9998; Update Ticket</h1></div>
<div class="result-card">
  <div class="icon">&#128270;</div>
  <h2>Ticket Not Found</h2>
  <p>No ticket exists with ID <strong>#$id</strong>.</p>
  <a href="search.php" class="btn-search">&#128269; Search Again</a>
  <a href="index.html" class="btn-home">&#127968; Home</a>
</div>
HTML;
    echo '<div class="footer">&copy; 2026 MISO Help Page</div></body></html>';
    $stmt->close();
    $connection->close();
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();
$connection->close();

// Split name back into first / last
$nameParts = explode(" ", $row["name"], 2);
$fname = htmlspecialchars($nameParts[0] ?? '');
$lname = htmlspecialchars($nameParts[1] ?? '');

$email    = htmlspecialchars($row["email"]);
$issue    = htmlspecialchars($row["issue"]);
$dept     = htmlspecialchars($row["dept"]);
$phone    = htmlspecialchars($row["phone_no"]);
$priority = htmlspecialchars($row["priority"]);
$desc     = htmlspecialchars($row["description"]);

// Build department options
$depts = [
    "1" => "College of Education",
    "2" => "College of Agriculture",
    "3" => "College of Forestry",
    "4" => "College of Hospitality and Tourism",
    "5" => "College of Arts and Science",
    "6" => "Registrar's Office",
    "7" => "Administrative Office",
    "8" => "Cashier's / Accounting Office",
    "9" => "College of Technology and Engineering",
];
$deptOptions = '<option value="">-- Select a Department --</option>';
foreach ($depts as $val => $label) {
    $sel = ($dept == $val || $dept == $label) ? ' selected' : '';
    $deptOptions .= "<option value=\"$val\"$sel>" . htmlspecialchars($label) . "</option>";
}

$priOptions = '';
foreach (["Normal","High","Urgent"] as $p) {
    $sel = ($priority === $p) ? ' selected' : '';
    $priOptions .= "<option value=\"$p\"$sel>$p</option>";
}

?>

<div class="page-title">
  <h1>&#9998; Update Ticket <span class="ticket-badge"># <?= $id ?></span></h1>
  <p>Edit the fields below. The Save button activates only when a change is detected.</p>
</div>

<div class="form-card">

  <!-- Change notice -->
  <div class="change-notice" id="changeNotice">
    <span>&#9998;</span>
    <span id="changeCount">0</span> field(s) modified &mdash; you can now save your changes.
  </div>

  <!-- Alert banner -->
  <div class="alert alert-error" id="formAlert">
    <span>&#9888;&#65039;</span>
    <span id="formAlertMsg">Please fill in all required fields correctly.</span>
  </div>

  <form id="updateForm" action="update_ticket.php" method="POST" novalidate>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="section-label">Personal Information</div>

    <div class="form-row">
      <div class="form-group">
        <label for="fname">First Name <span class="req">*</span></label>
        <input type="text" name="fname" id="fname" value="<?= $fname ?>" autocomplete="off">
        <div class="field-error" id="err-fname">First name is required.</div>
      </div>
      <div class="form-group">
        <label for="lname">Last Name <span class="req">*</span></label>
        <input type="text" name="lname" id="lname" value="<?= $lname ?>" autocomplete="off">
        <div class="field-error" id="err-lname">Last name is required.</div>
      </div>
    </div>

    <div class="form-group">
      <label for="email">Email Address <span class="req">*</span></label>
      <input type="email" name="email" id="email" value="<?= $email ?>" autocomplete="off">
      <div class="field-error" id="err-email">Enter a valid email address.</div>
    </div>

    <div class="form-group">
      <label for="phone_no">Contact Number <span class="req">*</span></label>
      <input type="tel" name="phone_no" id="phone_no" value="<?= $phone ?>" autocomplete="off">
      <div class="field-error" id="err-phone">Digits only, 7–15 characters.</div>
    </div>

    <div class="section-label" style="margin-top:24px;">Ticket Details</div>

    <div class="form-group">
      <label for="issue">Problem / Issue <span class="req">*</span></label>
      <input type="text" name="issue" id="issue" value="<?= $issue ?>" autocomplete="off">
      <div class="field-error" id="err-issue">Please describe the problem.</div>
    </div>

    <div class="form-group">
      <label for="dept">Department <span class="req">*</span></label>
      <select name="dept" id="dept">
        <?= $deptOptions ?>
      </select>
      <div class="field-error" id="err-dept">Please select a department.</div>
    </div>

    <div class="form-group">
      <label for="priority">Priority Level <span class="req">*</span></label>
      <select name="priority" id="priority">
        <?= $priOptions ?>
      </select>
    </div>

    <div class="form-group">
      <label for="desc">Problem Description <span class="req">*</span></label>
      <textarea name="desc" id="desc"><?= $desc ?></textarea>
      <div class="field-error" id="err-desc">Please provide a description.</div>
    </div>

    <div class="btn-row">
      <a href="search.php?id=<?= $id ?>" class="btn-cancel">&#10005; Cancel</a>
      <button type="submit" class="btn-save" id="btnSave" disabled>
        &#128190; Save Changes
      </button>
    </div>

  </form>
</div>

<div class="footer">&copy; 2026 MISO Help Page &mdash; IT Support Portal</div>

<script>
// ── Snapshot original values ───────────────────────────────────────────────
const fields = ['fname','lname','email','phone_no','issue','dept','priority','desc'];
const original = {};
fields.forEach(function(id) {
  const el = document.getElementById(id);
  original[id] = el ? el.value : '';
});

// ── Track changes ──────────────────────────────────────────────────────────
function checkChanges() {
  let changed = 0;
  fields.forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.value !== original[id]) {
      el.classList.add('changed');
      changed++;
    } else {
      el.classList.remove('changed');
    }
  });

  const btn    = document.getElementById('btnSave');
  const notice = document.getElementById('changeNotice');
  const count  = document.getElementById('changeCount');

  if (changed > 0) {
    btn.disabled = false;
    notice.classList.add('visible');
    count.textContent = changed;
  } else {
    btn.disabled = true;
    notice.classList.remove('visible');
  }
}

fields.forEach(function(id) {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('input',  checkChanges);
    el.addEventListener('change', checkChanges);
  }
});

// ── Validation helpers ─────────────────────────────────────────────────────
function showErr(errId, inputId) {
  document.getElementById(errId).classList.add('visible');
  document.getElementById(inputId).classList.add('error-field');
}
function clearErr(errId, inputId) {
  document.getElementById(errId).classList.remove('visible');
  document.getElementById(inputId).classList.remove('error-field');
}

// Clear errors on input
[
  ['err-fname','fname'], ['err-lname','lname'], ['err-email','email'],
  ['err-phone','phone_no'], ['err-issue','issue'], ['err-dept','dept'],
  ['err-desc','desc']
].forEach(function(pair) {
  const el = document.getElementById(pair[1]);
  if (!el) return;
  el.addEventListener('input',  function() { clearErr(pair[0], pair[1]); });
  el.addEventListener('change', function() { clearErr(pair[0], pair[1]); });
});

// ── Form submit validation ─────────────────────────────────────────────────
document.getElementById('updateForm').addEventListener('submit', function(e) {
  e.preventDefault();
  let valid = true;

  const fname = document.getElementById('fname').value.trim();
  const lname = document.getElementById('lname').value.trim();
  const email = document.getElementById('email').value.trim();
  const phone = document.getElementById('phone_no').value.trim();
  const issue = document.getElementById('issue').value.trim();
  const dept  = document.getElementById('dept').value;
  const desc  = document.getElementById('desc').value.trim();

  if (!fname) { showErr('err-fname','fname'); valid = false; } else clearErr('err-fname','fname');
  if (!lname) { showErr('err-lname','lname'); valid = false; } else clearErr('err-lname','lname');

  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!email || !emailRx.test(email)) { showErr('err-email','email'); valid = false; } else clearErr('err-email','email');

  const phoneRx = /^\d{7,15}$/;
  if (!phone || !phoneRx.test(phone)) { showErr('err-phone','phone_no'); valid = false; } else clearErr('err-phone','phone_no');

  if (!issue) { showErr('err-issue','issue'); valid = false; } else clearErr('err-issue','issue');
  if (!dept)  { showErr('err-dept','dept');   valid = false; } else clearErr('err-dept','dept');
  if (!desc)  { showErr('err-desc','desc');   valid = false; } else clearErr('err-desc','desc');

  if (!valid) {
    const alertEl = document.getElementById('formAlert');
    document.getElementById('formAlertMsg').textContent =
      'Please correct the highlighted fields before saving.';
    alertEl.classList.add('visible');
    alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  this.submit();
});
</script>
