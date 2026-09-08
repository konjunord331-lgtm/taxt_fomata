# MEDIA7 LAB

Petit labo local qui passe réellement les URLs dans le **MediaPack s9e/TextFormatter par défaut** du repo `taxt_fomata`.

## Providers testés

- Prezi
- CodePen
- JSFiddle
- Google Sheets
- Falstad / CircuitJS
- GitHub Gist
- Medium

## Installation

Place simplement le dossier `media7_lab` directement dans la racine du repo :

```text
taxt_fomata/
├─ src/
├─ composer.json
└─ media7_lab/
   ├─ index.php
   ├─ start.bat
   └─ README.md
```

Sur Windows, double-clique ensuite sur `media7_lab/start.bat`.

Adresse : `http://127.0.0.1:8080/`

## Ce qui est réellement testé

Le labo appelle :

```php
s9e\TextFormatter\Bundles\MediaPack::parse($input);
s9e\TextFormatter\Bundles\MediaPack::render($xml, $params);
```

Il ne réécrit pas les regex et ne construit pas lui-même les iframes.

Le bundle MediaPack contient tous les providers s9e par défaut, mais le labo applique **avant le parser** une gate de domaine limitée aux 7 providers ci-dessus. Pour ces 7 domaines, les regex, captures et templates sont donc ceux du bundle compilé du repo.

Le panneau de debug montre :

1. le host de l'URL;
2. le provider attendu;
3. le provider réellement détecté par s9e;
4. les attributs capturés (`id`, `cct`, `ctz`, `oid`, etc.);
5. le XML intermédiaire;
6. le HTML final exact produit par le renderer;
7. les `src` d'iframe finales;
8. le rendu live.

## Notes

- Aucune sandbox supplémentaire n'est ajoutée au rendu live, afin de ne pas fausser le comportement par défaut.
- Le formulaire utilise POST, pratique pour les longues URLs Falstad.
- Le labo limite seulement l'entrée à 1 MiB pour éviter de geler la page de debug.
- `MEDIAEMBED_THEME` peut être testé avec `default`, `light` ou `dark`.
