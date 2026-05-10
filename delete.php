<?php
$connection = new mysqli("localhost", "root", "", "miniproject");

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Ensure all expected columns exist
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS dept VARCHAR(100) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS phone_no VARCHAR(20) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'Normal'");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS description TEXT");

// ── Shared styles ──────────────────────────────────────────────────────────
$sharedStyles = <<<CSS
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
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
    border-radius: 6px; cursor: pointer; font-size: 0.9rem;
    text-decoration: none; transition: background 0.2s;
  }
  .nav-btn:hover { background: rgba(255,255,255,0.3); }
  .page-title { text-align: center; padding: 36px 20px 10px; }
  .page-title h1 { font-size: 2rem; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.4); }
  .page-title p { margin-top: 8px; color: rgba(255,255,255,0.75); }
  .card {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 16px;
    max-width: 520px; margin: 24px auto 60px; padding: 36px 40px;
  }
  .form-group { margin-bottom: 20px; }
  .form-group label { display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 6px; }
  .form-group label .req { color: #ff6b6b; margin-left: 3px; }
  input[type="number"] {
    width: 100%; padding: 11px 14px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.12); color: #fff; font-size: 0.95rem;
    outline: none; transition: border-color 0.2s;
  }
  input[type="number"]:focus { border-color: #42a5f5; background: rgba(255,255,255,0.2); }
  input.error-field { border-color: #ff6b6b !important; }
  .field-error { color: #ff6b6b; font-size: 0.8rem; margin-top: 4px; display: none; }
  .field-error.visible { display: block; }
  .warning-box {
    background: rgba(255,152,0,0.2); border: 1px solid #ffa726;
    border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;
    font-size: 0.88rem; color: rgba(255,255,255,0.9);
  }
  .btn-delete {
    width: 100%; padding: 13px; background: #c62828; color: #fff;
    border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
    cursor: pointer; transition: background 0.2s;
  }
  .btn-delete:hover { background: #b71c1c; }
  .result-card { text-align: center; }
  .result-card .icon { font-size: 3.5rem; margin-bottom: 16px; }
  .result-card h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: 10px; }
  .result-card p { color: rgba(255,255,255,0.8); }
  .btn-back {
    display: inline-block; background: rgba(255,255,255,0.15); color: #fff;
    padding: 10px 24px; border-radius: 8px; text-decoration: none;
    font-weight: 600; margin-top: 20px; border: 1px solid rgba(255,255,255,0.3);
    transition: background 0.2s;
  }
  .btn-back:hover { background: rgba(255,255,255,0.25); }
  .btn-home {
    display: inline-block; background: #1976d2; color: #fff;
    padding: 10px 24px; border-radius: 8px; text-decoration: none;
    font-weight: 600; margin-top: 20px; margin-left: 10px; transition: background 0.2s;
  }
  .btn-home:hover { background: #1565c0; }
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
    <a href="index.html" class="nav-btn">🏠 Home</a>
    <a href="javascript:history.go(-1)" class="nav-btn">← Back</a>
  </div>
</nav>
HTML;

// ── Handle delete ──────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"])) {
    $id = intval($_POST["id"]);

    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>MISO HELP PAGE | Delete Ticket</title>{$sharedStyles}</head><body>";
    echo $navbar;
    echo '<div class="page-title"><h1>🗑️ Delete a Ticket</h1></div>';
    echo '<div class="card"><div class="result-card">';

    if ($id <= 0) {
        echo '<div class="icon">⚠️</div>';
        echo '<h2>Invalid Ticket ID</h2>';
        echo '<p>Please enter a valid positive Ticket ID.</p>';
        echo '<div style="text-align:center;"><a href="delete.php" class="btn-back">← Try Again</a><a href="index.html" class="btn-home">🏠 Home</a></div>';
    } else {
        // Check if ticket exists first
        $check = $connection->prepare("SELECT id FROM tickets WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            echo '<div class="icon">🔎</div>';
            echo "<h2>Ticket Not Found</h2>";
            echo "<p>No ticket with ID <strong>#{$id}</strong> exists.</p>";
            echo '<div style="text-align:center;"><a href="delete.php" class="btn-back">← Try Again</a><a href="index.html" class="btn-home">🏠 Home</a></div>';
        } else {
            $check->close();
            $stmt = $connection->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                echo '<div class="icon">✅</div>';
                echo "<h2>Ticket #{$id} Deleted</h2>";
                echo '<p>The ticket has been successfully removed from the system.</p>';
            } else {
                echo '<div class="icon">❌</div>';
                echo '<h2>Error Occurred</h2>';
                echo '<p>Could not delete the ticket. Please try again.</p>';
                echo '<p style="font-size:0.82rem;color:rgba(255,255,255,0.5);margin-top:6px;">' . $connection->error . '</p>';
            }
            $stmt->close();
            echo '<div style="text-align:center;"><a href="delete.php" class="btn-back">← Delete Another</a><a href="index.html" class="btn-home">🏠 Home</a></div>';
        }
    }

    echo '</div></div>';
    echo '<div class="footer">&copy; 2026 MISO Help Page</div>';
    echo '</body></html>';
    $connection->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MISO HELP PAGE | Delete Ticket</title>
  <?php echo $sharedStyles; ?>
</head>
<body>
  <?php echo $navbar; ?>

  <div class="page-title">
    <h1>🗑️ Delete a Ticket</h1>
    <p>Enter the Ticket ID you want to permanently remove</p>
  </div>

  <div class="card">
    <div class="warning-box">
      ⚠️ <strong>Warning:</strong> This action is permanent and cannot be undone.
    </div>
    <form id="deleteForm" method="POST" novalidate>
      <div class="form-group">
        <label for="id">Ticket ID <span class="req">*</span></label>
        <input type="number" name="id" id="id" placeholder="e.g. 42" min="1">
        <div class="field-error" id="err-id">Please enter a valid Ticket ID (positive number).</div>
      </div>
      <button type="submit" class="btn-delete">🗑️ Delete Ticket</button>
    </form>
  </div>

  <div class="footer">&copy; 2026 MISO Help Page &mdash; IT Support Portal</div>

  <script>
    const form = document.getElementById('deleteForm');
    form.addEventListener('submit', function(e) {
      const val = parseInt(document.getElementById('id').value, 10);
      const errEl = document.getElementById('err-id');
      const inputEl = document.getElementById('id');
      if (!val || val <= 0) {
        e.preventDefault();
        errEl.classList.add('visible');
        inputEl.classList.add('error-field');
        return;
      }
      errEl.classList.remove('visible');
      inputEl.classList.remove('error-field');
      if (!confirm('Are you sure you want to delete Ticket #' + val + '? This cannot be undone.')) {
        e.preventDefault();
      }
    });
    document.getElementById('id').addEventListener('input', function() {
      document.getElementById('err-id').classList.remove('visible');
      this.classList.remove('error-field');
    });
  </script>
</body>
</html>
