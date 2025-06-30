<?php
// Debug Script - speichere als debug.php im Root-Verzeichnis

echo "<h1>URL Rewriting Debug</h1>";

echo "<h2>Request Information:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'NOT SET') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET') . "\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "\n";
echo "</pre>";

echo "<h2>\$_GET Array:</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>\$_POST Array:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>\$_REQUEST Array:</h2>";
echo "<pre>";
print_r($_REQUEST);
echo "</pre>";

echo "<h2>CBroute Detection:</h2>";
echo "<pre>";
if (isset($_REQUEST['CBroute'])) {
    echo "✅ CBroute gefunden: " . $_REQUEST['CBroute'];
} else {
    echo "❌ CBroute NICHT gefunden!";
}
echo "</pre>";

echo "<h2>Test URLs:</h2>";
echo "<p>Teste diese URLs:</p>";
echo "<ul>";
echo "<li><a href='/debug.php?CBroute=manual'>Manual: /debug.php?CBroute=manual</a></li>";
echo "<li><a href='/test'>Rewrite Test: /test</a></li>";
echo "<li><a href='/admin'>Admin Test: /admin</a></li>";
echo "<li><a href='/interface/test'>Interface Test: /interface/test</a></li>";
echo "</ul>";

echo "<h2>Server Environment:</h2>";
echo "<pre>";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'NOT SET') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET') . "\n";
echo "</pre>";
?>