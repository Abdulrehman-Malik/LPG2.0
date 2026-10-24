<?php
/**
 * QA helper: prints the url-encoded body a browser would submit for the first form posting to
 * <action> in an HTML page, with optional overrides. Lets suites change one setting while
 * submitting every other field unchanged.
 *
 *   php form_body.php page.html shop-settings/save receipt_title=QA [name=value ...]
 */
[$self, $file, $action] = $argv + [null, null, null];
$overrides = [];
foreach (array_slice($argv, 3) as $pair) {
    [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
    $overrides[$k] = $v;
}
$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML((string) file_get_contents($file));
$xp = new DOMXPath($doc);
$form = null;
foreach ($xp->query('//form') as $f) {
    if (str_contains((string) $f->getAttribute('action'), $action)) {
        $form = $f;
        break;
    }
}
if (!$form) {
    fwrite(STDERR, "form for $action not found\n");
    exit(1);
}
$pairs = [];
foreach ($xp->query('.//input|.//select|.//textarea', $form) as $el) {
    $name = $el->getAttribute('name');
    if ($name === '' || $el->hasAttribute('disabled')) {
        continue;
    }
    $tag = strtolower($el->nodeName);
    if ($tag === 'input') {
        $type = strtolower($el->getAttribute('type') ?: 'text');
        if (in_array($type, ['submit', 'button', 'file', 'image', 'reset'], true)) {
            continue;
        }
        if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
            continue;
        }
        $pairs[] = [$name, $el->getAttribute('value') ?: ($type === 'checkbox' ? 'on' : '')];
    } elseif ($tag === 'textarea') {
        $pairs[] = [$name, $el->textContent];
    } else {
        $selected = $xp->query('.//option[@selected]', $el);
        $options = $selected->length ? $selected : $xp->query('.//option[1]', $el);
        foreach ($options as $opt) {
            $pairs[] = [$name, $opt->getAttribute('value')];
            if (!$el->hasAttribute('multiple')) {
                break;
            }
        }
    }
}
$out = [];
$seen = [];
foreach ($pairs as [$k, $v]) {
    if (array_key_exists($k, $overrides)) {
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $v = $overrides[$k];
    }
    $out[] = rawurlencode($k) . '=' . rawurlencode($v);
}
foreach ($overrides as $k => $v) {
    if (!isset($seen[$k]) && !in_array($k, array_column($pairs, 0), true)) {
        $out[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
}
echo implode('&', $out);
