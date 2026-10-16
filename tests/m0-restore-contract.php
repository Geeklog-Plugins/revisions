<?php
/**
 * Standalone restoration contract test for Revisions M0.
 * PHP 5.6-compatible; NOT connected to Geeklog and NOT production code.
 */
class RestoreMemoryProvider
{
    public $data = array();
    public $history = array();
    public $canRestore = true;
    public $forceFailure = false;

    public function fingerprint($value)
    {
        ksort($value);
        return hash('sha256', json_encode($value));
    }

    public function seed($type, $id, $value)
    {
        $this->data[$type . ':' . $id] = $value;
    }

    public function saveViaOwner($type, $id, $expected, $incoming, $cause)
    {
        $key = $type . ':' . $id;
        if (!isset($this->data[$key])) {
            return 'not_found';
        }
        if (!$this->canRestore) {
            return 'forbidden';
        }
        $previous = $this->data[$key];
        if (!hash_equals($this->fingerprint($previous), $expected)) {
            return 'conflict';
        }
        if ($this->fingerprint($previous) === $this->fingerprint($incoming)) {
            return 'unchanged';
        }
        if ($this->forceFailure) {
            return 'failed';
        }
        // Simulated atomic commit. Real Geeklog provider+history need a durable protocol.
        $this->data[$key] = $incoming;
        if (!isset($this->history[$key])) {
            $this->history[$key] = array();
        }
        $this->history[$key][] = array('snapshot' => $previous, 'cause' => $cause);
        return 'saved';
    }

    public function restore($type, $id, $expected, $revisionIndex)
    {
        $key = $type . ':' . $id;
        if (!in_array($type, array('article', 'staticpages'), true)) {
            return 'unsupported';
        }
        if (!isset($this->history[$key][$revisionIndex])) {
            return 'not_found';
        }
        return $this->saveViaOwner(
            $type, $id, $expected, $this->history[$key][$revisionIndex]['snapshot'], 'restore'
        );
    }
}

function checkRestore($actual, $expected, $name)
{
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL: ' . $name . "\n");
        exit(1);
    }
    echo 'PASS: ' . $name . "\n";
}

foreach (array('article', 'staticpages') as $type) {
    $p = new RestoreMemoryProvider();
    $original = array('title' => 'Original', 'source' => '[youtube:id] <b>Été</b>');
    $changed = array('title' => 'Modifié', 'source' => '[youtube:id] <i>Hiver</i>');
    $p->seed($type, 'item1', $original);
    $o = $p->fingerprint($original);
    checkRestore($p->saveViaOwner($type, 'item1', $o, $changed, 'edit'), 'saved', $type . ' setup');
    $c = $p->fingerprint($changed);
    checkRestore($p->restore($type, 'item1', $o, 0), 'conflict', $type . ' rejects stale fingerprint');
    $p->canRestore = false;
    checkRestore($p->restore($type, 'item1', $c, 0), 'forbidden', $type . ' checks authorization');
    $p->canRestore = true;
    $p->forceFailure = true;
    checkRestore($p->restore($type, 'item1', $c, 0), 'failed', $type . ' provider failure');
    checkRestore(count($p->history[$type . ':item1']), 1, $type . ' failure leaves history untouched');
    $p->forceFailure = false;
    checkRestore($p->restore($type, 'item1', $c, 0), 'saved', $type . ' restored');
    checkRestore($p->data[$type . ':item1'], $original, $type . ' exact source restored');
    checkRestore($p->history[$type . ':item1'][1]['snapshot'], $changed, $type . ' replaced state recoverable');
    checkRestore($p->restore($type, 'item1', $p->fingerprint($original), 0), 'unchanged', $type . ' repeated restore idempotent');
}
echo "Memory-only restore contract checks completed; Geeklog runtime not tested.\n";
