<?php
$connection = new mysqli("localhost", "root", "", "miniproject");

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Ensure all expected columns exist (safe to run every time)
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS dept VARCHAR(100) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS phone_no VARCHAR(20) NOT NULL DEFAULT ''");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'Normal'");
$connection->query("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS description TEXT");

// ── Shared page shell ──────────────────────────────────────────────────────
function page_shell($title, $content) {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MISO HELP PAGE | {$title}</title>
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
    .result-card {
      background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.2); border-radius: 16px;
      max-width: 560px; margin: 60px auto; padding: 40px 36px; text-align: center;
    }
    .result-card .icon { font-size: 3.5rem; margin-bottom: 16px; }
    .result-card h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: 10px; }
    .result-card p { color: rgba(255,255,255,0.8); font-size: 1rem; margin-bottom: 6px; }
    .result-card .ticket-id {
      display: inline-block; background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3); border-radius: 8px;
      padding: 10px 24px; font-size: 1.4rem; font-weight: 800;
      letter-spacing: 2px; margin: 16px 0 24px;
    }
    .btn-home {
      display: inline-block; background: #1976d2; color: #fff;
      padding: 12px 32px; border-radius: 8px; text-decoration: none;
      font-weight: 700; font-size: 1rem; transition: background 0.2s;
    }
    .btn-home:hover { background: #1565c0; }
    .alert-error {
      background: rgba(211,47,47,0.35); border: 1px solid #ef5350;
      border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;
      font-size: 0.95rem;
    }
    .footer {
      text-align: center; padding: 18px; font-size: 0.82rem;
      color: rgba(255,255,255,0.5); border-top: 1px solid rgba(255,255,255,0.1);
      position: fixed; bottom: 0; width: 100%;
    }
  </style>
</head>
<body>
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
  {$content}
  <div class="footer">&copy; 2026 MISO Help Page &mdash; IT Support Portal</div>
</body>
</html>
HTML;
}

// ── Process form ───────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize inputs
    $fname    = trim($_POST["fname"] ?? '');
    $lname    = trim($_POST["lname"] ?? '');
    $email    = trim($_POST["email"] ?? '');
    $issue    = trim($_POST["issue"] ?? '');
    $dept     = trim($_POST["dept"] ?? '');
    $phone    = trim($_POST["phone_no"] ?? '');
    $priority = trim($_POST["priority"] ?? '');
    $desc     = trim($_POST["desc"] ?? '');

    // Server-side validation
    $errors = [];
    if (empty($fname))  $errors[] = "First name is required.";
    if (empty($lname))  $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($issue))  $errors[] = "Problem / issue is required.";
    if (empty($dept))   $errors[] = "Department is required.";
    if (empty($phone) || !preg_match('/^\d{7,15}$/', $phone))
        $errors[] = "A valid contact number is required (digits only, 7–15 characters).";
    if (empty($desc))   $errors[] = "Problem description is required.";

    if (!empty($errors)) {
        $errList = '<ul style="text-align:left;margin-top:8px;">';
        foreach ($errors as $e) $errList .= "<li>$e</li>";
        $errList .= '</ul>';
        $content = <<<HTML
<div class="result-card">
  <div class="icon">⚠️</div>
  <h2>Validation Error</h2>
  <div class="alert-error">
    Please correct the following errors:{$errList}
  </div>
  <a href="javascript:history.go(-1)" class="btn-home">← Go Back &amp; Fix</a>
</div>
HTML;
        page_shell("Error", $content);
        exit();
    }

    $name = $fname . " " . $lname;

    // Prepared statement to prevent SQL injection
    $stmt = $connection->prepare(
        "INSERT INTO tickets (name, email, issue, dept, phone_no, priority, description)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssssss", $name, $email, $issue, $dept, $phone, $priority, $desc);

    if ($stmt->execute()) {
        $ticket_id = $connection->insert_id;
        $content = <<<HTML
<div class="result-card">
  <div class="icon">✅</div>
  <h2>Ticket Successfully Submitted!</h2>
  <p>Your support request has been received. Please save your Ticket ID:</p>
  <div class="ticket-id"># {$ticket_id}</div>
  <p style="margin-bottom:24px;">Use this ID to search or delete your ticket later.</p>
  <a href="index.html" class="btn-home">🏠 Back to Home</a>
</div>
HTML;
        page_shell("Ticket Submitted", $content);
    } else {
        $content = <<<HTML
<div class="result-card">
  <div class="icon">❌</div>
  <h2>Error Occurred</h2>
  <p>An error occurred while submitting your ticket. Please try again.</p>
  <p style="font-size:0.82rem;color:rgba(255,255,255,0.5);margin-top:8px;">{$connection->error}</p>
  <br>
  <a href="javascript:history.go(-1)" class="btn-home">← Try Again</a>
</div>
HTML;
        page_shell("Error", $content);
    }

    $stmt->close();
    $connection->close();
    exit();
}

// If accessed directly without POST
header("Location: send_ticket.html");
exit();
?>
