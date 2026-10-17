<?php
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
if (!SEC_inGroup('Root') || !SEC_hasRights('revisions.admin')) {
    COM_output(COM_createHTMLDocument('Access denied'));
    exit;
}
function REVISIONS_ADMIN_canEdit($r) {
    return SEC_hasAccess($r['owner_id'], $r['group_id'], $r['perm_owner'], $r['perm_group'], $r['perm_members'], $r['perm_anon']) >= 3;
}
function REVISIONS_ADMIN_pageTitle($id) {
    global $_TABLES;
    $safe = DB_escapeString($id);
    $r = DB_query("SELECT sp_title FROM {$_TABLES['staticpage']} WHERE sp_id='$safe' LIMIT 1", 1);
    if ($r && DB_numRows($r) === 1) {
        $page = DB_fetchArray($r);
        if (isset($page['sp_title']) && trim($page['sp_title']) !== '') { return $page['sp_title']; }
    }
    return '(page unavailable)';
}
function R_H($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function REVISIONS_ADMIN_lineDiff($before, $after)
{
    if ($before === $after) { return '<p>No line changes.</p>'; }
    // Diff is read-only and bounded to avoid expensive dynamic programming.
    if (strlen($before) > 50000 || strlen($after) > 50000) {
        return '<p>Content is too long for line comparison; use the source panels above.</p>';
    }
    $a = preg_split('/\\r\\n|\\r|\\n/', $before);
    $b = preg_split('/\\r\\n|\\r|\\n/', $after);
    if (count($a) > 160 || count($b) > 160) {
        return '<p>More than 160 lines: use the full source panels above.</p>';
    }
    $n = count($a); $m = count($b); $dp = array();
    for ($i = 0; $i <= $n; $i++) { $dp[$i] = array_fill(0, $m + 1, 0); }
    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $m - 1; $j >= 0; $j--) {
            $dp[$i][$j] = ($a[$i] === $b[$j]) ? 1 + $dp[$i+1][$j+1] : max($dp[$i+1][$j], $dp[$i][$j+1]);
        }
    }
    $html = '<div class="revisions-line-diff" aria-label="Changes by line">';
    $i = 0; $j = 0; $changes = 0;
    while ($i < $n || $j < $m) {
        if ($i < $n && $j < $m && $a[$i] === $b[$j]) { $i++; $j++; continue; }
        if ($j < $m && ($i === $n || $dp[$i][$j+1] >= $dp[$i+1][$j])) {
            $html .= '<div class="revisions-line-added"><span>+ Added: </span><code>' . R_H($b[$j]) . '</code></div>';
            $j++;
        } else {
            $html .= '<div class="revisions-line-removed"><span>− Removed: </span><code>' . R_H($a[$i]) . '</code></div>';
            $i++;
        }
        $changes++;
    }
    return $html . '</div><p>' . (int)$changes . ' changed lines (added and removed).</p>';
}

global $_TABLES, $_CONF;
$message = '';
if (isset($_POST['action']) && $_POST['action'] === 'baseline') {
    if (!SEC_checkToken()) { $message='Security token invalid.'; }
    else {
        $id = isset($_POST['sp_id']) ? trim((string)$_POST['sp_id']) : '';
        if ($id === '' || !SEC_hasRights('staticpages.edit')) { $message='Not authorized.'; }
        else {
            $id = COM_sanitizeID($id);
            $row = DB_query("SELECT owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon FROM {$_TABLES['staticpage']} WHERE sp_id='" . DB_escapeString($id) . "'",1);
            if (!$row || DB_numRows($row) !== 1 || !REVISIONS_ADMIN_canEdit(DB_fetchArray($row))) {
                $message='Cannot initialize page; check page and access.';
            } else {
                $message=REVISIONS_initialize($id) ? 'Tracking initialized (or already active).' : 'Page not eligible or initialization failed.';
            }
        }
    }
}
if (isset($_POST['action']) && $_POST['action'] === 'reconcile') {
    SEC_checkToken();
    $id = isset($_POST['sp_id']) ? COM_sanitizeID((string) $_POST['sp_id']) : '';
    $idSql = DB_escapeString($id);
    $q = DB_query("SELECT owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon FROM {$_TABLES['staticpage']} WHERE sp_id='$idSql' LIMIT 1", 1);
    $tracking = DB_query("SELECT sp_id FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$idSql' LIMIT 1", 1);
    if ($id === '' || !SEC_hasRights('staticpages.edit') || !$q || DB_numRows($q) !== 1 ||
        !REVISIONS_ADMIN_canEdit(DB_fetchArray($q)) || !$tracking || DB_numRows($tracking) !== 1) {
        $message = 'No tracked page found or insufficient permissions.';
    } else {
        $message = REVISIONS_reconcilePage($id)
            ? 'Tracking checked. Check recent versions below (author 0 indicates recovered edit).'
            : 'Page is not eligible for tracking.';
    }
}

// Read-only revision detail: verify the current page ACL before revealing historical content.
$revisionDetail = '';
if (isset($_GET['revision_id']) && ctype_digit((string) $_GET['revision_id'])) {
    $rid = (int) $_GET['revision_id'];
    $res = DB_query("SELECT revision_id,sp_id,snapshot_json,content_hash,actor_id,created_at "
        . "FROM {$_TABLES['revisions_history']} WHERE revision_id=$rid LIMIT 1",1);
    if ($res && DB_numRows($res) === 1) {
        $rev = DB_fetchArray($res);
        $escapedId = DB_escapeString($rev['sp_id']);
        $aclRes = DB_query("SELECT owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon "
            . "FROM {$_TABLES['staticpage']} WHERE sp_id='$escapedId' LIMIT 1",1);
        if ($aclRes && DB_numRows($aclRes) === 1 && SEC_hasRights('staticpages.edit')
            && REVISIONS_ADMIN_canEdit(DB_fetchArray($aclRes))) {
            $old = json_decode($rev['snapshot_json'], true);
            $current = REVISIONS_snapshot($rev['sp_id']);
            if (is_array($old) && hash('sha256', $rev['snapshot_json']) === $rev['content_hash']) {
                $revisionDetail .= '<section style="border:1px solid #ccc;padding:16px;margin:24px 0">'
                    . '<h3>Revision #' . R_H($rid) . ' — read-only</h3>'
                    . '<p>Page ' . R_H($rev['sp_id']) . ' · ' . R_H($rev['created_at']) . '</p>'
                    . '<p>Historical source is escaped: HTML, PHP and autotags are never executed.</p>'
                    . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">';
                foreach (array('sp_title'=>'Title','sp_content'=>'Page content','meta_description'=>'Meta description') as $field=>$label) {
                    $oldValue = isset($old[$field]) ? (string)$old[$field] : '';
                    $currentValue = is_array($current) && isset($current[$field]) ? (string)$current[$field] : '';
                    $changed = ($oldValue !== $currentValue);
                    $revisionDetail .= '<div class="revisions-compare-field' . ($changed ? ' revisions-compare-field--changed' : '') . '">'
                        . '<h4>' . R_H($label) . ' <small>' . ($changed ? 'Changed' : 'Unchanged') . '</small></h4>'
                        . '<strong>Archived version</strong>'
                        . '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;border:1px solid #ddd;padding:10px">'
                        . R_H($oldValue) . '</pre><strong>Current version</strong>'
                        . '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;border:1px solid #ddd;padding:10px">'
                        . R_H($currentValue) . '</pre></div>';
                }
                $oldSource = isset($old['sp_content']) ? (string)$old['sp_content'] : '';
                $currentSource = is_array($current) && isset($current['sp_content']) ? (string)$current['sp_content'] : '';
                $revisionDetail .= '<div class="revisions-compare-diff"><h4>Changed content lines</h4>'
                    . REVISIONS_ADMIN_lineDiff($oldSource, $currentSource)
                    . '</div>';
                // Restore preflight is informational: NEVER update a provider table here.
                $currentEncoded = is_array($current) ? REVISIONS_encode($current) : false;
                $trackedHash = false;
                $baselineSql = DB_escapeString($rev['sp_id']);
                $baselineCheck = DB_query("SELECT content_hash FROM {$_TABLES['revisions_baseline']} WHERE sp_id='$baselineSql' LIMIT 1",1);
                if ($baselineCheck && DB_numRows($baselineCheck) === 1) {
                    $baselineRow = DB_fetchArray($baselineCheck);
                    $trackedHash = isset($baselineRow['content_hash']) ? $baselineRow['content_hash'] : false;
                }
                $sourceMatches = $currentEncoded !== false && $trackedHash !== false
                    && hash_equals($trackedHash, hash('sha256', $currentEncoded));
                $revisionDetail .= '<div class="revisions-restore-preflight" role="note">'
                    . '<h4>Restore readiness — preview only</h4>'
                    . '<p>Automatic restoration remains disabled. This check never changes the page.</p>';
                if ($current === false) {
                    $revisionDetail .= '<p>Unavailable: current page is missing or uses PHP/templates.</p>';
                } elseif (!$sourceMatches) {
                    $revisionDetail .= '<p>Not ready: the current page differs from its tracked baseline. '
                        . 'Check changes first and review the new revision before considering restoration.</p>';
                } elseif ($old === $current) {
                    $revisionDetail .= '<p>No action needed: archived and current editorial fields match.</p>';
                } else {
                    $revisionDetail .= '<p>Preview checks passed: the snapshot checksum is valid, '
                        . 'the current page is available, and the tracked baseline matches it.</p>'
                        . '<p>For now, use the archived HTML source below to restore manually in the '
                        . 'Static Pages editor. The current content will be saved in history by the normal save hook.</p>';
                }
                $revisionDetail .= '</div>';
                $rawHtml = isset($old['sp_content']) ? (string) $old['sp_content'] : '';
                $revisionDetail .= '</div>'
                    . '<h4>Archived HTML source — copy/paste</h4>'
                    . '<p>Select and copy the HTML source below to paste into the Static Pages editor in HTML/source mode. '
                    . 'Nothing is executed or restored automatically.</p>'
                    . '<textarea readonly rows="12" spellcheck="false" '
                    . 'aria-label="Archived HTML source" '
                    . 'style="box-sizing:border-box;width:100%;font-family:monospace;white-space:pre;'
                    . 'padding:12px;min-height:160px;overflow:auto">'
                    . R_H($rawHtml) . '</textarea>'
                    . '<p><small>Original HTML and autotags are preserved; attached images and media are not included.</small></p>'
                    . '<p>No restore action is provided in this pilot.</p></section>';
            } else {
                $message = 'Archived snapshot could not be verified.';
            }
        } else {
            $message = 'You are not allowed to view this page history.';
        }
    }
}
$section = isset($_GET['section']) ? (string) $_GET['section'] : 'home';
if (isset($_GET['revision_id'])) { $section = 'history'; }
if (isset($_POST['action'])) { $section = 'pages'; }
if (!in_array($section, array('home','pages','history','help'), true)) { $section = 'home'; }
$base = $_CONF['site_admin_url'] . '/plugins/revisions/index.php';
$out = '<link rel="stylesheet" href="' . R_H($_CONF['site_admin_url'] . '/plugins/revisions/admin.css?v=' . plugin_chkVersion_revisions()) . '">'
    . '<h1>Revisions ' . R_H(plugin_chkVersion_revisions()) . '</h1>'
    . '<p>Static Pages history: capture, inspect, and copy earlier editorial source. Restoration is not yet available.</p>';
$navItems = array('home'=>'Overview','pages'=>'Tracked pages','history'=>'History','help'=>'Help');
$out .= '<nav class="plugin-admin-nav" aria-label="Revisions administration"><div class="plugin-admin-nav__primary">';
foreach ($navItems as $key => $label) {
    $active = $section === $key;
    $out .= '<a class="plugin-admin-nav__item' . ($active ? ' is-active' : '') . '"'
        . ($active ? ' aria-current="page"' : '')
        . ' href="' . R_H($base . '?section=' . $key) . '">' . R_H($label) . '</a>';
}
$out .= '</div></nav>';
if ($message !== '') { $out .= '<p class="revisions-message" role="status">' . R_H($message) . '</p>'; }
if ($section === 'home') {
    $countTracked = DB_query("SELECT COUNT(*) AS cnt FROM {$_TABLES['revisions_baseline']}",1);
    $countHistory = DB_query("SELECT COUNT(*) AS cnt FROM {$_TABLES['revisions_history']}",1);
    $pagesNumber = $countTracked ? DB_fetchArray($countTracked) : array('cnt'=>'?');
    $revsNumber = $countHistory ? DB_fetchArray($countHistory) : array('cnt'=>'?');
    $out .= '<h2>Overview</h2><p><strong>' . R_H($pagesNumber['cnt']) . '</strong> tracked pages · <strong>'
        . R_H($revsNumber['cnt']) . '</strong> archived revisions.</p>'
        . '<p><a href="' . R_H($base . '?section=pages') . '">Manage tracked pages</a> · '
        . '<a href="' . R_H($base . '?section=history') . '">Browse history</a></p>'
        . '<h3>Getting started</h3><ol><li>Open Tracked pages and initialize an existing simple Static Page.</li>'
        . '<li>Edit that page and save your changes in Geeklog.</li>'
        . '<li>Open History to inspect and copy the previous source.</li></ol>'
        . '<p>PHP and template Static Pages are excluded. No automatic restoration or asset backup is included.</p>';
} elseif ($section === 'pages') {
    $out .= '<h2>Start tracking a page</h2><p>Use the exact Static Page ID. Tracking begins from the current saved state, not past edits.</p>';
    $token = SEC_createToken();
    $out .= '<form class="revisions-track-form" method="post" action="' . R_H($base) . '">'
        . '<label for="revisions-sp-id">Static Page ID</label>'
        . '<input id="revisions-sp-id" name="sp_id" maxlength="128" required>'
        . '<input type="hidden" name="action" value="baseline">'
        . '<input type="hidden" name="' . R_H(CSRF_TOKEN) . '" value="' . R_H($token) . '">'
        . '<button type="submit">Start tracking</button></form>';
    $tracked = DB_query("SELECT sp_id,updated_at FROM {$_TABLES['revisions_baseline']} ORDER BY updated_at DESC LIMIT 50", 1);
$out .= '<h2>Tracked pages</h2><p>Check changes compares the current page with the last saved baseline.</p>';
$out .= '<p><small>Tracking table: ' . R_H($_TABLES['revisions_baseline']) . '; History table: ' . R_H($_TABLES['revisions_history']) . '</small></p>';
if ($tracked && DB_numRows($tracked) > 0) {
    $out .= '<table><thead><tr><th>Page</th><th>Last tracking update</th><th>Check changes</th></tr></thead><tbody>';
    while ($t = DB_fetchArray($tracked)) {
        $token = SEC_createToken();
        $out .= '<tr><td><strong>' . R_H(REVISIONS_ADMIN_pageTitle($t['sp_id'])) . '</strong><br><small>' . R_H($t['sp_id']) . '</small>' . '</td><td>' . R_H($t['updated_at']) . '</td><td>'
            . '<form method="post" action="' . R_H($_CONF['site_admin_url'] . '/plugins/revisions/index.php') . '">'
            . '<input type="hidden" name="sp_id" value="' . R_H($t['sp_id']) . '">'
            . '<input type="hidden" name="action" value="reconcile">'
            . '<input type="hidden" name="' . R_H(CSRF_TOKEN) . '" value="' . R_H($token) . '">'
            . '<button type="submit">Check changes</button></form></td></tr>';
    }
    $out .= '</tbody></table>';
} else {
    $out .= '<p><strong>No pages are being tracked yet.</strong> Add the exact existing Static Page ID above and verify the confirmation message. If the message reports an error, inspect Geeklog error.log for SQL details.</p>';
}
} elseif ($section === 'history') {
    $q = DB_query("SELECT revision_id,sp_id,actor_id,created_at FROM {$_TABLES['revisions_history']} ORDER BY revision_id DESC LIMIT 50",1);
    $out .= $revisionDetail;
    $out .= '<h3>Revision history</h3><table><thead><tr><th>Revision</th><th>Page</th><th>Author</th><th>Date</th></tr></thead><tbody>';
if ($q) { while ($r=DB_fetchArray($q)) {
    $detailUrl = $_CONF['site_admin_url'] . '/plugins/revisions/index.php?revision_id=' . (int) $r['revision_id'];
    $out .= '<tr><td><a href="' . R_H($detailUrl) . '">#'.R_H($r['revision_id']).' — View</a></td><td>'.R_H($r['sp_id']).'</td><td>'.R_H($r['actor_id']).'</td><td>'.R_H($r['created_at']).'</td></tr>';
} }
$out .= '</tbody></table><p>Snapshots are stored in database. Select View to compare archived source and current source.</p>';

} else {
    $out .= '<h2>Help</h2><p><strong>How tracking works:</strong> initialize a simple Static Page, edit and save it, then look in History. An archive contains the earlier stored source.</p>'
        . '<p><strong>Missing updates:</strong> use Check changes in Tracked pages. A recovered change has author ID 0 because the original editor is unknown.</p>'
        . '<p><strong>HTML source:</strong> select View in History, then copy the escaped source from the read-only field into the Geeklog HTML/source editor.</p>'
        . '<p><strong>Limitations:</strong> PHP/template pages, file recovery, automatic restore and multi-writer atomicity are not supported in this pilot.</p>'
        . '<p><a href="https://github.com/hostellerie/revisions">Project documentation</a></p>';
}
COM_output(COM_createHTMLDocument($out,array('pagetitle'=>'Revisions')));