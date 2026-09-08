<?php declare(strict_types=1);

/**
 * MEDIA7 LAB — s9e/TextFormatter default MediaPack test harness
 */

$repoRoot = dirname(__DIR__);
$srcRoot  = $repoRoot . '/src';

spl_autoload_register(static function (string $class) use ($srcRoot): void {
    $prefix = 's9e\\TextFormatter\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $srcRoot . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$providers = [
    'prezi' => ['label' => 'Prezi', 'domains' => ['prezi.com']],
    'codepen' => ['label' => 'CodePen', 'domains' => ['codepen.io']],
    'jsfiddle' => ['label' => 'JSFiddle', 'domains' => ['jsfiddle.net']],
    'googlesheets' => ['label' => 'Google Sheets', 'domains' => ['docs.google.com']],
    'falstad' => ['label' => 'Falstad / CircuitJS', 'domains' => ['falstad.com']],
    'gist' => ['label' => 'GitHub Gist', 'domains' => ['gist.github.com']],
    'medium' => ['label' => 'Medium', 'domains' => ['medium.com']],
];

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hostMatches(string $host, string $domain): bool
{
    $host = strtolower(rtrim($host, '.'));
    $domain = strtolower($domain);
    return $host === $domain || str_ends_with($host, '.' . $domain);
}

function extractFirstUrl(string $input): ?string
{
    if (preg_match('~https?://[^\\s\\[\\]<>"\']+~i', $input, $m)) {
        return $m[0];
    }
    return null;
}

function inferProviderFromHost(string $host, array $providers): ?string
{
    foreach ($providers as $id => $provider) {
        foreach ($provider['domains'] as $domain) {
            if (hostMatches($host, $domain)) {
                return $id;
            }
        }
    }
    return null;
}

function parseExamples(string $xml): array
{
    if (!preg_match_all('~<example>(.*?)</example>~s', $xml, $m)) {
        return [];
    }
    return array_map(
        static fn(string $v): string => html_entity_decode(trim($v), ENT_QUOTES | ENT_XML1, 'UTF-8'),
        $m[1]
    );
}

function detectMediaFromXml(string $xml, array $providers): array
{
    foreach (array_keys($providers) as $id) {
        $tag = strtoupper($id);
        if (preg_match('~<' . preg_quote($tag, '~') . '\\b([^>]*)/?' . '>~', $xml, $m)) {
            $attrs = [];
            if (preg_match_all('~([A-Za-z_][A-Za-z0-9_.:-]*)="([^"]*)"~', $m[1], $a, PREG_SET_ORDER)) {
                foreach ($a as $attr) {
                    $attrs[$attr[1]] = html_entity_decode($attr[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            return [$id, $attrs];
        }
    }
    return [null, []];
}

function extractIframes(string $html): array
{
    $iframes = [];
    if (!preg_match_all('~<iframe\\b[^>]*>~i', $html, $matches)) {
        return $iframes;
    }
    foreach ($matches[0] as $tag) {
        $row = ['src' => '', 'media' => ''];
        if (preg_match('~\\bsrc="([^"]*)"~i', $tag, $m)) {
            $row['src'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('~\\bdata-s9e-mediaembed="([^"]*)"~i', $tag, $m)) {
            $row['media'] = $m[1];
        }
        $iframes[] = $row;
    }
    return $iframes;
}

$definitions = [];
$examples = [];
foreach ($providers as $id => $_provider) {
    $path = $srcRoot . '/Plugins/MediaEmbed/Configurator/sites/' . $id . '.xml';
    $definitions[$id] = is_file($path) ? (string) file_get_contents($path) : '';
    $examples[$id] = parseExamples($definitions[$id]);
}

$input = isset($_POST['input']) ? (string) $_POST['input'] : '';
$theme = isset($_POST['theme']) ? (string) $_POST['theme'] : 'default';
if (!in_array($theme, ['default', 'light', 'dark'], true)) {
    $theme = 'default';
}
$loadExternal = !isset($_POST['submitted']) || isset($_POST['load_external']);

$error = null;
$xml = '';
$html = '';
$detectedProvider = null;
$capturedAttributes = [];
$expectedProvider = null;
$firstUrl = null;
$host = null;
$iframes = [];

if ($input !== '') {
    if (strlen($input) > 1024 * 1024) {
        $error = 'Entrée refusée par le labo: maximum 1 MiB.';
    } elseif (!is_dir($srcRoot)) {
        $error = 'Le dossier src/ du repo s9e est introuvable.';
    } else {
        $firstUrl = extractFirstUrl($input);
        if ($firstUrl === null) {
            $error = 'Aucune URL http/https trouvée dans l’entrée.';
        } else {
            $parts = @parse_url($firstUrl);
            $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
            $expectedProvider = inferProviderFromHost($host, $providers);
            if ($expectedProvider === null) {
                $error = 'Ce labo n’autorise que les 7 domaines testés.';
            } else {
                try {
                    $xml = \s9e\TextFormatter\Bundles\MediaPack::parse($input);
                    [$detectedProvider, $capturedAttributes] = detectMediaFromXml($xml, $providers);
                    $params = [];
                    if ($theme !== 'default') {
                        $params['MEDIAEMBED_THEME'] = $theme;
                    }
                    $html = \s9e\TextFormatter\Bundles\MediaPack::render($xml, $params);
                    $iframes = extractIframes($html);
                } catch (Throwable $e) {
                    $error = get_class($e) . ': ' . $e->getMessage();
                }
            }
        }
    }
}

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MEDIA7 LAB</title>
<style>
body{font-family:system-ui,sans-serif;background:#0c0d10;color:#ececf1;margin:0}.wrap{max-width:1200px;margin:auto;padding:24px}.card{background:#15171c;border:1px solid #2d3038;border-radius:12px;padding:16px;margin-bottom:16px}textarea{width:100%;min-height:130px;background:#0d0f13;color:#fff;border:1px solid #3a3e47;border-radius:8px;padding:10px;font-family:monospace;box-sizing:border-box}button,select{padding:9px 12px;margin:8px 6px 0 0}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.provider{background:#101217;border:1px solid #2c2f36;border-radius:8px;padding:10px}.code{white-space:pre-wrap;overflow-wrap:anywhere;background:#090b0e;border:1px solid #282b31;border-radius:8px;padding:10px;max-height:360px;overflow:auto;font-family:monospace;font-size:12px}.result{background:white;color:#111;padding:10px;border-radius:8px;overflow:auto}.error{color:#ffb7bd;background:#2b1518;padding:10px;border-radius:8px;margin-top:10px}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #2c2f36;padding:7px;text-align:left;vertical-align:top}@media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body><div class="wrap">
<h1>MEDIA7 LAB</h1>
<p>Parser réel <code>s9e\TextFormatter\Bundles\MediaPack</code> pour 7 providers.</p>

<div class="card">
<form method="post">
<input type="hidden" name="submitted" value="1">
<label>URL ou BBCode média :</label>
<textarea id="input" name="input" spellcheck="false"><?= h($input) ?></textarea>
<br>
<button type="submit">Passer dans s9e</button>
<select name="theme">
<option value="default"<?= $theme==='default'?' selected':'' ?>>default</option>
<option value="light"<?= $theme==='light'?' selected':'' ?>>light</option>
<option value="dark"<?= $theme==='dark'?' selected':'' ?>>dark</option>
</select>
<label><input type="checkbox" name="load_external" value="1"<?= $loadExternal?' checked':'' ?>> charger l’embed externe</label>
</form>
<?php if ($error !== null): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
</div>

<div class="card">
<h2>Exemples des 7 providers</h2>
<div class="grid">
<?php foreach ($providers as $id => $provider): ?>
<div class="provider">
<strong><?= h($provider['label']) ?></strong><br><small><?= h(implode(', ', $provider['domains'])) ?></small><br>
<?php if (!empty($examples[$id])): ?><button type="button" data-example="<?= h($examples[$id][0]) ?>" onclick="document.getElementById('input').value=this.dataset.example">charger exemple</button><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>

<?php if ($input !== ''): ?>
<div class="card">
<h2>Résultat</h2>
<table>
<tr><th>Host</th><td><?= h($host ?: '—') ?></td></tr>
<tr><th>Provider attendu</th><td><?= h($expectedProvider ?? '—') ?></td></tr>
<tr><th>Détecté par s9e</th><td><?= h($detectedProvider ?? '—') ?></td></tr>
</table>
</div>

<div class="card"><h2>Attributs capturés</h2>
<?php if ($capturedAttributes): ?><table><?php foreach ($capturedAttributes as $k=>$v): ?><tr><th><?= h((string)$k) ?></th><td><code><?= h((string)$v) ?></code></td></tr><?php endforeach; ?></table><?php else: ?><p>—</p><?php endif; ?>
</div>

<div class="card"><h2>XML intermédiaire s9e</h2><div class="code"><?= h($xml) ?></div></div>
<div class="card"><h2>HTML final</h2><div class="code"><?= h($html) ?></div></div>
<div class="card"><h2>Iframe(s) finale(s)</h2>
<?php if ($iframes): ?><table><tr><th>media</th><th>src</th></tr><?php foreach ($iframes as $frame): ?><tr><td><?= h($frame['media']) ?></td><td><code><?= h($frame['src']) ?></code></td></tr><?php endforeach; ?></table><?php else: ?><p>—</p><?php endif; ?>
</div>
<?php if ($loadExternal && $html !== ''): ?><div class="card"><h2>Rendu externe</h2><div class="result"><?= $html ?></div></div><?php endif; ?>
<?php endif; ?>

</div></body></html>
