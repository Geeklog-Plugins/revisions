<?php
/**
 * Revisions M0: draft/commit and missing-media policy simulation.
 * Standalone PHP 5.6-compatible test, NOT wired to Geeklog.
 */
function rev_test($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
    echo "PASS: " . $message . "\n";
}

class RevisionEditSimulation {
    public $published = array();
    public $revisions = array();
    private $pending = array();

    public function openEditor($key) {
        return $this->published[$key]; // no revision
    }
    public function stage($key, $data) {
        $this->pending[$key] = $data; // in-memory unsaved draft only
    }
    public function abandon($key) {
        unset($this->pending[$key]); // no revision
    }
    public function save($key, $success) {
        if (!isset($this->pending[$key])) { return 'no_changes'; }
        $old = $this->published[$key];
        $new = $this->pending[$key];
        if ($old === $new) { unset($this->pending[$key]); return 'unchanged'; }
        if (!$success) { return 'failed'; } // preserve pending, do not log version
        $this->published[$key] = $new;
        if (!isset($this->revisions[$key])) { $this->revisions[$key] = array(); }
        $this->revisions[$key][] = $old;
        unset($this->pending[$key]);
        return 'saved';
    }
    public function countRevisions($key) {
        return isset($this->revisions[$key]) ? count($this->revisions[$key]) : 0;
    }
}
foreach (array('article', 'staticpages') as $kind) {
    $key = $kind . ':item1';
    $state = new RevisionEditSimulation();
    $state->published[$key] = array('title' => 'First', 'content' => '[image1] <p>Hello</p>');
    $loaded = $state->openEditor($key);
    rev_test($state->countRevisions($key) === 0, $kind . ': opening editor creates no revision');
    $state->stage($key, array('title' => 'Unpublished', 'content' => '<p>Draft</p>'));
    $state->abandon($key);
    rev_test($state->countRevisions($key) === 0 && $state->published[$key] === $loaded, $kind . ': abandoned edit creates no revision');
    $state->stage($key, $loaded);
    rev_test($state->save($key, true) === 'unchanged' && $state->countRevisions($key) === 0, $kind . ': identical save creates no revision');
    $state->stage($key, array('title' => 'Second', 'content' => '[image1] <p>Updated</p>'));
    rev_test($state->save($key, false) === 'failed' && $state->countRevisions($key) === 0, $kind . ': failed save creates no revision');
    rev_test($state->save($key, true) === 'saved' && $state->countRevisions($key) === 1, $kind . ': successful edit archives prior state');
}
/**
 * UI-only placeholder manifest: actual Geeklog image resolvers must
 * supply trustworthy availability and media type. No remote requests here.
 */
function rev_media_preview_status($reference, $knownAvailable) {
    if ($knownAvailable === true) { return 'available'; }
    if ($knownAvailable === false) { return 'missing'; }
    return 'unverified'; // do not claim an external URL is missing
}
rev_test(rev_media_preview_status('[image1]', false) === 'missing', 'missing internal image identified');
rev_test(rev_media_preview_status('https://example.invalid/image.jpg', null) === 'unverified', 'external image not assumed missing');
rev_test(rev_media_preview_status('[image2]', true) === 'available', 'present image remains unchanged');
echo "M0 edit lifecycle and media preview simulation complete.\n";
