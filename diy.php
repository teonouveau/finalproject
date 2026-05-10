<?php
// diy.php  -  Save new DIY guide, show TGID on success
$connection = new mysqli("localhost", "root", "", "miniproject");
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Ensure category column exists (safe to run every time)
$connection->query("ALTER TABLE diy ADD COLUMN IF NOT EXISTS problem VARCHAR(255) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE diy ADD COLUMN IF NOT EXISTS category VARCHAR(60) NOT NULL DEFAULT 'Other'");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: diy_corner.html");
    exit();
}

$problem         = trim($_POST["a"]   ?? "");
$troubleshooting = trim($_POST["b"]   ?? "");
$category        = trim($_POST["cat"] ?? "Other");

$validCats = ["Network","Hardware","Software","Printer","Email","Security","Other"];
if (!in_array($category, $validCats)) $category = "Other";

$errors = [];
if (empty($problem))         $errors[] = "Problem title is required.";
if (empty($troubleshooting)) $errors[] = "Troubleshooting instructions are required.";

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
  .result-card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 16px;
    max-width: 580px; margin: 60px auto 40px; padding: 44px 40px; text-align: center;
  }
  .result-card .icon { font-size: 3.5rem; margin-bottom: 16px; }
  .result-card h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: 10px; }
  .result-card p { color: rgba(255,255,255,0.8); font-size: 1rem; margin-bottom: 6px; }
  /* TGID box */
  .tgid-box {
    background: rgba(255,255,255,0.12); border: 2px solid #42a5f5;
    border-radius: 12px; padding: 18px 28px; margin: 22px 0 10px;
    display: inline-block;
  }
  .tgid-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.6); margin-bottom: 6px; }
  .tgid-value { font-size: 2rem; font-weight: 900; letter-spacing: 4px; color: #fff; }
  .tgid-sub { font-size: 0.8rem; color: rgba(255,255,255,0.55); margin-top: 4px; }
  /* category pill */
  .cat-pill {
    display: inline-block; background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3); border-radius: 20px;
    padding: 4px 16px; font-size: 0.85rem; font-weight: 600; margin: 10px 0 20px;
  }
  /* progress */
  .progress-wrap { margin: 20px 0 24px; }
  .progress-bar-bg {
    background: rgba(255,255,255,0.15); border-radius: 20px;
    height: 26px; overflow: hidden; position: relative;
  }
  .progress-bar-fill {
    height: 100%; background: linear-gradient(90deg, #43a047, #66bb6a);
    border-radius: 20px; width: 0%;
    display: flex; align-items: center; justify-content: center;
    transition: width 0.04s linear;
  }
  .progress-label { font-size: 0.82rem; font-weight: 700; color: #fff; }
  /* buttons */
  .btn-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 8px; }
  .btn-add {
    background: #2e7d32; color: #fff; padding: 11px 24px;
    border-radius: 8px; text-decoration: none; font-weight: 700; transition: background 0.2s;
  }
  .btn-add:hover { background: #1b5e20; }
  .btn-browse {
    background: #1976d2; color: #fff; padding: 11px 24px;
    border-radius: 8px; text-decoration: none; font-weight: 700; transition: background 0.2s;
  }
  .btn-browse:hover { background: #1565c0; }
  .btn-home {
    background: rgba(255,255,255,0.15); color: #fff; padding: 11px 24px;
    border-radius: 8px; text-decoration: none; font-weight: 600;
    border: 1px solid rgba(255,255,255,0.3); transition: background 0.2s;
  }
  .btn-home:hover { background: rgba(255,255,255,0.25); }
  .alert-error {
    background: rgba(211,47,47,0.35); border: 1px solid #ef5350;
    border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; font-size: 0.95rem;
  }
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

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>MISO HELP PAGE | DIY Corner</title>{$css}</head><body>";
echo $navbar;

if (!empty($errors)) {
    $errList = "<ul style='text-align:left;margin-top:8px;'>";
    foreach ($errors as $e) $errList .= "<li>$e</li>";
    $errList .= "</ul>";
    echo <<<HTML
<div class="result-card">
  <div class="icon">&#9888;&#65039;</div>
  <h2>Validation Error</h2>
  <div class="alert-error">Please correct the following:{$errList}</div>
  <div class="btn-row">
    <a href="javascript:history.go(-1)" class="btn-home">&larr; Go Back &amp; Fix</a>
  </div>
</div>
HTML;
    echo '<div class="footer">&copy; 2026 MISO Help Page</div></body></html>';
    $connection->close();
    exit();
}

$stmt = $connection->prepare("INSERT INTO diy (problem, troubleshooting, category) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $problem, $troubleshooting, $category);

if ($stmt->execute()) {
    $tgid = $connection->insert_id;
    $catSafe = htmlspecialchars($category);
    $probSafe = htmlspecialchars($problem);
    echo <<<HTML
<div class="result-card">
  <div class="icon">&#9989;</div>
  <h2>Guide Successfully Added!</h2>
  <p>Your troubleshooting guide has been saved to the repository.</p>
  <div class="tgid-box">
    <div class="tgid-label">Troubleshooting Guide ID (TGID)</div>
    <div class="tgid-value">TGID-{$tgid}</div>
    <div class="tgid-sub">Save this ID to find your guide later</div>
  </div>
  <div class="cat-pill">&#128196; {$catSafe}</div>
  <p style="font-size:0.9rem;color:rgba(255,255,255,0.65);margin-bottom:16px;">
    &ldquo;{$probSafe}&rdquo;
  </p>
  <div class="progress-wrap">
    <div class="progress-bar-bg">
      <div class="progress-bar-fill" id="progressFill">
        <span class="progress-label" id="progressLabel">0%</span>
      </div>
    </div>
  </div>
  <div class="btn-row">
    <a href="diy_corner.html" class="btn-add">&#10133; Add Another</a>
    <a href="viewguide.php" class="btn-browse">&#128218; Browse Guides</a>
    <a href="index.html" class="btn-home">&#127968; Home</a>
  </div>
</div>
<script>
  var fill  = document.getElementById("progressFill");
  var label = document.getElementById("progressLabel");
  var w = 0;
  var timer = setInterval(function() {
    if (w >= 100) { clearInterval(timer); return; }
    w++;
    fill.style.width = w + "%";
    label.textContent = w + "%";
  }, 12);
</script>
HTML;
} else {
    echo <<<HTML
<div class="result-card">
  <div class="icon">&#10060;</div>
  <h2>Error Occurred</h2>
  <p>Could not save the guide. Please try again.</p>
  <p style="font-size:0.82rem;color:rgba(255,255,255,0.5);margin-top:6px;">{$connection->error}</p>
  <div class="btn-row">
    <a href="javascript:history.go(-1)" class="btn-home">&larr; Try Again</a>
    <a href="index.html" class="btn-browse">&#127968; Home</a>
  </div>
</div>
HTML;
}

$stmt->close();
$connection->close();
echo '<div class="footer">&copy; 2026 MISO Help Page</div></body></html>';
?>
