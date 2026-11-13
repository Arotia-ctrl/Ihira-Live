# IHIRA LIVE



Application locale (PHP 8 + SQLite) de gestion des notes du jury, des votes du public (3 phases) et de diffusion des résultats via OBS Studio. 100 % hors ligne.

## Installation (Windows 11)
1. Installer PHP 8.1+ (zip « Thread Safe » depuis windows.php.net), l'ajouter au PATH.
2. Dans `php.ini` (copier `php.ini-development`), activer : `extension=pdo_sqlite`, `extension=sqlite3`, `extension=mbstring`.
3. Lancer `start.bat` → http://localhost:8000
4. Connexion : **admin / admin123** → changez le mot de passe immédiatement (Tableau de bord).

La base `database/chorale.sqlite` se crée toute seule au premier lancement.

## Polices (hors ligne)
Déposer `Montserrat.woff2` et `PlayfairDisplay.woff2` (ou `Montserrat.ttf` et `PlayfairDisplay.ttf`) dans `public/assets/fonts/` (sinon police système de secours).

## OBS Studio (28+ : WebSocket 5 intégré)
1. OBS → Outils → Paramètres du serveur WebSocket : activer, port 4455, définir un mot de passe.
2. Créer une scène « IHIRA » avec une source **Navigateur** : URL `http://localhost:8000/display/`, largeur 1920, hauteur 1080, cocher « Actualiser le navigateur quand la scène devient active ».
3. (Option) créer d'autres scènes (pause, caméra…) pour les basculer depuis la page **Diffusion**.
4. Projecteur plein écran (clic droit sur l'aperçu → Projecteur plein écran (Programme)) sur le 2e écran (HDMI → splitter).
5. Page **Diffusion** → saisir le mot de passe OBS → Connecter. Le navigateur de la régie doit rester ouvert pendant l'événement.

## Les 3 phases
| Phase | Qui compte | Score | À l'écran |
|---|---|---|---|
| 1 · Éliminatoires | Jury seul (aucun vote extérieur) | note du jury | notes du jury, classement, liste des qualifiés |
| 2 · Demi-finale | Qualifiés de la phase 1 | 60 % public + 40 % jury | votes du public → notes du jury → score combiné et classement |
| 3 · Finale | Finalistes de la phase 2 | 80 % public + 20 % jury | votes du public → notes du jury → score combiné et classement final, podium, grand gagnant |

Calcul des phases 2 et 3 : points public = (votes ÷ votes du premier) × 100 × poids ; points jury = (note ÷ note max) × 100 × poids ; score = somme des deux.
Nombre de qualifiés, de finalistes et note maximale du jury : Tableau de bord → Événement.

## Déroulé le jour J
Compétiteurs → Notes & votes (phase 1) → Valider → Diffusion : classement, résultat individuel, qualifiés → « Passer à la phase 2 » → saisir votes + notes → Valider → Diffusion : bouton **Révéler** (1 = public, 2 = jury, 3 = addition et classement) → idem phase 3, puis podium et grand gagnant.

## Logos, transition et textes
- **Logos** : gauche et droite restent affichés en haut dans toutes les scènes ; le logo du milieu est sur l'accueil et dans la transition. Deux façons de les fournir : (1) Tableau de bord → Événement (envoi, réduit automatiquement à 900 px si l'extension GD de PHP est active), ou (2) déposer les fichiers dans `public/assets/img/` sous les noms `milieu.png`, `haut_gauche.png`, `haut_droite.png` (ou changer les noms dans `app/texts.php`). L'envoi du Tableau de bord a priorité.
- **Transition** : à chaque changement de scène, un balayage doré et les 3 logos qui convergent. Désactivable / réglable dans `app/texts.php` (`transition`).
- **Textes** : tous les textes de l'écran sont dans `app/texts.php` (F5 suffit). Nom du concours, date, organisation : Tableau de bord. Noms des phases et pondérations : `app/bootstrap.php` (`PHASES`, `WEIGHTS`).
- Lancer avec `start.bat` (il relève la limite d'envoi de PHP, 2 Mo par défaut).
- **Générique des logos** : la scène « 08 · Générique des logos (animation) » fait défiler les trois logos un à un au milieu de l'écran, puis tous ensemble, puis tout disparaît (environ 10 s). Republiez la scène pour la rejouer. Les logos des coins sont masqués pendant le générique et reviennent à la fin.

## Nouveautés v6
- **Notes par juré** : le nombre de jurés (1 à 9) se règle dans le Tableau de bord ; la note du jury est la moyenne. En phase 1, l'écran affiche la note de chaque juré, la moyenne et le rang. Pas de photos en phase 1 (elles apparaissent à partir de la phase 2).
- **Phases 2 et 3** : l'écran n'affiche plus aucun calcul (ni « + pts », ni pourcentages) : votes du public, puis note du jury, puis score final et classement.
- **Scène suivante** (Diffusion) : choisissez la scène à afficher ensuite ; l'écran la charge aussitôt en coulisses. « ▶ Lancer la suivante maintenant » la publie sans délai. Le compte à rebours a été supprimé : il n'est plus nécessaire.
- **Textes** : page « Textes » du menu (prioritaire sur `app/texts.php`).
- **Retour en arrière / tests** : Notes & votes → bouton « Phase 1 » (puis « Déverrouiller »), ou « Zone de test » pour tout effacer.
- Animations plus rapides (`in_ms`, `out_ms`, `hold_ms`, `count_ms` dans `app/texts.php`).

## Nouveautés v7
- **Correction** : le classement n'affichait qu'une personne, les notes restaient à 0 et les colonnes jury / moyenne / votes restaient vides (une variable manquante dans `display.js` bloquait la mise à jour des scènes). Corrigé, ce qui rétablit aussi la révélation par étapes.
- **Transition (scène 08)** : environ 6 s au lieu de 10 s (réglable : `jingle` → `speed` dans `app/texts.php`, 0.7 = plus rapide). Après les logos, le texte de la phase en cours défile (« Phase 1 · Éliminatoires », etc. ; modifiable dans la page Textes).
- **Transition + scène suivante** : si une scène suivante est choisie dans la régie, elle s'affiche toute seule à la fin de la transition.
- **Compétiteurs** : plus de nom complet, seulement le nom de scène et un numéro unique (refus d'un numéro déjà pris).
- **Accueil** : le nom de la phase en cours est écrit au-dessus de la date (et à côté du titre du Tableau de bord).
- **Grand gagnant** : petit suspense (anneaux qui battent, « Et le grand gagnant est… »), éclair puis révélation et confettis. Durée : `winner_suspense_ms` dans `app/texts.php` (0 = sans suspense).
- **Texte du balayage** entre deux scènes : `transition_text` (vide par défaut), modifiable dans la page Textes.
- **Révélation** : l'aide de la régie indique à quelles scènes elle sert (phases 2 et 3 : résultat individuel et classement ; podium). En phase 1, tout s'affiche d'un coup.
