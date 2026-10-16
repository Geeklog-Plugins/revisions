<?php
/** Revisions M0: recoverable intent protocol simulation. PHP 5.6 syntax.
 * NOT a Geeklog integration, SQL implementation, or concurrency guarantee.
 */
function assert_m0($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $label . "\n"); exit(1); }
    echo "PASS: " . $label . "\n";
}
function hash_m0($data) {
    ksort($data);
    return hash('sha256', json_encode($data));
}
class RecoverableRevisionSimulation {
    public $current = array();
    public $intents = array();
    public $history = array();

    public function seed($key, $source) { $this->current[$key] = $source; }

    // Stages an old-state snapshot BEFORE a provider-owned save.
    public function prepare($op, $key, $expectedOld, $newSource) {
        if (isset($this->intents[$op])) { return 'duplicate_operation'; }
        if (!isset($this->current[$key])) { return 'missing'; }
        $old = $this->current[$key];
        if (hash_m0($old) !== $expectedOld) { return 'conflict'; }
        if (hash_m0($old) === hash_m0($newSource)) { return 'unchanged'; }
        $this->intents[$op] = array(
            'key' => $key, 'old' => $old, 'old_hash' => hash_m0($old),
            'new_hash' => hash_m0($newSource), 'state' => 'prepared'
        );
        return 'prepared';
    }
    // Simulates provider save only; cannot be called for an unstaged operation.
    public function providerSave($op, $newSource, $succeeds) {
        if (!isset($this->intents[$op])) { return 'missing_intent'; }
        if (hash_m0($newSource) !== $this->intents[$op]['new_hash']) { return 'invalid_payload'; }
        if (!$succeeds) { return 'failed'; }
        $key = $this->intents[$op]['key'];
        if (hash_m0($this->current[$key]) !== $this->intents[$op]['old_hash']) {
            return 'conflict';
        }
        $this->current[$key] = $newSource;
        return 'saved';
    }
    public function reconcile($op) {
        if (!isset($this->intents[$op])) { return 'missing_intent'; }
        $i = &$this->intents[$op];
        $currentHash = hash_m0($this->current[$i['key']]);
        if ($currentHash === $i['old_hash']) {
            $i['state'] = 'aborted';
            return 'aborted'; // no provider mutation observed; cleanup later
        }
        if ($currentHash !== $i['new_hash']) {
            $i['state'] = 'needs_review'; // unexpected third state, no guessing
            return 'needs_review';
        }
        if (!isset($this->history[$op])) {
            $this->history[$op] = array('key' => $i['key'], 'snapshot' => $i['old']);
        }
        $i['state'] = 'committed';
        return 'committed';
    }
}
foreach (array('article', 'staticpages') as $provider) {
    $s = new RecoverableRevisionSimulation();
    $key = $provider . ':item';
    $old = array('title'=>'Before','source'=>'[image1] Old');
    $new = array('title'=>'After','source'=>'[image1] New');
    $s->seed($key,$old);
    assert_m0($s->prepare('op1',$key,hash_m0($old),$new)==='prepared', $provider.' intent persisted first');
    assert_m0($s->providerSave('op1',$new,false)==='failed', $provider.' failed provider save');
    assert_m0($s->reconcile('op1')==='aborted' && count($s->history)===0, $provider.' no false revision after failure');
    assert_m0($s->prepare('op2',$key,hash_m0($old),$new)==='prepared', $provider.' prepare again');
    assert_m0($s->providerSave('op2',$new,true)==='saved', $provider.' owner saves');
    // Simulate process death before commit notice; pending old snapshot survives.
    assert_m0($s->reconcile('op2')==='committed', $provider.' recovery after interrupted post-save');
    assert_m0($s->reconcile('op2')==='committed' && count($s->history)===1, $provider.' idempotent reconcile');
    assert_m0($s->history['op2']['snapshot']===$old, $provider.' exact previous source');
    assert_m0($s->prepare('op3',$key,hash_m0($old),$old)==='conflict', $provider.' stale writer blocked');
    assert_m0($s->prepare('op4',$key,hash_m0($new),$old)==='prepared', $provider.' prepare restore');
    $s->current[$key]=array('title'=>'Third party','source'=>'Changed concurrently');
    assert_m0($s->reconcile('op4')==='needs_review', $provider.' ambiguous state never auto-committed');
}
echo "M0 recoverable protocol simulated only.\n";
