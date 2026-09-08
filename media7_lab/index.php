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
    'prezi'        => ['label' => 'Prezi',              'domains' => ['prezi.com']],
    'codepen'      => ['label' => 'CodePen',            'domains' => ['codepen.io']],
    'jsfiddle'     => ['label' => 'JSFiddle',           'domains' => ['jsfiddle.net']],
    'googlesheets' => ['label' => 'Google Sheets',      'domains' => ['docs.google.com']],
    'falstad'      => ['label' => 'Falstad / CircuitJS','domains' => ['falstad.com']],
    'gist'         => ['label' => 'GitHub Gist',        'domains' => ['gist.github.com']],
    'medium'       => ['label' => 'Medium',             'domains' => ['medium.com']],
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
        $error = 'Entrée refusée par le labo: maximum 1 MiB pour éviter de bloquer la page de debug.';
    } elseif (!is_dir($srcRoot)) {
        $error = 'Le dossier src/ du repo s9e est introuvable. Place media7_lab directement dans la racine de taxt_fomata.';
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

$status = 'idle';
if ($error !== null) {
    $status = 'error';
} elseif ($input !== '' && $detectedProvider !== null) {
    $status = 'match';
} elseif ($input !== '') {
    $status = 'nomatch';
}

$summary = '';
if ($input !== '') {
    $lines = [];
    $lines[] = '=== MEDIA7 LAB RESULT ===';
    $lines[] = 'INPUT:';
    $lines[] = $input;
    $lines[] = '';
    $lines[] = 'HOST: ' . ($host ?: '—');
    $lines[] = 'EXPECTED: ' . ($expectedProvider ? $providers[$expectedProvider]['label'] : '—');
    $lines[] = 'DETECTED: ' . ($detectedProvider ? $providers[$detectedProvider]['label'] : '—');
    $lines[] = 'STATUS: ' . $status;
    if ($error !== null) {
        $lines[] = 'ERROR: ' . $error;
    }
    $lines[] = '';
    $lines[] = 'CAPTURED ATTRIBUTES:';
    if ($capturedAttributes) {
        foreach ($capturedAttributes as $k => $v) {
            $lines[] = $k . '=' . $v;
        }
    } else {
        $lines[] = '—';
    }
    $lines[] = '';
    $lines[] = 'FINAL IFRAME SRC:';
    if ($iframes) {
        foreach ($iframes as $i => $frame) {
            $lines[] = '#' . ($i + 1) . ' media=' . ($frame['media'] ?: '—');
            $lines[] = $frame['src'];
        }
    } else {
        $lines[] = '—';
    }
    $lines[] = '';
    $lines[] = 'XML:';
    $lines[] = $xml ?: '—';
    $lines[] = '';
    $lines[] = 'HTML:';
    $lines[] = $html ?: '—';
    $summary = implode("\n", $lines);
}

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<base href="https://media7-lab.invalid/">
<title>MEDIA7 LAB — s9e default parser</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:dark;background:#0c0d10;color:#ececf1}*{box-sizing:border-box}body{margin:0;background:#0c0d10;color:#ececf1}.wrap{max-width:1380px;margin:0 auto;padding:28px}.top{display:flex;gap:18px;align-items:flex-start;justify-content:space-between;margin-bottom:22px}.title h1{font-size:26px;margin:0 0 7px}.title p{margin:0;color:#a8abb4;max-width:850px;line-height:1.5}.badge{display:inline-flex;align-items:center;border:1px solid #343741;border-radius:999px;padding:7px 10px;font-size:12px;color:#cdd0d9;background:#15171d}.grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(360px,.9fr);gap:18px}.card{background:#14161b;border:1px solid #292c34;border-radius:14px;padding:18px;box-shadow:0 12px 34px rgba(0,0,0,.16)}h2{font-size:16px;margin:0 0 12px}label{font-size:13px;color:#c4c7d0}.input,.copybox{width:100%;min-height:128px;margin-top:8px;padding:12px 13px;background:#0e1014;border:1px solid #343842;border-radius:10px;color:#f3f3f5;font:13px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;resize:vertical}.copybox{min-height:280px}.controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px}.controls select,.controls button,.copybtn{border:1px solid #3a3e48;background:#1c1f26;color:#f2f2f4;border-radius:9px;padding:9px 11px}.controls button,.copybtn{cursor:pointer;font-weight:650;background:#eeeeef;color:#111217;border-color:#eeeeef}.copybtn.done{background:#b9f5c9}.check{display:flex;align-items:center;gap:7px}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px}.metric{background:#0f1116;border:1px solid #282b33;border-radius:10px;padding:10px}.metric b{display:block;font-size:12px;color:#8f94a0;margin-bottom:4px}.metric span{font-size:13px;overflow-wrap:anywhere}.ok{color:#81d89b}.muted{color:#969aa5}.providers{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.provider{border:1px solid #2b2e36;border-radius:10px;padding:10px;background:#101217}.provider strong{font-size:13px}.provider small{display:block;color:#8f939d;margin-top:3px}.provider button{margin-top:8px;border:1px solid #3b3f48;background:#1b1e24;color:#ddd;border-radius:7px;padding:6px 8px;cursor:pointer;font-size:11px}.section{margin-top:18px}.code{white-space:pre-wrap;overflow-wrap:anywhere;background:#0b0d11;border:1px solid #272a31;border-radius:10px;padding:12px;max-height:320px;overflow:auto;font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;color:#d7d9df}.result{background:white;color:#111;border-radius:10px;padding:10px;min-height:120px;overflow:auto}.error{background:#2b1518;border:1px solid #633138;color:#ffb7bd;border-radius:10px;padding:11px;margin-top:12px}.nomatch{background:#292312;border:1px solid #65552c;color:#f5d98c;border-radius:10px;padding:11px;margin-top:12px}.attrs{width:100%;border-collapse:collapse;font-size:12px}.attrs th,.attrs td{text-align:left;border-bottom:1px solid #2b2e35;padding:8px;vertical-align:top}.attrs th{color:#9499a5;font-weight:600}.attrs td{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}.note{font-size:12px;color:#9297a2;line-height:1.5;margin-top:10px}details{border:1px solid #292c34;border-radius:10px;margin-top:8px;background:#101217}summary{cursor:pointer;padding:9px 10px;font-size:12px;color:#cfd1d7}details .code{border:0;border-top:1px solid #292c34;border-radius:0;margin:0;max-height:220px}.full{grid-column:1/-1}@media(max-width:900px){.grid{grid-template-columns:1fr}.metrics{grid-template-columns:1fr 1fr}.providers{grid-template-columns:1fr}.wrap{padding:16px}.top{flex-direction:column}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="title">
      <h1>MEDIA7 LAB</h1>
      <p>Testeur du <strong>MediaPack s9e par défaut</strong> pour Prezi, CodePen, JSFiddle, Google Sheets, Falstad/CircuitJS, GitHub Gist et Medium. L’entrée originale est donnée directement au parser du repo.</p>
    </div>
    <div class="badge">backend: s9e\TextFormatter\Bundles\MediaPack</div>
  </div>

  <div class="grid">
    <section class="card">
      <h2>1. Entrée brute</h2>
      <form method="post" action="http://<?= h($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080') ?><?= h($_SERVER['PHP_SELF'] ?? '/index.php') ?>">
        <input type="hidden" name="submitted" value="1">
        <label>Colle exactement l’URL ou le `[media]...[/media]` que tu veux tester.</label>
        <textarea id="input" class="input" name="input" spellcheck="false" placeholder="https://www.falstad.com/circuit/circuitjs.html?ctz=..."><?= h($input) ?></textarea>
        <div class="controls">
          <button type="submit">Passer dans s9e</button>
          <label>Thème
            <select name="theme">
              <option value="default"<?= $theme === 'default' ? ' selected' : '' ?>>default</option>
              <option value="light"<?= $theme === 'light' ? ' selected' : '' ?>>light</option>
              <option value="dark"<?= $theme === 'dark' ? ' selected' : '' ?>>dark</option>
            </select>
          </label>
          <label class="check"><input type="checkbox" name="load_external" value="1"<?= $loadExternal ? ' checked' : '' ?>> charger l’embed externe</label>
        </div>
      </form>

      <?php if ($error !== null): ?>
        <div class="error"><strong>Erreur :</strong> <?= h($error) ?></div>
      <?php elseif ($status === 'nomatch'): ?>
        <div class="nomatch">Le domaine est dans les 7 autorisés, mais le parser s9e n’a produit aucun tag média correspondant.</div>
      <?php endif; ?>

      <div class="metrics">
        <div class="metric"><b>Host</b><span><?= h($host ?: '—') ?></span></div>
        <div class="metric"><b>Route attendue</b><span><?= h($expectedProvider ? $providers[$expectedProvider]['label'] : '—') ?></span></div>
        <div class="metric"><b>Détecté par s9e</b><span class="<?= $detectedProvider ? 'ok' : 'muted' ?>"><?= h($detectedProvider ? $providers[$detectedProvider]['label'] : '—') ?></span></div>
        <div class="metric"><b>Taille entrée</b><span><?= number_format(strlen($input), 0, ',', ' ') ?> octets</span></div>
      </div>
    </section>

    <aside class="card">
      <h2>2. Les 7 définitions du repo</h2>
      <div class="providers">
        <?php foreach ($providers as $id => $provider): ?>
          <div class="provider">
            <strong><?= h($provider['label']) ?></strong>
            <small><?= h(implode(', ', $provider['domains'])) ?></small>
            <?php if (!empty($examples[$id])): ?>
              <button type="button" data-example="<?= h($examples[$id][0]) ?>" onclick="loadExample(this.dataset.example)">charger exemple</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="note">Les exemples et définitions ci-dessous sont lus directement depuis <code>src/Plugins/MediaEmbed/Configurator/sites/*.xml</code> du repo local.</p>
      <?php foreach ($providers as $id => $provider): ?>
        <details>
          <summary><?= h($provider['label']) ?> — définition XML</summary>
          <div class="code"><?= h($definitions[$id] ?: '[fichier introuvable]') ?></div>
        </details>
      <?php endforeach; ?>
    </aside>

    <?php if ($input !== ''): ?>
      <section class="card full">
        <h2>3. Résumé copiable</h2>
        <p class="note">Ce bloc contient l’entrée, les attributs capturés, le <code>src</code> final, le XML et le HTML. Copie-le et colle-le directement dans le chat.</p>
        <textarea id="copySummary" class="copybox" readonly><?= h($summary) ?></textarea>
        <div class="controls"><button id="copyBtn" class="copybtn" type="button" onclick="copySummary()">Copier le résumé</button></div>
      </section>
    <?php endif; ?>

    <?php if ($input !== '' && $error === null): ?>
      <section class="card">
        <h2>4. Captures du parser</h2>
        <?php if ($capturedAttributes): ?>
          <table class="attrs">
            <thead><tr><th>Attribut</th><th>Valeur capturée</th></tr></thead>
            <tbody>
            <?php foreach ($capturedAttributes as $k => $v): ?>
              <tr><td><?= h((string) $k) ?></td><td><?= h((string) $v) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted">Aucun attribut de l’un des 7 tags n’a été capturé.</p>
        <?php endif; ?>
        <div class="section">
          <h2>XML intermédiaire</h2>
          <div class="code"><?= h($xml) ?></div>
        </div>
      </section>

      <section class="card">
        <h2>5. Renderer s9e</h2>
        <?php if ($iframes): ?>
          <table class="attrs">
            <thead><tr><th>#</th><th>data-s9e-mediaembed</th><th>src final</th></tr></thead>
            <tbody>
            <?php foreach ($iframes as $i => $frame): ?>
              <tr><td><?= $i + 1 ?></td><td><?= h($frame['media'] ?: '—') ?></td><td><?= h($frame['src']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted">Aucun iframe produit.</p>
        <?php endif; ?>
        <div class="section">
          <h2>HTML final exact</h2>
          <div class="code"><?= h($html) ?></div>
        </div>
      </section>

      <section class="card full">
        <h2>6. Résultat live</h2>
        <p class="note">Le labo n’ajoute pas de sandbox ni de paramètres au HTML de s9e. Le <code>&lt;base href="https://…"&gt;</code> sert uniquement à résoudre les URLs <code>//provider/…</code> en HTTPS.</p>
        <?php if ($loadExternal): ?>
          <div class="result"><?= $html ?></div>
        <?php else: ?>
          <div class="code">Chargement externe désactivé. Le parser et le renderer ont quand même été exécutés.</div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
<script>
function loadExample(value){
  const box=document.getElementById('input');
  box.value=value;
  box.focus();
  box.scrollIntoView({behavior:'smooth',block:'center'});
}
async function copySummary(){
  const box=document.getElementById('copySummary');
  const btn=document.getElementById('copyBtn');
  if(!box) return;
  try{
    await navigator.clipboard.writeText(box.value);
  }catch(e){
    box.focus();
    box.select();
    document.execCommand('copy');
  }
  if(btn){
    const old=btn.textContent;
    btn.textContent='Copié';
    btn.classList.add('done');
    setTimeout(()=>{btn.textContent=old;btn.classList.remove('done')},1200);
  }
}
</script>
</body>
</html>
