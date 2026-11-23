<?php

// Platformer version + update check (GitHub, once a day, result cached in cache/)
// Instances can override this page in src/admin/modules/dashboard/views/main.php
$versionLocal = trim((string)@file_get_contents(CAPPS . 'VERSION'));
$cacheFile = dirname(SOURCEDIR) . '/cache/update-check.txt';
if (!is_file($cacheFile) || time() - filemtime($cacheFile) > 86400) {
    if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0775, true);
    $remote = @file_get_contents('https://raw.githubusercontent.com/cybobcom/Platformer/main/src/capps/VERSION', false, stream_context_create(['http' => ['timeout' => 3]]));
    @file_put_contents($cacheFile, $remote === false ? '' : trim($remote)); // no connection: try again tomorrow
}
$versionRemote = trim((string)@file_get_contents($cacheFile));
$hasUpdate = $versionLocal !== '' && preg_match('/^\d+\.\d+\.\d+$/', $versionRemote) && version_compare($versionRemote, $versionLocal, '>');

?>
<div class="contentarea p-3 mt-4">
    <b>Platformer</b> <?php echo htmlspecialchars($versionLocal !== '' ? $versionLocal : '?'); ?>
    <?php if ($hasUpdate): ?>
        · <span class="text-danger"><b>Update verfügbar: <?php echo htmlspecialchars($versionRemote); ?></b></span>
        (<a href="https://github.com/cybobcom/Platformer" target="_blank" rel="noopener">GitHub</a>)
    <?php endif; ?>
    <?php if (!defined('DEBUG_MODE') && empty(CONFIGURATION['trusted_hosts'])): // SECURITY: servers should restrict the Host header ?>
        <div class="text-danger mt-2"><b>Hinweis:</b> <code>$arrConf['trusted_hosts']</code> ist in der inc.localconf.php leer, bitte die Domains der Seite eintragen.</div>
    <?php endif; ?>
</div>
